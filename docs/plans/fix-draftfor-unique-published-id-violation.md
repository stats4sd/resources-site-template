# Plan: Hard-delete shadow drafts + catch the `unique(published_id)` violation on `draftFor()`

**Status:** Not Started

Fixes the **in-scope** parts of code-review finding #7 ([docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md)): `TrovePublisher::draftFor()`'s check-then-insert (`$canonical->draft()->first()` then insert) is guarded only by application logic, against the DB-level `$table->unique('published_id')` ([2023_11_24_132900_create_troves_table.php:37](../../database/migrations/2023_11_24_132900_create_troves_table.php#L37)). Two failure paths were flagged:

- **(b) — deterministic, soft-deleted draft.** `Trove` uses `SoftDeletes`, and the unique index is on `published_id` alone (not `deleted_at`). The edit page's `DeleteAction` soft-deletes a shadow draft, but the trashed row still occupies the unique index while `draft()` (default, non-trashed scope) can't see it. The next edit of that canonical calls `draftFor()`, finds no draft, and the insert fails the unique constraint **every time** — the trove becomes permanently un-editable.
- **(a) — the concurrent first-fork race.** Two admins both open the live canonical, both press Save; both `draftFor()` calls find no draft, both insert the same `published_id`; the second throws an untranslated `QueryException` (500).

This plan removes source (b) entirely (shadow drafts stop being soft-deletable) and turns (a) into a clean, explained bounce-back instead of a 500. The *real* fix for concurrent editing — live presence indication that two admins are on the same trove — needs Laravel Reverb/Echo and is **out of scope**; it is captured as a GitHub issue (see below).

## Design decisions

### 1. Shadow drafts are never soft-deleted; only canonicals are (fixes (b))

Per Dave's decision: soft-delete is a canonical-only concept. A shadow draft is disposable pending-edit scratch — if it's deleted it should be **gone forever** (force-deleted), never left trashed to squat on `unique(published_id)`.

