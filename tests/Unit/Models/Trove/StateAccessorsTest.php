<?php

use App\Enums\PublicationState;
use App\Enums\ReviewState;
use App\Models\Trove;

// Pure accessor logic — no persistence needed, so build unsaved instances.
function troveWith(array $attributes): Trove
{
    $trove = new Trove;

    foreach ($attributes as $key => $value) {
        $trove->{$key} = $value;
    }

    return $trove;
}

describe('publicationState', function () {
    it('is PendingChanges whenever published_id is set', function () {
        expect(troveWith(['published_id' => 5, 'published_at' => null])->publicationState)
            ->toBe(PublicationState::PendingChanges)
            // published_id wins even if the draft row somehow also carries a published_at
            ->and(troveWith(['published_id' => 5, 'published_at' => now()])->publicationState)
            ->toBe(PublicationState::PendingChanges);
    });

    it('is Published for a canonical row with a publish date', function () {
        expect(troveWith(['published_id' => null, 'published_at' => now()])->publicationState)
            ->toBe(PublicationState::Published);
    });

    it('is Draft for a never-published canonical row', function () {
        expect(troveWith(['published_id' => null, 'published_at' => null])->publicationState)
            ->toBe(PublicationState::Draft);
    });
});

describe('reviewState', function () {
    it('is Reviewed whenever reviewed_at is set, even with a lingering request', function () {
        expect(troveWith(['reviewed_at' => now(), 'review_requested_at' => now()])->reviewState)
            ->toBe(ReviewState::Reviewed);
    });

    it('is InReview when a request is outstanding and no review recorded', function () {
        expect(troveWith(['reviewed_at' => null, 'review_requested_at' => now()])->reviewState)
            ->toBe(ReviewState::InReview);
    });

    it('is None when neither review column is set', function () {
        expect(troveWith(['reviewed_at' => null, 'review_requested_at' => null])->reviewState)
            ->toBe(ReviewState::None);
    });
});

describe('isPublished / hasPublishedVersion', function () {
    it('reports isPublished from published_at alone', function () {
        expect(troveWith(['published_at' => now()])->is_published)->toBeTrue()
            ->and(troveWith(['published_at' => null])->is_published)->toBeFalse();
    });

    it('reports hasPublishedVersion for a live canonical or any shadow draft', function () {
        expect(troveWith(['published_id' => null, 'published_at' => now()])->hasPublishedVersion)->toBeTrue()
            ->and(troveWith(['published_id' => 3, 'published_at' => null])->hasPublishedVersion)->toBeTrue()
            ->and(troveWith(['published_id' => null, 'published_at' => null])->hasPublishedVersion)->toBeFalse();
    });
});
