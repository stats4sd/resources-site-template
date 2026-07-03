<?php

use App\Models\Collection;
use App\Models\Trove;

// PublishedScope self-disables under a Filament panel. The test harness registers the
// admin panel as current by default, so exercise the public rules explicitly.
beforeEach(fn () => usePublicContext());

it('returns only published canonicals to a public (non-panel) query', function () {
    $published = publishedTrove();
    $unpublished = draftTrove();
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()->draftOf($published)->create());

    $ids = Trove::pluck('id');

    expect($ids)->toContain($published->id)
        ->and($ids)->not->toContain($unpublished->id)
        ->and($ids)->not->toContain($draft->id);
});

it('opts out of the scope with withDrafts()', function () {
    $published = publishedTrove();
    $unpublished = draftTrove();

    $ids = Trove::withDrafts()->pluck('id');

    expect($ids)->toContain($published->id)
        ->and($ids)->toContain($unpublished->id);
});

it('hides unpublished troves from relationship queries', function () {
    $collection = Collection::factory()->create();
    $published = publishedTrove();
    $unpublished = draftTrove();
    $collection->troves()->attach([$published->id, $unpublished->id]);

    $troveIds = $collection->troves()->pluck('troves.id');

    expect($troveIds)->toContain($published->id)
        ->and($troveIds)->not->toContain($unpublished->id);
});
