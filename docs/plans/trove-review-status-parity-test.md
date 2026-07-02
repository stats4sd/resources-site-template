# Plan: Parity test for Trove review-status accessor vs query scope

**Status:** Not Started — deferred to the upcoming Trove test-suite PR.

## Context

`Trove::reviewStatus()` (the in-memory accessor) and `Trove::scopeWithReviewStatus()` (the DB-side filter used by the `ListTroves` tabs) are two representations of the same lifecycle rules — one branches on a loaded model's attributes, the other emits SQL. They cannot share a single implementation, so they are deliberately co-located in `app/Models/Trove.php` and both keyed off the `ReviewStatus` enum. Nothing currently guarantees they stay in agreement; this is the drift risk that previously produced the wrong "Drafts" tab membership. When the full Trove test suite lands, add the parity test below so any future edit to one that isn't mirrored in the other fails loudly.

## The test to add

For a seeded matrix of rows covering every `ReviewStatus` case plus the awkward edges, assert that filtering via the scope yields exactly the rows whose loaded accessor resolves to that case:

```php
foreach (ReviewStatus::cases() as $status) {
    $viaScope = Trove::query()->withDrafts()->withReviewStatus($status)->pluck('id')->sort()->values();
    $viaAccessor = Trove::withDrafts()->get()
        ->filter(fn (Trove $t) => $t->review_status === $status)
        ->pluck('id')->sort()->values();

    expect($viaScope)->toEqual($viaAccessor);
}
```

## Rows the seed/factory must include

- **Draft** — never-published canonical (`published_at` null, `published_id` null, no review).
- **InReview** — `review_requested_at` set, `reviewed_at` null (both on a fresh draft and on a pending-changes draft, to confirm the outstanding review wins over `published_id`).
- **PublishedWithPendingChanges** — shadow draft (`published_id` set), no outstanding review.
- **Published** — live canonical (`published_at` set, `published_id` null), no draft.
- **Reviewed-but-unpublished** — `review_requested_at` and `reviewed_at` both set, `published_at`/`published_id` null. This must resolve to **Draft** (it is the edge the old `whereNull('review_requested_at')` Drafts filter got wrong).
- **Live canonical that also has a draft** — confirms `workingVersions()` picks the draft, not both rows.

## Notes

- Test the scope in isolation (`withDrafts()->withReviewStatus(...)`) for the parity guarantee, separately from the `ListTroves` tab queries (which additionally apply `workingVersions()`).
- The `ListTroves` "Drafts" and "Published" tabs each pass **two** statuses to `withReviewStatus()`; a `PublishedWithPendingChanges` row therefore appears in both by design. A tab-level test (asserting the four tabs partition/overlap as intended) is a separate, optional addition beyond the accessor⇄scope parity above.
