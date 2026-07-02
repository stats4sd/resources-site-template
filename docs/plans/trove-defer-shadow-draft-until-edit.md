# Plan: Defer shadow-draft creation until a live Trove is actually edited

**Status:** Not Started

## Problem

Opening the edit page for a `ReviewStatus::Published` trove currently forks its shadow draft immediately. [`EditTrove::mount()`](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L29-L39) calls `TrovePublisher::draftFor()` and redirects to editing the draft row. As a side effect, the mere act of clicking "Edit" flips the trove to `PublishedWithPendingChanges` and leaves an orphan draft behind if the user backs out without changing anything.

Desired behaviour: a user can click "Edit" on a published trove, back out without making changes, and see the trove's status unchanged (no draft created).

## Decision: fork-on-save, not delete-on-leave

Two approaches were considered:

- **Delete-on-leave** — create the draft eagerly (as now), then delete it when the user leaves unless changes were made. Rejected: there is no reliable server-side "left the page" signal in a Livewire/Filament SPA (back button, tab close, cross-page navigation), so it needs an orphan-cleanup sweep anyway; there is still a live window where the status reports `PublishedWithPendingChanges`; and "unless changes were made" requires a content+relations+media diff that fork-on-save avoids entirely.
- **Fork-on-save** (chosen) — do not create the draft until there is something to persist. Lazy creation is the correct model, `mount()` gets simpler, and the "opening a page mutates state" surprise disappears.

## Why the fork currently lives in `mount()`

The [`draftFor()` docblock](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L24-L28) says "every field, relation and media edit targets the draft row." For plain fields this is overstated — Filament edits `$this->data` in memory and only writes to the DB on save, so field edits never touch the DB early. The real reason for the early redirect is that **`SpatieMediaLibraryFileUpload` and the relation fields (tags, troveTypes, collections) persist against `$this->record` during the save pipeline** (`saveRelationships()`), and `$this->record` is the canonical. That write is what would disturb the live copy.

So the fix is not merely "move the `draftFor()` call" — it is "ensure media/relation persistence targets a draft that is created at save time."

## Implementation

### 1. Simplify `mount()`

Remove the `Published`-branch fork + redirect from [`EditTrove::mount()`](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L29-L39). The page mounts directly on whatever record the list linked to:

- A `Published` canonical (no draft yet) → edit the canonical in place until a save forks it.
- An existing shadow draft (`PublishedWithPendingChanges`) → edit the draft directly. The ListTroves edit link already resolves to the draft row for these via [`scopeWorkingVersions()`](../../app/Models/Trove.php#L209-L215), so no fork is needed (confirmed).

### 2. Fork at save time in `handleRecordUpdate()`

In [`handleRecordUpdate()`](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L99-L104), when the record is a `Published` canonical, create the draft and **re-point `$this->record` to the draft before Filament writes relations/media**, so `saveRelationships()` reconciles the form's tag/media state onto the draft and the canonical is never touched:

```php
protected function handleRecordUpdate(Model $record, array $data): Model
{
    // A live canonical only forks a shadow draft on the first save that actually
    // persists edits — opening the edit page no longer mutates state. Re-point
    // $this->record so Filament's relation/media persistence lands on the draft.
    if ($this->shouldForkOnSave($record)) {
        $record = app(TrovePublisher::class)->draftFor($record);
        $this->record = $record;
    }

    $record->update($data);

    return $record;
}
```

### 3. Gate the fork on the pressed action (nailed-down point 1)

The footer actions route through the same save pipeline via [`triggerTroveSave()`](../../app/Filament/Resources/TroveResource/Concerns/HasTroveFormActions.php#L41-L44), so the fork decision must account for which one was pressed on a `Published` canonical:

- **Save draft** — fork. The whole point is capturing pending edits without disturbing the live copy.
- **Request review** — fork. The pending review must live on the shadow draft, never on the live canonical. `finalizeTroveSave()` calls `requestReview($this->record, ...)` in `afterSave()`, which runs after `handleRecordUpdate()`, so re-pointing `$this->record` to the draft there means the review request correctly lands on the draft.
- **Publish** — do **not** fork. If the record is already published, creating a shadow draft only to immediately fold it back into the canonical (`publish()` merges draft → canonical then deletes the draft) is pointless churn. Publish the canonical in place.

Concretely, `shouldForkOnSave()` returns true when the record resolves to `ReviewStatus::Published` **and** `$this->shouldPublish` is false. (`$this->shouldPublish` is set by the pressed action before `triggerTroveSave()` runs.) Both Save draft and Request review leave `shouldPublish` false, so they fork; Publish sets it true, so it does not.

Note the interaction with `publish()`'s in-place branch: publishing an already-live canonical falls through [`publish()`'s `published_id === null` path](../../app/Services/TrovePublisher.php#L82-L93), which re-stamps `published_at`/review state on the canonical — correct for publish-in-place.

## Behavioural outcomes

- Open a `Published` trove, change nothing, back out → no draft row, status stays `Published`.
- Open a `Published` trove, edit a field, Save draft → draft forked on save, status becomes `PublishedWithPendingChanges`.
- Open a `Published` trove, Request review → draft forked, outstanding review recorded on the draft.
- Open a `Published` trove, Publish (no meaningful change) → canonical published in place, no throwaway draft.
- Save fails validation → nothing persisted, no draft created; user stays on the canonical's edit URL.
- Editing an existing `PublishedWithPendingChanges` draft is unaffected (mounts on the draft, no fork).

## To verify after implementing (nailed-down point 2 — owner: Dave)

- **`SpatieMediaLibraryFileUpload` reconciliation after re-pointing `$this->record` mid-save.** `draftFor()` copies the canonical's media onto the draft; the upload component then reconciles the form state (loaded from the canonical's media) against the draft's media. Confirm no duplication and that added/removed files behave, for a trove with existing cover + content media in more than one locale.
- Confirm the canonical's media/relations are untouched after a Save draft (the live copy must not change).

## Out of scope

- `CreateTrove` — a never-published draft has no canonical to protect; its save path is unchanged.
- The delete-on-leave approach and any orphan-cleanup sweep (not needed once creation is lazy).
