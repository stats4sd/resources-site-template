# Change log: Hard-delete shadow drafts + catch the `unique(published_id)` violation on `draftFor()`

Implements [docs/plans/fix-draftfor-unique-published-id-violation.md](../plans/fix-draftfor-unique-published-id-violation.md), fixing the in-scope parts of code-review finding #7 in [docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md): `TrovePublisher::draftFor()`'s check-then-insert was guarded only by application logic against the DB-level `unique(published_id)` index, with two failure paths — (b) a soft-deleted shadow draft permanently squatting the index, and (a) a genuine concurrent first-fork race surfacing as an untranslated 500.

## What changed

### `app/Services/TrovePublisher.php`

- Added `delete(Trove $working)`: resolves `$working` to its canonical (via `publishedVersion()` if `$working` is a draft), hard-deletes any shadow draft via the existing `deleteDraftRow()`, then soft-deletes the canonical. Single entry point for "delete the whole logical trove" from either a draft or canonical row.

### `app/Filament/Resources/TroveResource/Pages/EditTrove.php`

- **Delete action** now routes through `TrovePublisher::delete()` via `->using()`, with `->successRedirectUrl()` to the index — so deleting from the edit page can never leave a soft-deleted shadow draft squatting the unique index.
- **`draftFor()` race**: wrapped the call inside `forkToDraftAndRebind()` (not `handleRecordUpdate()` — the sibling plan [fix-live-trove-edit-forks-on-save.md](../plans/fix-live-trove-edit-forks-on-save.md) had already moved the fork there) in a `try`/`catch (QueryException)`. A MySQL duplicate-entry error (`errno 1062`, checked via `isDuplicateDraftViolation()`) on the `published_id` unique index sends a persistent danger notification explaining another admin just started editing, then throws `Filament\Support\Exceptions\Halt` to abort the save and keep the user's edits in the form. Any other `QueryException` rethrows.

### `app/Filament/Resources/TroveResource/Pages/ViewTrove.php`

- Its `DeleteAction` routed through `TrovePublisher::delete()` the same way, so no delete surface can soft-delete a shadow draft.

### GitHub issue

The real concurrent-edit *prevention* fix (live presence indication via Reverb/Echo) remains tracked in [stats4sd/resources-site-template#6](https://github.com/stats4sd/resources-site-template/issues/6) — out of scope here, which only makes the collision safe (no 500, no data corruption, clear message).

## Verification

- `php -l` clean on all three files; `vendor/bin/pint` applied (only new code reformatted — `throw new Halt;` per project style).
- `php artisan tinker`, run against the real (MySQL) database inside a transaction that was rolled back at the end (no data persisted):
  - `delete()` called on a shadow draft: canonical soft-deleted (`deleted_at` set), draft row gone (`withTrashed()` finds nothing).
  - `delete()` called on a canonical that has a draft: draft force-deleted, canonical soft-deleted.
  - `delete()` called on a plain canonical with no draft: soft-deleted as normal.
  - Manually inserting two rows with the same `published_id` reproduces `QueryException` with `errorInfo[1] === 1062`, confirming `isDuplicateDraftViolation()`'s check matches the real MySQL error code.
- Not verified: the Filament UI wiring itself (notification rendering, `Halt` aborting the Livewire request cleanly, `DeleteAction::using()` being honoured in this Filament version) — requires a browser + admin session per the plan's manual verification steps, which wasn't exercised in this session.

## Out of scope (unchanged)

Concurrent-edit *prevention* (Reverb/Echo presence UI) and other findings from the code review are addressed separately.
