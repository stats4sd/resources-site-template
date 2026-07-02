# Code review — trove-review-update (drafts → app-owned publication + review axes)

**Date**: 2026-07-02
**Branch**: `trove-review-update`
**Reviewer**: Claude (automated multi-angle review, high effort)
**Scope**: 38 files, 2,640 insertions / 549 deletions (18 code files, 893/379). Removes `oddvalue/laravel-drafts` + `guava/filament-drafts` and reimplements a "published canonical + single shadow draft" model with orthogonal `PublicationState` / `ReviewState` axes, a `TrovePublisher` service, a global `PublishedScope`, and rebuilt Filament create/edit/list flows.

Findings ranked most-severe first.

---

## 1. Editing a live trove and "Save draft" / "Request review" mutates the PUBLIC copy's tags and media immediately (breaks R2)

**`app/Filament/Resources/TroveResource/Pages/EditTrove.php:88`** (`handleRecordUpdate` / `shouldForkOnSave`)

The fork happens too late. Filament's `EditRecord::save()` (vendor `EditRecord.php:148`) calls `$this->form->getState(afterValidate: …)`, and inside `getState()` (vendor `HasState.php:258`) `saveRelationships()` runs — persisting tags/troveTypes/collections **and** the Spatie media components onto the form's bound model — *before* `handleRecordUpdate()` is ever called. The form is bound to `$this->getRecord()`, which for a live `Published` trove is the canonical, and is only re-pointed to the draft *inside* `handleRecordUpdate` (after the relations/media have already been written).

**Failure scenario**: Edit a published trove (no existing draft), change a tag and/or upload/remove a file, click **Save draft**. The new tags and media are synced straight onto the live public canonical; only scalar columns (title/description) get isolated onto the newly-forked draft via `$record->update($data)`. The public page immediately shows the new tags/files under the *old* title — unpublished edits leak to the public site, which is exactly what the shadow-draft model is meant to prevent. Same path applies to **Request review**. The comment "Re-point `$this->record` so Filament's relation/media persistence lands on the draft" is incorrect: persistence already happened, and it targets the form model, not `$this->record`.

---

## 2. "Unpublish" leaves an orphaned shadow draft and its modal lies about discarding changes

**`app/Filament/Resources/TroveResource/Pages/EditTrove.php:73`** (`unpublish` action)

When editing a `PendingChanges` trove the loaded record is the draft, and the action runs `unpublish($record->publishedVersion)`, which only sets the canonical's `published_at = null`. `discardDraft()` / `deleteDraftRow()` is never called, so the draft row survives.

**Failure scenario**: Edit a live trove (forking a draft), then click Unpublish. Modal promises "removes the trove from the public site (and discards any draft changes)". Afterwards the canonical resolves to `Draft` (published_at null, published_id null) while the orphaned draft still resolves to `PendingChanges` (published_id set) pointing at a now-unpublished parent — "pending changes" over nothing published. `workingVersions()` shows the orphan and hides the canonical. The promised discard never happens.

---

## 3. A cleanly published trove can never be unpublished from the UI
**FIXED**

**`app/Filament/Resources/TroveResource/Pages/EditTrove.php:70`** (`unpublish` visibility)

Both `unpublish` and `discard_draft` are gated on `publication_state === PublicationState::PendingChanges`. A live trove with no pending draft resolves to `Published`, so neither action renders — and the table has no bulk unpublish (commented out).

**Failure scenario**: Publish a trove, make no further edits, open its edit page → only **Delete** is available. There is no path to take an unedited live trove off the public site, despite `TrovePublisher::unpublish()` supporting it and review item 1.6 ("Unpublish implemented") requiring it.

---

## 4. Publishing edits to an already-reviewed live trove keeps a stale "Reviewed by X" stamp

**`app/Services/TrovePublisher.php:191`** (`publish` in-place branch + `applyReviewStateOnPublish`), **`app/Filament/Resources/TroveResource/Concerns/HasTroveFormActions.php:135`** (`reviewedAlready`)

`shouldForkOnSave()` returns false when `shouldPublish` is true, so editing a live trove and pressing **Publish changes** publishes the canonical in place. The canonical still carries `reviewed_at`/`reviewer_id` from an earlier publish, so `applyReviewStateOnPublish($canonical, $canonical)` preserves that approval stamp for brand-new, unreviewed content. Compounding it, `reviewedAlready()` reads the canonical's old `reviewed_at`, so the publish confirmation shows "This trove has been reviewed. Publish it?" (plain confirm) instead of the "Publish without a review" checkbox.

