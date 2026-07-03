<?php

use App\Enums\PublicationState;
use App\Enums\ReviewState;
use App\Models\Trove;

// The parity the model comments explicitly call for: the accessor (publicationState /
// reviewState) and its DB-mirror scope (scopeWithPublicationState / scopeWithReviewState)
// must agree on membership for every combination of the underlying columns.

function forceState(array $attributes): Trove
{
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()->create());
    $trove->forceFill($attributes)->saveQuietly();

    return $trove->fresh();
}

it('keeps publicationState() and scopeWithPublicationState() in parity', function () {
    // A canonical to hang the "published_id set" combinations off (satisfies the FK).
    $canonical = publishedTrove();

    forceState(['published_id' => null, 'published_at' => null]);          // Draft
    forceState(['published_id' => null, 'published_at' => now()]);         // Published
    forceState(['published_id' => $canonical->id, 'published_at' => null]);// PendingChanges
    // published_id wins even with a publish date (unusual, but the mirror must agree).
    $weirdCanonical = publishedTrove();
    forceState(['published_id' => $weirdCanonical->id, 'published_at' => now()]); // PendingChanges

    foreach (PublicationState::cases() as $state) {
        $viaScope = Trove::withDrafts()->withPublicationState($state)->pluck('id')->sort()->values()->all();
        $viaAccessor = Trove::withDrafts()->get()
            ->filter(fn (Trove $t) => $t->publicationState === $state)
            ->pluck('id')->sort()->values()->all();

        expect($viaScope)->toBe($viaAccessor, "mismatch for {$state->value}");
    }
});

it('keeps reviewState() and scopeWithReviewState() in parity', function () {
    forceState(['review_requested_at' => null, 'reviewed_at' => null]);         // None
    forceState(['review_requested_at' => now(), 'reviewed_at' => null]);        // InReview
    forceState(['review_requested_at' => now(), 'reviewed_at' => now()]);       // Reviewed
    forceState(['review_requested_at' => null, 'reviewed_at' => now()]);        // Reviewed (no lingering request)

    foreach (ReviewState::cases() as $state) {
        $viaScope = Trove::withDrafts()->withReviewState($state)->pluck('id')->sort()->values()->all();
        $viaAccessor = Trove::withDrafts()->get()
            ->filter(fn (Trove $t) => $t->reviewState === $state)
            ->pluck('id')->sort()->values()->all();

        expect($viaScope)->toBe($viaAccessor, "mismatch for {$state->value}");
    }
});

it('supports OR-ing multiple states in one scope call', function () {
    $canonical = publishedTrove();
    forceState(['published_id' => null, 'published_at' => null]);           // Draft
    forceState(['published_id' => $canonical->id, 'published_at' => null]); // PendingChanges

    $ids = Trove::withDrafts()
        ->withPublicationState(PublicationState::Draft, PublicationState::PendingChanges)
        ->pluck('id');

    // Everything except the plain published canonicals.
    $expected = Trove::withDrafts()->get()
        ->reject(fn (Trove $t) => $t->publicationState === PublicationState::Published)
        ->pluck('id');

    expect($ids->sort()->values()->all())->toBe($expected->sort()->values()->all());
});
