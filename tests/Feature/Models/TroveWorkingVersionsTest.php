<?php

use App\Models\Trove;
use App\Models\User;

function makeDraft(Trove $canonical, array $attributes = []): Trove
{
    return Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->draftOf($canonical)
        ->create($attributes));
}

describe('workingVersions', function () {
    it('yields the draft when one exists, otherwise the canonical', function () {
        $withDraft = publishedTrove();
        $draft = makeDraft($withDraft);
        $withoutDraft = publishedTrove();
        $neverPublished = draftTrove();

        $ids = Trove::workingVersions()->pluck('id')->sort()->values()->all();

        expect($ids)->toBe(collect([$draft->id, $withoutDraft->id, $neverPublished->id])->sort()->values()->all())
            // the canonical that has a draft is NOT a working version
            ->and($ids)->not->toContain($withDraft->id);
    });
});

describe('awaitingReviewBy', function () {
    it('returns only outstanding-review working rows assigned to the given user', function () {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        // Draft in review, assigned to me -> included.
        $mine = makeDraft(publishedTrove(), ['review_requested_at' => now(), 'reviewer_id' => $me->id]);
        // Draft in review, assigned to someone else -> excluded.
        $theirs = makeDraft(publishedTrove(), ['review_requested_at' => now(), 'reviewer_id' => $someoneElse->id]);
        // Working canonical (no draft) in review, assigned to me -> included.
        $canonicalInReview = publishedTrove(['review_requested_at' => now(), 'reviewer_id' => $me->id]);
        // Draft already reviewed, assigned to me -> excluded (review no longer outstanding).
        $done = makeDraft(publishedTrove(), [
            'review_requested_at' => now(), 'reviewed_at' => now(), 'reviewer_id' => $me->id,
        ]);

        $ids = Trove::awaitingReviewBy($me->id)->pluck('id');

        expect($ids)->toContain($mine->id)
            ->and($ids)->toContain($canonicalInReview->id)
            ->and($ids)->not->toContain($theirs->id)
            ->and($ids)->not->toContain($done->id);
    });
});
