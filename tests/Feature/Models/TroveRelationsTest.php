<?php

use App\Models\Collection;
use App\Models\Trove;

it('relatedTroves returns troves sharing a collection, excluding itself', function () {
    $collection = Collection::factory()->create();
    $t1 = publishedTrove();
    $t2 = publishedTrove();
    $t3 = publishedTrove();
    $collection->troves()->attach([$t1->id, $t2->id, $t3->id]);

    $related = $t1->fresh()->relatedTroves();

    expect($related->pluck('id')->sort()->values()->all())
        ->toBe(collect([$t2->id, $t3->id])->sort()->values()->all())
        ->and($related->pluck('id'))->not->toContain($t1->id);
});

it('draft() and publishedVersion() ignore PublishedScope', function () {
    usePublicContext(); // scope active: unpublished rows are normally hidden

    $canonical = publishedTrove();
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()->draftOf($canonical)->create());

    // The draft is unpublished, yet these inverse links still resolve it/the canonical.
    expect($canonical->fresh()->draft?->is($draft))->toBeTrue()
        ->and($draft->fresh()->publishedVersion?->is($canonical))->toBeTrue();
});
