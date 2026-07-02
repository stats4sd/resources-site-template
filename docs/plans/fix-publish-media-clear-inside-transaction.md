# Fix: `copyMedia(replace: true)` deletes canonical media on disk inside a non-restorable transaction

**Status:** Not Started

Addresses finding #8 of [docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md).

## The bug

`TrovePublisher::publish()` runs inside a single `DB::transaction`. When folding a shadow draft onto its canonical it calls `copyMedia($draft, $canonical, replace: true)` ([app/Services/TrovePublisher.php:127](../../app/Services/TrovePublisher.php#L127)). `copyMedia` with `replace: true` calls `$canonical->clearMediaCollection($name)` ([app/Services/TrovePublisher.php:213](../../app/Services/TrovePublisher.php#L213)), which physically deletes the canonical's files from disk (S3/local) **before** the draft's files are copied in.

If anything after the clear fails (disk/S3 error, a bad draft media row, connection drop), the `DB::transaction` rolls back the *rows* — but the canonical's original files are already gone from disk. Result: a published trove whose media rows point at files that no longer exist, with no DB record of the intended state. The DB rollback cannot restore a deleted S3 object.

## Root cause — there are TWO disk-deletion paths, not one

1. **Explicit clear.** `clearMediaCollection()` → `$media->delete()` → Spatie's `deleting` model event deletes the media directory synchronously.
2. **Implicit single-file eviction.** `cover_image_{locale}` collections are declared `->singleFile()` ([app/Models/Trove.php:266](../../app/Models/Trove.php#L266)). Even if we removed the explicit clear, Spatie's `FileAdder` auto-evicts the excess when a second file is added to a size-limited collection: after `toMediaCollection()` it calls `clearMediaCollectionExcept(...)` ([vendor FileAdder.php:645-652](../../vendor/spatie/laravel-medialibrary/src/MediaCollections/FileAdder.php#L645)), which again `$media->delete()`s the old file — still inside the transaction.

So any fix must neutralise **both** paths. The design principle: **no irreversible disk operation may run inside the publish transaction.** Disk deletion of the superseded files happens only *after* the DB has committed.

The mirror call in `draftFor()` ([app/Services/TrovePublisher.php:61](../../app/Services/TrovePublisher.php#L61)) uses `replace: false` onto a freshly-`replicate()`d draft whose collections are empty, so it deletes nothing and is out of scope — but it still copies files inside a transaction (see Risks).

## Design constraint check

- Spatie's `DefaultPathGenerator` keys the storage directory on `media.id`, **not** on `collection_name` ([config/media-library.php:79](../../config/media-library.php#L79)). Therefore changing a media row's `collection_name` is a pure DB update — it moves no files. This is what makes the recommended option cheap and rollback-safe.
- Copying a draft media via `$media->copy($to, ...)` always allocates a **new** `media.id` / directory, so copied-in files never collide with the canonical's existing files on disk.

## Options considered

### Option A — Detach-then-defer (recommended)

Inside the transaction, move the canonical's superseded media *out of the live collection* into a per-publish "trash" collection by renaming `collection_name` (DB-only, no disk op). Then copy the draft's media into the now-empty live collection (no single-file eviction fires, because the collection has no rows). After the transaction **commits**, physically delete the trashed media through Spatie's normal `$media->delete()` (which correctly cleans originals + conversions + responsive images).

- Rollback safety: on failure the transaction reverts the `collection_name` renames — the canonical's media is fully intact and every file is still on disk (we never touched disk inside the transaction). ✅
- Post-commit cleanup failure only leaves orphaned trash rows/files — storage clutter, never data loss or broken display. Swept up by a fallback command (below).
- Cleanup goes through Spatie, so conversions/responsive variants are handled for free — no manual path arithmetic.

### Option B — Row-delete-preserving-file + manual disk sweep

Inside the transaction, capture each stale media's `(id, disk)`, delete the rows via the query builder (`Media::whereKey($ids)->delete()`, which bypasses model events so files survive), copy the draft media in, commit, then `Storage::disk($disk)->deleteDirectory((string) $id)` after commit.

- Works, and leaves no lingering rows. But we hand-delete directories, so we own the conversion/responsive-image directory layout ourselves and must keep it in sync with Spatie's path generator. More brittle than A. Rejected as primary.

### Option C — Move all media work outside the transaction

Publish the trove row + relations in the transaction; do media replacement afterwards with its own recovery. Cleanest separation but loses row/media atomicity and needs its own failure-recovery story for the media half. More invasive than the bug warrants. Rejected.

**Recommendation: Option A.** Smallest change, reuses Spatie for cleanup, and the "trash collection" gives a natural, idempotent tidy-up target.

## Implementation (Option A)

### 1. Rework `copyMedia` / the replace path in `TrovePublisher`

Split the current one-shot copy into a transaction-safe phase and a post-commit phase.

- Add a trash-collection naming convention, e.g. a constant `TRASH_SUFFIX = '__superseded'` (any name that can never collide with a registered collection).
- New private helper `stashMediaForReplacement(Trove $to, string $collectionName): void` — for each existing media in `$collectionName`, update `collection_name` to `"{$collectionName}{$suffix}"`. Pure DB update (no disk op). Prefer a single bulk update per collection over per-model saves so no Spatie media events fire.
- Rewrite `copyMedia(replace: true)` so that, per registered collection, it **stashes** the target's existing media (instead of `clearMediaCollection`) and then copies `$from`'s media in. Because the live collection is now empty, single-file eviction never triggers a disk delete.
- `copyMedia(replace: false)` (the `draftFor` path) is unchanged.

The `publish()` transaction body stays as-is except that the disk-destructive `clearMediaCollection` is gone.

### 2. Tidy up after the transaction commits

`publish()` currently `return`s the canonical from inside `DB::transaction(...)`. Restructure so the transaction returns its result, then run cleanup outside it:

- Capture the canonical after the transaction commits.
- Call a new `purgeSupersededMedia(Trove $canonical): void` that finds every media whose `collection_name` ends with the trash suffix and `$media->delete()`s it (Spatie removes files + conversions + responsive). Wrap this in a try/catch that logs on failure but does **not** rethrow — the publish has already succeeded and must not surface as an error; leftover trash is handled by the fallback sweep.

Do the same restructure for the never-published in-place branch only if it ever gains a replace path (it currently does not — no change needed there).

### 3. Fallback sweep command (defensive tidy-up)

Post-commit cleanup can itself fail (process killed, transient S3 error). Add an idempotent command to reclaim any orphaned trash media:

- `app/Console/Commands/PruneSupersededMedia.php` (follow the existing `PurgeTelescopeEntries` command style) — deletes all `media` rows whose `collection_name` ends with the trash suffix and whose owning trove still exists, via Spatie `$media->delete()`.
- Register it on a low-frequency schedule (e.g. daily) in the console kernel / `routes/console.php`.
- This is the "method to tidy up afterwards" the review asks for: the happy path cleans up immediately post-commit; the sweep guarantees eventual cleanup if that ever fails.

## Testing

Feature tests against MySQL (per project convention), using `local`/`public` disks:

1. **Happy path.** Published trove with a cover image + content files; edit the draft to change cover and add a content file; `publish()`. Assert: canonical ends with exactly the draft's media in the live collections, files exist on disk, and **no** `*__superseded` rows remain.
2. **Rollback safety (the regression test for #8).** Force a failure inside the transaction *after* the media stash (e.g. mock `copyMedia`/relation sync to throw, or a DB error). Assert: transaction rolled back, canonical's **original** media rows are intact and their **files still exist on disk**. This is the test that fails on the current code.
3. **Post-commit cleanup failure is non-fatal.** Make `purgeSupersededMedia` throw; assert `publish()` still returns a correctly-published canonical (new media live), and that `PruneSupersededMedia` then removes the leftover trash rows/files.
4. **Single-file collection.** Confirm replacing a `cover_image_*` (singleFile) image does not delete the old file mid-transaction and leaves exactly one live cover afterwards.

## Risks / edge cases

- **`draftFor()` also copies files inside a transaction.** It copies *onto a brand-new draft*, so a mid-transaction failure that rolls back the draft row leaves only orphaned copied files (clutter, not data loss) — lower severity than #8, but the same `PruneSupersededMedia`/orphan-sweep philosophy could later cover it. Out of scope for this fix; note it.
- **Trash-suffix collision.** Choose a suffix that cannot match any registered collection name (`cover_image_*`, `content_*`); `__superseded` is safe.
- **Concurrent publishes of the same canonical.** Out of scope here (that's finding #7's unique-constraint problem); this change doesn't make it worse — the stash is scoped to the canonical's own media rows.
- **Verify Spatie deletion semantics** for the target version in `vendor/spatie/laravel-medialibrary` before relying on `$media->delete()` cleaning conversions/responsive images (it does in current versions).
