# Change log: Split the Trove lifecycle into two orthogonal axes (Publication × Review)

Implements [docs/plans/trove-split-publication-and-review-axes.md](../plans/trove-split-publication-and-review-axes.md).

## Summary

The fused `ReviewStatus` enum — which flattened the genuinely 2-D lifecycle (publication × review) into one enum via a precedence hack — is gone. In its place are two single-axis computed facets, each with its own accessor and DB scope. The model now always tells the truth on both axes; the view decides which to emphasise. This is a derivation-and-presentation refactor only: no migration, no change to `TrovePublisher`, columns unchanged.

## Changes

### New enums

- **`app/Enums/PublicationState.php`** — `{Draft, Published, PendingChanges}`, implements `HasColor`/`HasIcon`/`HasLabel`. Derived from `published_at` / `published_id` alone.
- **`app/Enums/ReviewState.php`** — `{None, InReview, Reviewed}`, implements the same contracts. Derived from `review_requested_at` / `reviewed_at` alone. `Reviewed` is now a first-class member of its own axis rather than the old orphaned "orthogonal ✓ marker".

### `app/Enums/ReviewStatus.php` — deleted

No consumer references it any more.

### `app/Models/Trove.php`

- Replaced the single `reviewStatus(): Attribute` with `publicationState(): Attribute` and `reviewState(): Attribute`, each deriving from its own columns only — no cross-axis precedence.
- Replaced `scopeWithReviewStatus(ReviewStatus ...)` with `scopeWithPublicationState(PublicationState ...)` and `scopeWithReviewState(ReviewState ...)`. Every `whereNot($outstandingReview)` cross-axis guard is deleted; each scope now filters one or two columns on a single axis.
- `reviewInProgress` accessor redefined as `review_state === ReviewState::InReview`.
- `scopeAwaitingReviewBy`, `isPublished`, `hasPublishedVersion` unchanged (already single-axis).

### `app/Filament/Resources/TroveResource.php`

- Status column split into a **presentation pairing**: `publication_state` is the primary badge (carrying the "✓ reviewed by X" description line when `reviewed_at` is set); a second `review_state` badge column renders an "In review" chip only while a review is outstanding. The two facets are shown side by side, never flattened.

### `app/Filament/Resources/TroveResource/Pages/EditTrove.php`

- `mark_reviewed` → `visible(review_state === ReviewState::InReview)`.
- `discard_draft` → `visible(publication_state === PublicationState::PendingChanges)`.
- `unpublish` → `visible(publication_state === PublicationState::PendingChanges)`.
- `shouldForkOnSave` → `publication_state === PublicationState::Published`.

### `app/Filament/Resources/TroveResource/Pages/ListTroves.php`

- **Drafts** tab → `withPublicationState(Draft, PendingChanges)->withReviewState(None, Reviewed)` (composed from two explicit axes).
- **In review** tab → `withReviewState(InReview)`.
- **Needs my review** tab → `awaitingReviewBy(...)` (unchanged).
- **Published** tab → `withPublicationState(Published, PendingChanges)`; the "InReview intentionally excluded (for now)" special case is deleted.

### `HasTroveFormActions.php`

Unchanged — `reviewedAlready()` reads `reviewed_at`, `publishLabel()` reads `has_published_version`; neither touched the fused enum.

### Docs

- [docs/plans/trove-review-status-parity-test.md](../plans/trove-review-status-parity-test.md) rewritten to assert accessor⇄scope parity **per axis independently**, with a seed matrix that now includes rows where the two facets vary independently (e.g. `PendingChanges · InReview`).

## Bugs fixed as a side-effect

1. **Discard / Unpublish vanishing under review** — a live trove with pending edits under review reported `review_status === InReview` (precedence), so both controls disappeared. They now gate on the publication axis (`PendingChanges`) and are unaffected by review state.
2. **Published tab dropping in-review pending-changes troves** — the same row fell out of the "Published" tab the moment it went under review. The tab now filters on the publication axis alone and correctly includes it.

## Verification

- `php -l` clean on all six changed PHP files.
- Accessor⇄scope parity confirmed via tinker against the seeded DB for all `PublicationState` and `ReviewState` cases (scope IDs === accessor IDs on every case).
- Full end-to-end admin-panel walkthrough (two-facet rendering across the state matrix, bug-fix checks, "Mark as reviewed leaves publication untouched", optionality) is listed in the plan's Verification section for a manual pass on a seeded environment.

## Out of scope (noted only)

- Stale `reviewed_at` when a reviewed draft is edited afterward — pre-existing, orthogonal, not addressed here.
- Domain events / real notifications, review history — still deferred/excluded per the prior plan.
