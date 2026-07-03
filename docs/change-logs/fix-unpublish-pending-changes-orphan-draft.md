# Change log: Fix `unpublish` so it collapses a pending-changes trove to a single canonical Draft

Implements [docs/plans/fix-unpublish-pending-changes-orphan-draft.md](../plans/fix-unpublish-pending-changes-orphan-draft.md), fixing code-review finding #2 in [docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md): unpublishing a `PendingChanges` trove only nulled `published_at` on the canonical, leaving the shadow draft orphaned (pointing at a now-unpublished parent, so it still resolved to `PendingChanges` and hid the canonical from `workingVersions()`). It also closes a null-pointer hole exposed by finding #3's earlier fix: the unpublish action was passing `$record->publishedVersion`, which is `null` when `$record` is already the canonical.

## What changed

### `app/Services/TrovePublisher.php`

- `unpublish(Trove $canonical)` now folds any shadow draft onto the canonical before nulling `published_at`: if `$canonical->draft()->first()` exists, it's published via the existing `publish()` (copies content/relations/media, deletes the draft row), then the canonical instance is `refresh()`ed — `publish()` mutates a *different* in-memory instance (`draft->publishedVersion()`), so without the refresh a stale `published_at` would get saved and un-fold the edits. Wrapped in an outer `DB::transaction` (nests fine with `publish()`'s own transaction via savepoints). The no-draft path (cleanly `Published` trove) is unchanged.

### `app/Filament/Resources/TroveResource/Pages/EditTrove.php`

- The `unpublish` action now resolves the canonical from `$record`: if `$record` is `PendingChanges` it uses `$record->publishedVersion`, otherwise `$record` itself is the canonical. Fixes the null-pointer risk from always passing `publishedVersion`.
- Fixed the action's `->visible()` predicate, which was `=== PublicationState::PendingChanges || PublicationState::Published` — a logic error where the right-hand side is a truthy enum instance, making the action always visible regardless of state. Now `in_array($record->publication_state, [PendingChanges, Published], true)`.

## Accepted side effects (called out in the plan, not new)

Folding via `publish()` inherits: the media clear-and-copy behaviour of finding #8 (unchanged risk, tracked separately), clearing of any outstanding review request on the folded draft (judged correct — a trove pulled offline has no live review pending), and harmless `previous_slugs` appension if the draft changed the slug.

## Verification

- `php -l` and `vendor/bin/pint` clean on both files.
- `php artisan tinker`, run against the real (MySQL) database inside a transaction rolled back at the end (no data persisted):
  - Unpublish a cleanly `Published` trove (no draft) → resolves to `draft` state, `published_at` null.
  - Unpublish a `PendingChanges` trove (mimicking the action's canonical resolution) → canonical resolves to `draft` state, carries the draft's edited title, and the draft row is gone (hard-deleted) — exactly one row remains.
  - Unpublish a `PendingChanges` trove whose draft had an outstanding review request (`InReview`) → resulting canonical has `review_state === none`.
- Not verified: the Filament UI wiring itself (action visibility across all three states, modal, redirect) — requires a browser + admin session, not exercised in this session.

## Out of scope (unchanged)

Finding #8 (non-atomic media clear-and-copy inside `publish()`) is addressed separately.
