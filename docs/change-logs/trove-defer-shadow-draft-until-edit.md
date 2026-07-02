# Change log: Defer shadow-draft creation until a live Trove is actually edited

Implements [docs/plans/trove-defer-shadow-draft-until-edit.md](../plans/trove-defer-shadow-draft-until-edit.md).

## Summary

Opening the edit page for a `ReviewStatus::Published` trove no longer forks its shadow draft. The draft is now created lazily, at save time, only when a save would actually persist pending edits without publishing. A user can click "Edit" on a published trove, back out without changing anything, and the trove's status stays `Published` with no orphan draft.

## Changes

`app/Filament/Resources/TroveResource/Pages/EditTrove.php`:

- **Removed the eager fork from `mount()`.** The whole `mount()` override (which called `TrovePublisher::draftFor()` and redirected to the draft's edit URL for `Published` records) is gone. The page now mounts directly on whatever record the list linked to — the canonical for a `Published` trove, or the existing shadow draft for a `PublishedWithPendingChanges` one (the ListTroves edit link already resolves to the draft via `scopeWorkingVersions()`).
- **Fork at save time in `handleRecordUpdate()`.** When `shouldForkOnSave()` is true, `draftFor()` creates the draft and `$this->record` is re-pointed to it before `$record->update($data)`, so Filament's `saveRelationships()` (tags, troveTypes, collections) and `SpatieMediaLibraryFileUpload` persistence land on the draft, never the live canonical. Because `afterSave()` → `finalizeTroveSave()` runs after `handleRecordUpdate()`, a Request review also correctly records against the draft.
- **Added `shouldForkOnSave()`.** Returns true when `! $this->shouldPublish` and the record resolves to `ReviewStatus::Published`. Save draft and Request review leave `shouldPublish` false → they fork; Publish sets it true → it publishes the canonical in place (no throwaway draft folded straight back).

## Behavioural outcomes

- Open a `Published` trove, change nothing, back out → no draft, status stays `Published`.
- Open a `Published` trove, edit, Save draft → draft forked, status becomes `PublishedWithPendingChanges`.
- Open a `Published` trove, Request review → draft forked, review recorded on the draft.
- Open a `Published` trove, Publish → canonical published in place via `publish()`'s `published_id === null` branch.
- Editing an existing `PublishedWithPendingChanges` draft is unchanged (mounts on the draft, no fork).

## Not done here (owner: Dave, per plan)

Manual verification of `SpatieMediaLibraryFileUpload` reconciliation after the mid-save `$this->record` re-point — confirm no media duplication and that the canonical's media/relations are untouched after a Save draft, for a trove with cover + content media across multiple locales.
