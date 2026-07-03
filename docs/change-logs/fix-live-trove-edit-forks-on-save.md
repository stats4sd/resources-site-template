# Change log: Fork a live Trove's shadow draft in `save()`, gated by a form-state dirty-check

Implements [docs/plans/fix-live-trove-edit-forks-on-save.md](../plans/fix-live-trove-edit-forks-on-save.md), fixing code-review finding #1 in [docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md): editing a live Trove and pressing **Save draft** / **Request review** wrote the new tags and media straight onto the public canonical, because the fork happened in `handleRecordUpdate()` — after Filament's `getState()` had already run `saveRelationships()` against the canonical-bound form.

## What changed

### `app/Services/TrovePublisher.php`

- `copyMedia()` now returns a per-collection `canonicalUuid => copyUuid` map (was `void`). The `publish()` call site ignores the return value — no behaviour change there.
- `draftFor()` gained a backward-compatible `array &$mediaUuidMap = []` out-parameter, populated with the per-collection UUID map so a caller that filled a form from the canonical can remap the media components onto the draft's new UUIDs. The fresh-copy path reads the map straight from `copyMedia()`; the idempotent (existing-draft) path builds it via the new `existingDraftMediaMap()`.
- Added `existingDraftMediaMap()`: pairs each collection's canonical media to the existing draft's media by `file_name` (unambiguous names) or positionally, and omits collections whose counts diverged so the caller reloads those from the draft rather than remapping stale UUIDs.

### `app/Filament/Resources/TroveResource/Pages/EditTrove.php`

- **Removed** `handleRecordUpdate()` and `shouldForkOnSave()`. The trivial update now falls through to `EditRecord::handleRecordUpdate()` (which updates `getRecord()`, now the draft).
- Added `save()` override that forks **before** `parent::save()` runs form-state resolution:
  - A plain Save (not publishing, not requesting review) on a live canonical that is **not dirty** short-circuits with a "No changes to save" notification and creates no draft.
  - A live canonical being saved as draft (or via Request review) forks a shadow draft and rebinds the page + form to it, so all relation/media persistence lands on the draft.
  - Publish still folds into the canonical in place (no fork).
- Added a whole-form-state snapshot dirty-check: `afterFill()` snapshots `$this->data` into `$originalFormState`; `troveFormIsDirty()` re-snapshots at save time and compares. `normalizeFormState()` collapses `TemporaryUploadedFile` instances to a marker so any new upload reads as a difference. This auto-covers tags, troveTypes, media, cover images, translations and any field added later — no per-field enumeration to keep in sync.
- Added `forkToDraftAndRebind()` and `remapMediaState()`: after forking, each `SpatieMediaLibraryFileUpload` component's state is rewritten from canonical UUIDs to draft-copy UUIDs (new uploads pass through, user-removed files stay absent so their draft copy is abandoned). Collections the map can't describe (existing-draft divergence) are reloaded from the draft while preserving new uploads.

## Media-UUID trap (why the remap is required)

`draftFor()` copies media to the draft with **new** UUIDs, but the form state still holds the canonical's UUIDs. Without the remap, `deleteAbandonedFiles()` on the draft would see none of the draft's media in the state and delete all of it (silent data loss), while the canonical UUID strings would never be re-added. `remapMediaState()` closes this.

## Verification

- `php -l` clean on both files; `vendor/bin/pint` applied.
- `php artisan test`: the 23 failures are all pre-existing environmental issues (auth scaffolding routes 404, bcrypt hash config) unrelated to this change; nothing in the Trove/Publisher path is exercised by the current suite.
- Manual end-to-end verification (steps 1–7 in the plan) requires MySQL + Meilisearch + a media disk and is still outstanding.

## Out of scope (unchanged)

Findings #7 (unique(published_id) race), #6 (canonical-with-existing-draft still route-resolvable), and #3 (Unpublish a clean live trove) are addressed by their own fixes.