`TrovePublisher` already encodes exactly this in `deleteDraftRow()` ([TrovePublisher.php:223](../../app/Services/TrovePublisher.php#L223)) — detach pivots, then `forceDelete()`. The bug is only that the Filament `DeleteAction` bypasses it and calls the model's default (soft) `delete()`.

**"Delete" on the edit/view page deletes the whole logical trove**, routed through a new `TrovePublisher::delete()`:

- If the loaded record is a **shadow draft** (`published_id !== null`): force-delete the draft (gone forever) **and** soft-delete its canonical (recoverable).
- If the loaded record is a **canonical**: force-delete its draft if one exists (else the FK's `cascadeOnDelete` never fires on a soft-delete UPDATE and the draft is orphaned + keeps squatting the index), then soft-delete the canonical.

This is coherent with the existing header actions: **Discard draft changes** already covers "drop the draft, keep the live version", so **Delete** correctly means "remove the whole resource", not "drop just the draft".

**Rejected alternative — a model `deleting` event that promotes any shadow-draft soft-delete to a force-delete.** It would cover every delete path (tinker, future bulk actions) with one hook, but the mechanism (call `forceDelete()` from inside `deleting`, then `return false` to cancel the outer soft-delete) relies on re-entrancy + `isForceDeleting()` and is fragile/surprising. And on its own it gives "Delete a PendingChanges trove" the confusing semantics of silently doing the same thing as "Discard draft changes" (draft vanishes, canonical stays). Centralising in `TrovePublisher::delete()` and routing the two `DeleteAction`s through it is clearer and matches the "delete the resource" mental model. (If non-UI delete paths ever appear, revisit the guard as belt-and-braces.)

### 2. Catch the `unique(published_id)` violation and bounce the user back (turns (a) from a 500 into an explanation)

With (b) eliminated, the only remaining way `draftFor()`'s insert can fail is the genuine concurrent-first-fork race (a). Per Dave's decision, do **not** silently recover (re-fetch the winner's draft and let the loser edit it) — that just lets the second admin clobber the first, which is the concurrent-edit problem the out-of-scope Reverb work addresses. Instead **catch the violation and send the user back with an error notification explaining what happened and why**, discarding their unsaved edit.

The catch lives in `EditTrove` (UI concern), wrapping the `draftFor()` call. The message tells them another admin just started editing and to reload before retrying.

## Changes

### `app/Services/TrovePublisher.php`

Add a single public entry point for deleting a working row, reusing the existing `deleteDraftRow()`:

```php
/**
 * Delete a logical Trove from any working row. The shadow draft (if any) is
 * hard-deleted — it must never linger soft-deleted, where it keeps occupying
 * unique(published_id) and blocks the next draftFor(). The canonical is
 * soft-deleted (recoverable).
 */
public function delete(Trove $working): void
{
    DB::transaction(function () use ($working) {
        $canonical = $working->published_id !== null
            ? $working->publishedVersion()->firstOrFail()
            : $working;

        if ($draft = $canonical->draft()->first()) {
            $this->deleteDraftRow($draft);   // detach pivots + forceDelete()
        }

        $canonical->delete();                // soft-delete (SoftDeletes)
    });
}
```

Note: when `$working` **is** the draft, `$canonical->draft()->first()` returns that same row, so `deleteDraftRow()` force-deletes it — no double-handling.

### `app/Filament/Resources/TroveResource/Pages/EditTrove.php`

**Delete action** — route through `TrovePublisher::delete()` instead of the default soft-delete, and redirect to the index:

```php
Actions\DeleteAction::make()
    ->using(fn (Trove $record) => app(TrovePublisher::class)->delete($record))
    ->successRedirectUrl($this->getResource()::getUrl('index')),
```

(`->using()` overrides only the delete mechanism; the confirmation modal + success notification are kept. If `->using()` proves not to be honoured in this Filament version, fall back to an explicit `->action(function (Trove $record) { app(TrovePublisher::class)->delete($record); Notification::make()->title('Trove deleted')->success()->send(); return redirect($this->getResource()::getUrl('index')); })`.)

**`draftFor()` race** — wrap the fork so a `unique(published_id)` violation becomes a clean bounce-back. In the current code the fork is in `handleRecordUpdate()`; wrap the `draftFor()` call there:

```php
use Illuminate\Database\QueryException;
use Filament\Support\Exceptions\Halt;

protected function handleRecordUpdate(Model $record, array $data): Model
{
    if ($this->shouldForkOnSave($record)) {
        try {
            $record = app(TrovePublisher::class)->draftFor($record);
        } catch (QueryException $e) {
            if ($this->isDuplicateDraftViolation($e)) {
                Notification::make()
                    ->title('Could not save — another editor got there first')
                    ->body('Someone else has just started editing this trove. To avoid overwriting their changes your edit was not saved. Reload the page and try again.')
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt();   // abort the save, stay on the page, edits preserved in the form
            }
            throw $e;               // any other DB error is a genuine fault
        }
        $this->record = $record;
    }

    $record->update($data);

    return $record;
}

/** MySQL duplicate-entry (SQLSTATE 23000 / errno 1062) on the published_id unique index. */
protected function isDuplicateDraftViolation(QueryException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}
```

**Interaction with the sibling plan** [fix-live-trove-edit-forks-on-save.md](fix-live-trove-edit-forks-on-save.md): that plan moves the fork out of `handleRecordUpdate()` into an overridden `save()` (`forkToDraftAndRebind()`). Whichever lands second must apply this `try/catch` around **that** plan's `draftFor()` call site instead — the catch belongs wherever `draftFor()` is invoked. The two changes don't conflict; they just share a call site.

### `app/Filament/Resources/TroveResource/Pages/ViewTrove.php`

Route its `DeleteAction` ([ViewTrove.php:18](../../app/Filament/Resources/TroveResource/Pages/ViewTrove.php#L18)) through `TrovePublisher::delete()` the same way, so no delete surface can soft-delete a shadow draft:

```php
Actions\DeleteAction::make()
    ->using(fn (Trove $record) => app(TrovePublisher::class)->delete($record)),
```

### GitHub issue (out of scope here)

The real concurrent-edit fix — live presence indication (Laravel Reverb + Echo) warning admins when someone else is already editing the same trove, so the race in (a) is prevented rather than merely reported — is tracked in [stats4sd/resources-site-template#6](https://github.com/stats4sd/resources-site-template/issues/6).

## Out of scope

- **Concurrent-edit *prevention* / presence UI** — needs Reverb/Echo, tracked in [#6](https://github.com/stats4sd/resources-site-template/issues/6). This plan only makes the collision safe (no 500, no data corruption, clear message).
- **Other findings (#1, #5, #6, etc.)** — separate plans.

## Verification (end-to-end, manual — no automated suite yet)

Requires MySQL + a media disk running.

1. **(b) is fixed — the core bug:** publish a trove; edit it (creating a shadow draft); open the draft's edit page and press **Delete** → confirm the canonical is soft-deleted (`deleted_at` set, still in `withTrashed()`) and the draft row is **gone** (`withTrashed()->withDrafts()` shows no row with that `published_id`). Then, separately: publish a fresh trove, edit → fork a draft, **Discard draft changes**, edit again → a new draft forks with **no** `QueryException` (the old draft left no trashed row squatting the index).
2. **Delete of a plain canonical still soft-deletes:** publish a trove with no pending draft, **Delete** → `deleted_at` set, recoverable via `withTrashed()`; no orphan rows.
3. **Delete of a canonical that has a draft** (reachable via `ViewTrove` / a canonical route): draft hard-deleted, canonical soft-deleted, no orphan draft left behind.
4. **(a) is a clean bounce, not a 500:** simulate the race — in `tinker`, insert a draft for a live canonical (`app(App\Services\TrovePublisher::class)->draftFor($canonical)` won't help since it's idempotent; instead manually `Trove::withDrafts()` create a row with that `published_id`), then in the UI open the *canonical's* stale edit page and press **Save draft**. Confirm: the danger notification appears, the page stays put with the form intact, no 500, and no second draft row was created. (Easier repro if [fix-live-trove-edit-forks-on-save.md](fix-live-trove-edit-forks-on-save.md) is not yet applied, since it narrows canonical route resolution.)
5. **`ViewTrove` delete** ([ViewTrove.php:18](../../app/Filament/Resources/TroveResource/Pages/ViewTrove.php#L18)) — apply the same `->using()` routing there too so its delete path can't soft-delete a draft; verify as in step 1.
6. `php artisan test` green.

On completion, write the change log to [docs/change-logs/](../change-logs/) per CLAUDE.md and link it from the Status line here.
