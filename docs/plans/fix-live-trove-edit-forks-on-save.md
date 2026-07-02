# Plan: Fork a live Trove's shadow draft in `save()`, gated by a form-state dirty-check

**Status:** Not Started

Fixes code-review finding #1 ([docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md)): editing a live Trove and pressing **Save draft** / **Request review** currently writes the new tags and media straight onto the public canonical, because the fork happens in `handleRecordUpdate()` — *after* Filament's `getState()` has already run `saveRelationships()` against the canonical-bound form. The fix moves the fork earlier (into an overridden `save()`, before `getState()`), rebinds the form to the draft, and only forks when the form actually changed.

## Decision (confirmed by prior discussion)

Rejected two alternatives:

- **Fork in `mount()`/`resolveRecord()`** (the original plan) — mutates trove state (creates a "pending changes" row) merely by opening the edit page. Unintuitive.
- **View page + "Create editable draft" button** — same mutation problem relocated behind a button, plus orphan-draft churn, extra clicks, three-way route resolution, and read-only-component fidelity work. See the discussion in the review thread.

Chosen: **fork on first *dirty* save**, in an overridden `EditTrove::save()`, before `parent::save()` runs form-state resolution. No mutation on open, single Edit page, one route, no read-only components. The cost is the fiddly rebind-the-form-model-mid-save code — which is exactly where finding #1 already lives, so it is the correct home for the fix.

## Why the fork must happen in `save()`, not `handleRecordUpdate()`

`Filament\Resources\Pages\EditRecord::save()` (vendor, line 148) does:

```php
$data = $this->form->getState(afterValidate: …);   // <-- saveRelationships() runs HERE, on the form's bound model
…
$this->handleRecordUpdate($this->getRecord(), $data);   // <-- current fork happens here, too late
```

`getState()` internally calls `ComponentContainer::saveRelationships()` (vendor `Concerns/BelongsToModel.php:18`), which persists the relationship Selects (`tags`, `troveTypes`) **and** the `SpatieMediaLibraryFileUpload` components onto **the form container's model**. The form container's model is set once, at form-build time, to `$this->getRecord()` (vendor `EditRecord::form()`, line 385 `->model($this->getRecord())`). Re-pointing `$this->record` inside `handleRecordUpdate()` (the current [EditTrove.php:90](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L90)) is too late and targets the wrong object — the relations/media are already written to the canonical.

So the fork + rebind must complete **before** `parent::save()` is invoked.

## The media-UUID trap (must not be skipped)

`SpatieMediaLibraryFileUpload` state is `[uuid => uuid]` for each existing file, loaded from whichever record the form was filled from — here, the **canonical** (vendor `SpatieMediaLibraryFileUpload::setUp()`, `loadStateFromRelationshipsUsing`, ~line 54). On save:

- `deleteAbandonedFiles()` deletes every media row on the **current record** whose UUID is *not* in the state array.
- `saveUploadedFiles()` only persists `TemporaryUploadedFile` entries (genuinely new uploads).

`TrovePublisher::draftFor()` copies the canonical's media to the draft with **new** UUIDs. If we rebind the form to the draft but leave the state holding the canonical's UUIDs, then `deleteAbandonedFiles()` on the draft sees none of the draft's (new-UUID) media in the state and **deletes all of it**, while the old canonical UUIDs are strings (not `TemporaryUploadedFile`) so they are never re-added. Result: the draft loses every unchanged file. This is silent data loss and is the reason a naive "rebind to draft" is not enough.

**Fix:** after forking, remap each media component's state, replacing every canonical UUID with its draft-copy UUID. New uploads (`TemporaryUploadedFile`) pass through untouched; files the user removed (canonical UUID absent from state) have their draft copy correctly abandoned and deleted.

This requires `draftFor()` to hand back the `canonicalUuid => draftUuid` map it produces while copying.

## The dirty-check: whole-form-state snapshot diff (no field enumeration)

