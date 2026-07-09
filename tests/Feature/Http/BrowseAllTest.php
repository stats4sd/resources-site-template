<?php

use App\Livewire\BrowseAll;
use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;

// NOTE: a full render of BrowseAll (Livewire::test / GET /browse-all) is NOT exercised here.
// Its render path builds the filter tag types with MySQL-only SQL (ISNULL / JSON_EXTRACT /
// JSON_UNQUOTE) and its search() orders hits with MySQL's FIELD(). Those raise syntax errors
// on SQLite, so this is a documented SQLite gap (see docs/plans/test-suite-buildout.md).
// The data-selection logic below runs without the MySQL-only render path.

beforeEach(fn () => bootPublicSite());

function fakeSearchEngine(Closure $searchResult): void
{
    $engine = Mockery::mock(Engine::class);
    $engine->shouldReceive('search')->andReturnUsing($searchResult);

    $manager = Mockery::mock(EngineManager::class);
    $manager->shouldReceive('engine')->andReturn($engine);

    app()->instance(EngineManager::class, $manager);
}

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

it('returns no results when a non-empty search yields zero hits', function () {
    publishedTrove();
    publishedTrove();
    Collection::factory()->create();

    fakeSearchEngine(fn () => ['hits' => []]);

    $component = new BrowseAll;
    $component->fetchInitialData();
    $component->query = 'a-term-that-matches-nothing';
    $component->search();

    expect($component->items)->toHaveCount(0)
        ->and($component->totalResourcesAndCollections)->toBe(0)
        ->and($component->searchUnavailable)->toBeFalse();
});

it('flags search as unavailable and returns no results when the engine throws', function () {
    publishedTrove();

    fakeSearchEngine(function () {
        throw new Exception('Meilisearch is down');
    });

    $component = new BrowseAll;
    $component->fetchInitialData();
    $component->query = 'anything';
    $component->search();

    expect($component->searchUnavailable)->toBeTrue()
        ->and($component->items)->toHaveCount(0);
});

// The tag-filter checkboxes bind to selectedTagsByType.{tagTypeId}. Livewire's client only
// treats a checkbox as part of a group when the bound value is already an array — an undefined
// key makes a click set the whole key to boolean true (checking every box in the group) and
// then crash search()'s whereIn. Each filterable tag type's key must therefore exist as an
// array from mount onwards, and survive clearFilters().
it('initialises an empty tag filter array per filterable tag type on mount', function () {
    $filterable = TagType::factory()->shownInFilter()->create();
    TagType::factory()->create();

    $component = new BrowseAll;
    $component->mount();

    expect($component->selectedTagsByType)->toBe([$filterable->id => []]);
});

it('re-initialises the tag filter arrays when filters are cleared', function () {
    $filterable = TagType::factory()->shownInFilter()->create();
    $tag = Tag::factory()->ofType($filterable)->create();

    $component = new BrowseAll;
    $component->mount();
    $component->selectedTagsByType[$filterable->id] = [$tag->id];

    $component->clearFilters();

    expect($component->selectedTagsByType)->toBe([$filterable->id => []]);
});

it('filters resources to those tagged with the selected tags', function () {
    $filterable = TagType::factory()->shownInFilter()->create();
    $tag = Tag::factory()->ofType($filterable)->create();
    $tagged = publishedTrove();
    $tagged->tags()->attach($tag);
    publishedTrove();

    $component = new BrowseAll;
    $component->mount();
    $component->selectedTagsByType[$filterable->id] = [$tag->id];
    $component->search();

    expect($component->items->where('type', 'resource')->pluck('id')->all())->toBe([$tagged->id]);
});

it('clamps loadPage to the valid page range', function () {
    publishedTrove();
    publishedTrove();

    $component = new BrowseAll;
    $component->perPage = 1;
    $component->fetchInitialData();

    $component->loadPage(0);
    expect($component->currentPage)->toBe(1);

    $component->loadPage(99);
    expect($component->currentPage)->toBe(2);
});
