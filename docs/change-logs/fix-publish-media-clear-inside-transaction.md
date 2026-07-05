# Fix: `copyMedia(replace: true)` deletes canonical media on disk inside a non-restorable transaction

Implements [docs/plans/fix-publish-media-clear-inside-transaction.md](../plans/fix-publish-media-clear-inside-transaction.md) (Option A: detach-then-defer), addressing finding #8 of [docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md).

## Problem

`TrovePublisher::publish()` folded a shadow draft onto its canonical inside a single `DB::transaction`, and did so by calling `$canonical->clearMediaCollection($name)` before copying the draft's media in. That call physically deletes files from disk (S3/local) synchronously. If anything failed later in the same transaction, the DB rows rolled back but the deleted files did not come back — a published trove could end up with media rows pointing at files that no longer existed.

A second, implicit path had the same effect: `cover_image_{locale}` collections are `singleFile()`, and Spatie's `FileAdder` auto-evicts (and deletes) the existing file when a second one is added to a size-limited collection — so even without the explicit `clearMediaCollection` call, adding the draft's cover image would still delete the canonical's old one mid-transaction.

## Fix

`app/Services/TrovePublisher.php`:

- Added `TrovePublisher::TRASH_SUFFIX` (`'__superseded'`).
- `copyMedia(replace: true)` no longer calls `clearMediaCollection()`. Instead, a new `stashMediaForReplacement()` renames the target's existing media in that collection to `"{$collectionName}__superseded"` via a bulk query-builder update. Spatie's `DefaultPathGenerator` keys storage paths on `media.id`, not `collection_name`, so this is a pure DB rename — no disk I/O, no Spatie model events, and it empties the live collection so `singleFile()` eviction never fires while copying the draft's media in.
- `publish()`'s shadow-draft branch now returns the canonical from the transaction and calls a new `purgeSupersededMedia()` **after** the transaction commits. That method finds all of the canonical's media in a `*__superseded` collection and hard-deletes them via Spatie's normal `$media->delete()` (which also removes conversions/responsive images). Failures are logged, not thrown — the publish has already succeeded by that point.

Net effect: a mid-transaction failure now only rolls back the `collection_name` renames — the canonical's original files are never touched on disk unless and until the transaction has actually committed.

`draftFor()` (`replace: false`) is unchanged — it copies onto a freshly-replicated, media-empty draft, so it was never affected by this bug.

## Fallback sweep

`app/Console/Commands/PruneSupersededMedia.php` — a new `app:prune-superseded-media` command that finds any leftover `*__superseded` media (in case the post-commit cleanup itself failed, e.g. a killed process or transient S3 error) and deletes them. Scheduled daily in `app/Console/Kernel.php`, alongside the existing `app:purge-telescope-entries` job.

## Testing

Not covered by automated tests — per `CLAUDE.md`, the test suite for this project is not yet built out and isn't used as a review/diagnostic tool. Verified by reading through Spatie's `FileAdder`/`Media::copy()`/`MediaObserver` source to confirm:

- the `collection_name` rename touches no disk path (`DefaultPathGenerator` keys on `media.id`);
- the bulk `update()` bypasses Eloquent model events, so no `deleting`/`deleted` observer runs;
- `singleFile()` eviction (`FileAdder::processMediaItem()`) re-fetches the model via `$this->subject->fresh()` before checking the collection size, so it can't act on a stale, pre-stash relation cache.

The plan's proposed feature-test scenarios (happy path, rollback safety, post-commit cleanup failure, single-file collection) remain a good candidate list if/when the test suite is stood up.