Because `TranslatableComboField` stores the **full** locale array in one place in form state ([TranslatableComboField.php:52](../../app/Filament/Translatable/Form/TranslatableComboField.php#L52), `formatStateUsing` → `getTranslations()`) and the edit page does **not** use the Spatie translatable page concern's active-locale buffering, the entire form's meaningful state — scalars, translatable arrays, relation ID sets, and media UUID maps — lives in `$this->data` after `fillForm()`. `EditRecord::fillFormWithDataAndCallHooks()` fills then fires `afterFill` (vendor lines 103-116), and `ComponentContainer::fill()` loads relation/media state into `$this->data` before that hook.

So the dirty-check is a **snapshot diff**, not an enumerated per-field comparison:

1. In `afterFill()`, snapshot `$this->data` (normalized — see below) into a page property `$originalFormState`.
2. At save time, snapshot `$this->data` again and compare.

This auto-covers **tags, troveTypes, media, cover images, title/description translations, external/youtube links, and any field added later** — no list to keep in sync (avoids the enumeration-drift class of bug, where a new field silently defeats the check and re-leaks to the canonical).

Normalization (to make the comparison robust and cheap):

```php
protected function troveFormStateSnapshot(): array
{
    // Replace TemporaryUploadedFile instances with a marker so object identity
    // doesn't matter: at fill time there are none, so any new upload => difference.
    return $this->normalizeState($this->data);   // recursively map TemporaryUploadedFile => '__new_upload__'
}
```

Dirty ⇔ `$this->troveFormStateSnapshot() !== $this->originalFormState`.

**Safety asymmetry (why this bias is correct):** a false "clean" verdict is a real bug — the edit is either lost or leaks to the canonical (the very thing #1 fixes). A false "dirty" verdict only creates a draft identical to the canonical (a discardable annoyance). The snapshot diff biases toward "dirty" (any state difference at all forks), which is the safe direction. During verification, confirm an untouched Save on a live trove reports *clean* (guards against volatile-at-hydrate fields producing spurious forks).

## Changes

### `app/Services/TrovePublisher.php`

- Change `copyMedia()` to build and return the per-collection UUID map:
  ```php
  private function copyMedia(Trove $from, Trove $to, bool $replace = false): array
  {
      $map = [];   // ['content_en' => ['<oldUuid>' => '<newUuid>', …], …]
      $from->getRegisteredMediaCollections()->each(function ($collection) use ($from, $to, $replace, &$map) {
          if ($replace) {
              $to->clearMediaCollection($collection->name);
          }
          $from->getMedia($collection->name)->each(function ($media) use ($to, &$map, $collection) {
              $copy = $media->copy($to, $media->collection_name, $media->disk);
              $map[$collection->name][$media->uuid] = $copy->uuid;
          });
      });
      return $map;
  }
  ```
  The `publish()` call site ([TrovePublisher.php:127](../../app/Services/TrovePublisher.php#L127)) just ignores the return value — no behaviour change there.
- Give `draftFor()` an out-parameter for the map (backward-compatible; the only existing caller can ignore it):
  ```php
  public function draftFor(Trove $canonical, array &$mediaUuidMap = []): Trove
  {
      if ($existing = $canonical->draft()->first()) {
          // Existing draft (idempotent path): the form was still filled from the
          // canonical, so we need canonicalUuid => existingDraftUuid to remap. Pair
          // by collection using a stable key (see note) rather than the copy return.
          $mediaUuidMap = $this->existingDraftMediaMap($canonical, $existing);
          return $existing;
      }
      return DB::transaction(function () use ($canonical, &$mediaUuidMap) {
          $draft = $canonical->replicate([...]);   // unchanged
          $draft->published_id = $canonical->id;
          $draft->save();
          $this->copyRelations($canonical, $draft);
          $mediaUuidMap = $this->copyMedia($canonical, $draft);
          return $draft;
      });
  }
  ```
  Note the idempotent branch: when a draft already exists (e.g. two edits of the same live trove, or a retry), there is no fresh copy to read UUIDs from, so build the map by pairing `$canonical->getMedia($coll)` to `$existing->getMedia($coll)`. Pair by `file_name` within the collection, falling back to `order_column`; if a collection's counts don't line up (draft media already diverged from canonical), **do not remap that collection** — instead re-fill that media component's state from the existing draft, because the canonical UUIDs no longer describe the draft. (This idempotent-existing-draft case only arises with concurrent editors / retries — see "Out of scope" re: finding #7.)

### `app/Filament/Resources/TroveResource/Pages/EditTrove.php`

- **Delete** `handleRecordUpdate()` (lines 83-96) and `shouldForkOnSave()` (lines 104-108). The fork moves to `save()`; the trivial update falls through to `EditRecord::handleRecordUpdate()` (`$record->update($data)` on `getRecord()`, which is now the draft).
- Add the snapshot property + hook:
  ```php
  protected array $originalFormState = [];

  protected function afterFill(): void
  {
      $this->originalFormState = $this->troveFormStateSnapshot();
  }
  ```
- Override `save()`:
  ```php
  public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
  {
      // Only a *live* canonical needs forking, and only when NOT publishing.
      // Publish folds straight into the canonical; draft / never-published edits
      // already target the correct row.
      $isLiveCanonical = $this->getRecord()->publication_state === PublicationState::Published;
      $isPlainSave     = ! $this->shouldPublish && $this->reviewerIdToRequest === null;

      if ($isLiveCanonical && $isPlainSave && ! $this->troveFormIsDirty()) {
          Notification::make()->title(__('No changes to save'))->info()->send();
          return;   // no fork, no write, stay on page
      }

      if ($isLiveCanonical && ! $this->shouldPublish) {
          $this->forkToDraftAndRebind();
      }

      parent::save($shouldRedirect, $shouldSendSavedNotification);
  }
  ```
  - `troveFormIsDirty()`: `return $this->troveFormStateSnapshot() !== $this->originalFormState;`
  - `forkToDraftAndRebind()`:
    ```php
    protected function forkToDraftAndRebind(): void
    {
        $canonical = $this->getRecord();
        $map = [];
        $draft = app(TrovePublisher::class)->draftFor($canonical, $map);

        $this->record = $draft;          // handleRecordUpdate(getRecord(), …) now targets the draft
        $this->form->model($draft);      // container model; relation Selects + media inherit via getParentComponent()->getRecord()
        $this->remapMediaState($map);    // rewrite canonical UUIDs -> draft UUIDs in the form state
    }
    ```
  - `remapMediaState(array $map)`: iterate `$this->form->getFlatFields(withHidden: true)`, keep `SpatieMediaLibraryFileUpload` instances, and for each look up `$map[$component->getCollection()]`, then rewrite the component state:
    ```php
    $new = [];
    foreach ($component->getState() ?? [] as $k => $v) {
        if ($v instanceof TemporaryUploadedFile) { $new[$k] = $v; continue; }  // new upload untouched
        if ($draftUuid = ($collMap[$v] ?? null)) { $new[$draftUuid] = $draftUuid; }  // kept file remapped
        // canonical UUID absent from the map cannot happen (draftFor copied all); if it did, drop it
    }
    $component->state($new);
    ```
- **Decision — Request review on a clean live trove:** the "No changes" short-circuit deliberately applies to **Save draft only** (`$isPlainSave`). **Request review** and **Publish** proceed even with no content change: Request review still forks (the review handshake belongs on a working row per the model), and Publish still publishes in place. Flagged for confirmation; the alternative (block Request review with no changes) is a one-line change to the guard.

### No change needed

- `HasTroveFormActions::finalizeTroveSave()` ([HasTroveFormActions.php:123](../../app/Filament/Resources/TroveResource/Concerns/HasTroveFormActions.php#L123)) runs in `afterSave()`, by which point `$this->record` is the draft — the per-locale file-name writes and `publish()`/`requestReview()` calls already target the right row.
- `publishLabel()` / `reviewedAlready()` read `$this->record`; in the fork path they read the draft (review fields stripped by `draftFor()`), which is correct.

## Out of scope (related findings, not addressed here)

- **#7 (unique(published_id) race / soft-deleted-draft):** `draftFor()`'s check-then-insert is unchanged. This plan does not worsen it (the idempotent branch is handled), but the concurrency hardening is finding #7's own fix.
- **#6 (a canonical *with* an existing draft is still route-resolvable and editable):** the idempotent `draftFor()` branch above makes a save on such a route land on the existing draft rather than clobbering it, but the proper narrowing of `getEloquentQuery()` to working versions is finding #6's fix.
- **#3 (Unpublish a clean live trove):** unchanged; that action's visibility fix is separate.

## Verification (end-to-end, manual — no automated suite yet)

Requires MySQL + Meilisearch + a media disk running.

1. **The bug is fixed:** publish a trove, then edit it — change a tag **and** upload/remove a file — press **Save draft**. Confirm: public page unchanged (old tags, old files); a draft row now exists with the new tags/files; `draftFor` copied media survives on the draft (open the draft's edit page, all expected files present).
2. **Media remap correctness:** on the draft edit, verify unchanged files still render (correct draft UUIDs), a removed file is gone, and a newly uploaded file is present. Check S3/local: canonical's original files untouched.
3. **Dirty-check — clean save:** open a live trove's edit page, change nothing, press **Save draft** → "No changes to save", **no draft row created** (check DB).
4. **Dirty-check — each dimension independently forks:** repeat step 3 but change only (a) title in one locale, (b) a tag, (c) reorder files, (d) the file display name — each must fork a draft.
5. **Publish path:** edit a live trove, press **Publish changes** → publishes in place, no orphan draft, single canonical row (PK stable).
6. **Request review path:** edit a live trove with a real change, **Request review** → review lands on the draft, canonical stays live.
7. **Non-forking paths still work:** edit a never-published draft (edits in place, no new row); edit an existing shadow draft (edits the draft, no second row).
8. `php artisan test` green (add unit tests for `copyMedia()` returning the map and `draftFor()` populating it if a test harness is stood up).

On completion, write the change log to [docs/change-logs/](../change-logs/) per CLAUDE.md and link it from the Status line here.
