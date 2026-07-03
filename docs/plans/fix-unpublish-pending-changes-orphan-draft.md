# Plan: Fix `unpublish` so it collapses a pending-changes trove to a single canonical Draft

**Status:** Completed. See [docs/change-logs/fix-unpublish-pending-changes-orphan-draft.md](../change-logs/fix-unpublish-pending-changes-orphan-draft.md).

Fixes code-review finding #2 ([docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md)): unpublishing a `PendingChanges` trove leaves an orphaned shadow draft. It also closes the null-pointer hole that finding #3's fix exposed: the `unpublish` action is now visible for a cleanly `Published` trove, but the action passes `$record->publishedVersion` — which is `null` when `$record` is itself the canonical.

## The bug

`TrovePublisher::unpublish()` ([app/Services/TrovePublisher.php:146](../../app/Services/TrovePublisher.php#L146)) only sets `published_at = null` on the row it's given. When the edited trove is `PendingChanges`, the action ([EditTrove.php:74](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L74)) passes the canonical (`$record->publishedVersion`), nulls its `published_at`, and never touches the draft row. End state: the canonical resolves to `Draft` (published_at null, published_id null) while the orphaned draft still resolves to `PendingChanges` (published_id set) pointing at a now-unpublished parent — "pending changes over nothing published". `workingVersions()` then surfaces the orphan and hides the canonical.

## Target behaviour

Unpublishing must leave **exactly one row**: the canonical, resolving to `Draft`, carrying the most recent (pending) edits. This is a *fold-and-keep*, not a discard — pulling a trove offline to keep working on it should not silently throw away in-progress edits.

## Approach

Two changes — the service **and** the action. The proposal as originally framed ("if `$canonical` is `PendingChanges`") can't be tested inside the service, because the argument is always a canonical (`published_id === null`) and a canonical can never resolve to `PendingChanges`. The real predicate is "does this canonical have a shadow draft?".

### 1. `TrovePublisher::unpublish()`

```php
public function unpublish(Trove $canonical): void
{
    DB::transaction(function () use ($canonical) {
        // Fold any pending shadow-draft edits onto the canonical (and delete the
        // draft row) so unpublish always leaves a single canonical, never an orphan.
        if ($draft = $canonical->draft()->first()) {
            $this->publish($draft);   // copies content/relations/media onto canonical, deletes draft
            $canonical->refresh();    // publish() mutated a *different* instance (draft->publishedVersion)
        }

        $canonical->published_at = null;
        $canonical->save();
    });
}
```

- Keyed on `$canonical->draft()->first()`, not a state enum.
- The `refresh()` is **mandatory**: `publish($draft)` operates on `$draft->publishedVersion()->firstOrFail()`, a different in-memory instance than the `$canonical` we hold. Without the refresh we'd `save()` a stale `published_at` and un-fold the edits.
- Wrapped in an outer `DB::transaction` so the fold + the `published_at = null` are all-or-nothing (nesting inside `publish()`'s own transaction is fine — Laravel uses savepoints).
- The no-draft path (cleanly `Published` trove) is unchanged behaviour: skip the fold, null `published_at`.

### 2. `EditTrove` unpublish action

The action must resolve the canonical from whatever `$record` it holds, because for a cleanly `Published` trove `$record` **is** the canonical and `$record->publishedVersion` is `null`:

```php
->action(function (Trove $record) {
    $canonical = $record->publication_state === PublicationState::PendingChanges
        ? $record->publishedVersion
        : $record;
    app(TrovePublisher::class)->unpublish($canonical);
    Notification::make()->title('Trove unpublished')->success()->send();

    return redirect($this->getResource()::getUrl('index'));
})
```

While here, fix the visibility predicate on [EditTrove.php:70](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L70) — `=== PublicationState::PendingChanges || PublicationState::Published` is a logic error (the right operand is a truthy enum instance, so the action is *always* visible). Intended:

```php
->visible(fn (Trove $record) => in_array($record->publication_state, [
    PublicationState::PendingChanges,
    PublicationState::Published,
], true))
```

## Side effects inherited from reusing `publish()` — accepted, but note them

Folding via `publish()` (rather than reimplementing a content-only copy) drags in three behaviours. All are judged acceptable here, but are called out so the decision is conscious:

- **Media (finding #8):** `publish()` calls `copyMedia(replace: true)`, which physically `clearMediaCollection()`s the canonical's files before copying the draft's in. `unpublish` therefore inherits finding #8's non-atomic disk-delete risk. This is not made worse by this change and will be resolved when #8 is addressed separately.
- **Review request:** `applyReviewStateOnPublish` clears `review_requested_at` / `requester_id`. Unpublishing an `InReview` trove cancels its outstanding review request (and clears the reviewer if `reviewed_at` is null). This is judged correct — a trove pulled offline has no live review pending.
- **Slug redirects:** `previous_slugs` gets appended if the draft changed the slug. Harmless.

## Rejected alternative

**Promote the draft to canonical** (set `draft.published_id = null`, delete the old canonical). Avoids the media clear-and-copy and the review-state clear, but changes the canonical primary key — breaking the stable-PK invariant documented in `TrovePublisher`, plus collection pivots and any bookmarked `/troves/{id}/edit`. Folding onto the existing canonical keeps the PK stable and is the correct model.

## Modal copy

Current copy ([EditTrove.php:72](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L72)) — "This removes the trove from the public site. It is not deleted." — is consistent with fold-and-keep; leave it. Do **not** reintroduce any "discards draft changes" wording (finding #2 quoted such a variant): with this approach the edits are kept, not discarded.

## Tests

- Unpublish a cleanly `Published` trove (no draft) → row is `Draft`, `published_at` null, content unchanged.
- Unpublish a `PendingChanges` trove → single row remains (the canonical, stable PK), resolves to `Draft`, carries the draft's pending edits (title/description/tags/media), draft row is gone, `workingVersions()` returns exactly one row.
- Unpublish a `PendingChanges` trove whose draft is `InReview` → resulting `Draft` has `review_state === None` (request cleared).
- Action-level: unpublishing from the edit page of a `Published` trove does not error (canonical resolved, no null passed to the service).

## Files

- [app/Services/TrovePublisher.php](../../app/Services/TrovePublisher.php) — `unpublish()`
- [app/Filament/Resources/TroveResource/Pages/EditTrove.php](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php) — unpublish action (canonical resolution) + visibility predicate fix