**Failure scenario**: A reviewed, published trove is edited and republished in place; it now displays "Reviewed by X" for content X never saw, and the reviewer gate is silently skipped.

---

## 5. The new global `PublishedScope` hides unpublished troves from admin surfaces that never opt out

**`app/Livewire/AllTrovesTable.php:60`** (`->query(fn () => Trove::query())`); also **`app/Filament/Resources/CollectionResource/RelationManagers/TrovesRelationManager.php:30`** (`$relationship = 'troves'`)

Only `TroveResource::getEloquentQuery()` opts out via `withoutGlobalScope(PublishedScope::class)`. The "Show All Troves" collection picker (`AllTrovesTable`, reachable from `ViewCollection` → `view-collection.blade.php`) and the collection's Troves relation manager both run unscoped `Trove` queries.

**Failure scenario**: A never-published draft trove can't be found in the picker, so it can't be added to a collection. An attached trove that is later unpublished vanishes from the "Troves in this Collection" table, so an admin can no longer see it or use the DetachAction — even though the pivot row still exists.

---

## 6. `getEloquentQuery()` doesn't narrow to working versions, so a canonical with a draft can be edited and clobber the draft
**Fixed**

**`app/Filament/Resources/TroveResource.php:50`**

The docblock says "List tabs and edit-record resolution then narrow this to working versions," but `getEloquentQuery()` only removes `PublishedScope`; record-route resolution can therefore load a canonical that already has a shadow draft (e.g. a global-search hit — only canonicals are searchable — or a bookmarked `/troves/{canonicalId}/edit`).

**Failure scenario**: Open the canonical of a trove that has pending draft edits and save. `shouldForkOnSave` forks, but `draftFor()` is idempotent and returns the *existing* draft; `$record->update($data)` then writes the canonical-form data over the draft, silently clobbering its independent pending edits.

---

## 7. `draftFor()` check-then-insert can violate `unique(published_id)` — including permanently after a soft-deleted draft

**`app/Services/TrovePublisher.php:42`** (`draftFor`)

`$canonical->draft()->first()` then insert, guarded only by application logic, against `$table->unique('published_id')`.

**Failure scenario (a)**: Two admins first-edit the same live trove concurrently; both find no draft, both insert with the same `published_id` → the second throws an untranslated `QueryException` (500). **(b)** More deterministically: the edit page's `DeleteAction` soft-deletes a draft (`Trove` uses `SoftDeletes`), but the trashed row still occupies the unique index while `draft()` (default scope) can't see it — so the next edit of that canonical calls `draftFor()`, finds no draft, and the insert fails the unique constraint every time.

---

## 8. `copyMedia(replace: true)` deletes canonical media on disk inside a DB transaction that can't restore it

**`app/Services/TrovePublisher.php:209`** (`copyMedia`, called from `publish`)

Inside `publish()`'s `DB::transaction`, `clearMediaCollection()` physically deletes the canonical's files (S3/local) before copying the draft's in.

**Failure scenario**: A disk/S3 error (or a bad draft media row) after the clear but during the copy rolls back the DB rows, but the canonical's original files are already gone — the published trove is left with missing/partial media and no DB record of the intended state.

---

## 9. Dead code: `reviewInProgress` accessor is defined but never used
**FIXED**

**`app/Models/Trove.php:200`**

`reviewInProgress()` has zero call sites (all callers use `$record->review_state === ReviewState::InReview` directly). It is a third derivation of "in review" that must be kept in sync with `reviewState()` and `scopeWithReviewState()` for no benefit — a maintenance trap.

---

## 10. N+1 on the `review_state` column, plus a `needs_my_review` count on every list render

**`app/Filament/Resources/TroveResource.php:374`** and **`app/Filament/Resources/TroveResource/Pages/ListTroves.php:34`**

The `review_state` column's description reads `$record->reviewer?->name` with no `with('reviewer')` eager-load, firing one `users` query per reviewed row. Separately, `getTabs()` runs `Trove::query()->awaitingReviewBy(auth()->id())->count()` (which itself does a `whereDoesntHave` subquery) on every Livewire render regardless of the active tab, duplicating that scan when the tab is active.

---

### Not flagged (checked, currently non-manifesting)

- `download_count` and `previous_slugs` are content-merged from the draft's stale snapshot on publish (absent from `TrovePublisher::NON_CONTENT`). No code increments `download_count`, and `previous_slugs` only diverges after an out-of-band slug change, so neither manifests today — but both are latent data-loss traps if those paths ever appear.
- `scopeWithPublicationState`/`scopeWithReviewState` degrade to match-**all** (not match-none) on an empty variadic; no current caller passes an empty set.
