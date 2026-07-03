<?php

use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
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

it('themeAndTopicTags filters to the themes and topics tag types only', function () {
    $themes = TagType::factory()->slug('themes')->create();
    $topics = TagType::factory()->slug('topics')->create();
    $other = TagType::factory()->slug('locations')->create();

    $themeTag = Tag::factory()->ofType($themes)->create();
    $topicTag = Tag::factory()->ofType($topics)->create();
    $otherTag = Tag::factory()->ofType($other)->create();

    $trove = publishedTrove();
    $trove->tags()->attach([$themeTag->id, $topicTag->id, $otherTag->id]);

    $ids = $trove->themeAndTopicTags()->pluck('tags.id');

    expect($ids)->toContain($themeTag->id)
        ->and($ids)->toContain($topicTag->id)
        ->and($ids)->not->toContain($otherTag->id);
});

it('draft() and publishedVersion() ignore PublishedScope', function () {
    usePublicContext(); // scope active: unpublished rows are normally hidden

    $canonical = publishedTrove();
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()->draftOf($canonical)->create());

    // The draft is unpublished, yet these inverse links still resolve it/the canonical.
    expect($canonical->fresh()->draft?->is($draft))->toBeTrue()
        ->and($draft->fresh()->publishedVersion?->is($canonical))->toBeTrue();
});
