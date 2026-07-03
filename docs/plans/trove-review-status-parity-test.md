# Plan: Parity test for Trove state accessors vs query scopes

**Status:** Not Started — deferred to the upcoming Trove test-suite PR.

## Context

The Trove lifecycle is described by two orthogonal computed facets, each with an in-memory accessor and a DB-side scope that must agree:

- **Publication axis** — `Trove::publicationState()` (accessor) and `Trove::scopeWithPublicationState()` (scope), keyed off `App\Enums\PublicationState` and derived from `published_at` / `published_id`.
- **Review axis** — `Trove::reviewState()` (accessor) and `Trove::scopeWithReviewState()` (scope), keyed off `App\Enums\ReviewState` and derived from `review_requested_at` / `reviewed_at`.

Within each pair, one branches on a loaded model's attributes and the other emits SQL — they cannot share a single implementation, so they are deliberately co-located in `app/Models/Trove.php`. Nothing currently guarantees they stay in agreement; this is the drift risk that previously produced the wrong "Drafts" tab membership. Since the axes were split (see [trove-split-publication-and-review-axes.md](trove-split-publication-and-review-axes.md)), each scope filters one or two columns on a single axis with **no cross-axis precedence guards**, so each parity check is now simpler and independent. When the full Trove test suite lands, add the parity tests below so any future edit to one side that isn't mirrored in the other fails loudly.

## The tests to add

For a seeded matrix of rows covering every case on both axes, assert — **per axis, independently** — that filtering via the scope yields exactly the rows whose loaded accessor resolves to that case:

```php
foreach (PublicationState::cases() as $state) {
    $viaScope = Trove::query()->withDrafts()->withPublicationState($state)->pluck('id')->sort()->values();
    $viaAccessor = Trove::withDrafts()->get()
        ->filter(fn (Trove $t) => $t->publication_state === $state)
        ->pluck('id')->sort()->values();

    expect($viaScope)->toEqual($viaAccessor);
}

foreach (ReviewState::cases() as $state) {
    $viaScope = Trove::query()->withDrafts()->withReviewState($state)->pluck('id')->sort()->values();
    $viaAccessor = Trove::withDrafts()->get()
        ->filter(fn (Trove $t) => $t->review_state === $state)
        ->pluck('id')->sort()->values();

    expect($viaScope)->toEqual($viaAccessor);
}
```

## Rows the seed/factory must include

The matrix must exercise both axes independently — crucially, rows where the two facets vary independently (e.g. a pending-changes draft that is also in review), which is exactly what the old fused enum could not represent.

- **Draft · None** — never-published canonical (`published_at` null, `published_id` null, no review).
- **Draft · InReview** — fresh draft with `review_requested_at` set, `reviewed_at` null.
- **Draft · Reviewed** — `review_requested_at` and `reviewed_at` both set, `published_at`/`published_id` null.
- **Published · None** — live canonical (`published_at` set, `published_id` null), no review.
- **Published · Reviewed** — live canonical carrying a completed-review stamp.
- **PendingChanges · None** — shadow draft (`published_id` set), no review.
- **PendingChanges · InReview** — shadow draft with an outstanding review on the edits. Under the old fused model this row was masked to `InReview` and dropped out of the Published tab / lost its Discard & Unpublish controls; on both axes it is now `PendingChanges` **and** `InReview`.
- **PendingChanges · Reviewed** — shadow draft whose edits have been reviewed.
- **Live canonical that also has a draft** — confirms `workingVersions()` picks the draft, not both rows.

## Notes

- Test each scope in isolation (`withDrafts()->withPublicationState(...)` / `withDrafts()->withReviewState(...)`) for the parity guarantee, separately from the `ListTroves` tab queries (which additionally apply `workingVersions()` and, for the "Drafts" tab, compose both axes).
- The `ListTroves` "Drafts" tab composes `withPublicationState(Draft, PendingChanges)` with `withReviewState(None, Reviewed)` (i.e. not currently in review); "Published" uses `withPublicationState(Published, PendingChanges)` alone. A tab-level test (asserting the tabs partition/overlap as intended) is a separate, optional addition beyond the per-axis accessor⇄scope parity above.
