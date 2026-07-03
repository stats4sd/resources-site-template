<?php

use App\Livewire\BrowseAll;
use App\Models\Collection;

// NOTE: a full render of BrowseAll (Livewire::test / GET /browse-all) is NOT exercised here.
// Its render path builds the filter tag types with MySQL-only SQL (ISNULL / JSON_EXTRACT /
// JSON_UNQUOTE) and its search() orders hits with MySQL's FIELD(). Those raise syntax errors
// on SQLite, so this is a documented SQLite gap (see docs/plans/test-suite-buildout.md).
// The data-selection logic below runs without the MySQL-only render path.

beforeEach(fn () => bootPublicSite());

it('lists published troves and public collections, excluding the rest', function () {
    $published = publishedTrove();
    $unpublished = draftTrove();
    $publicCollection = Collection::factory()->create();
    $privateCollection = Collection::factory()->private()->create();

    $component = new BrowseAll;
    $component->fetchInitialData();

    $resourceIds = $component->items->where('type', 'resource')->pluck('id');
    $collectionIds = $component->items->where('type', 'collection')->pluck('id');

    expect($resourceIds)->toContain($published->id)
        ->and($resourceIds)->not->toContain($unpublished->id)
        ->and($collectionIds)->toContain($publicCollection->id)
        ->and($collectionIds)->not->toContain($privateCollection->id);
});

it('merges resources and collections into one paginated item set', function () {
    publishedTrove();
    publishedTrove();
    Collection::factory()->create();

    $component = new BrowseAll;
    $component->fetchInitialData();

    expect($component->totalResourcesAndCollections)->toBe(3)
        ->and($component->items)->toHaveCount(3)
        ->and($component->renderedItems)->toHaveCount(3); // all on page 1 (perPage 100)
});
