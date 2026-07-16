<?php

use App\Contracts\SearchesLibrary;
use App\Livewire\BrowseAll;
use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\TroveType;
use App\Services\Search\LibraryFacets;
use App\Services\Search\LibraryHit;
use App\Services\Search\LibrarySearchRequest;
use App\Services\Search\LibrarySearchResult;
use App\Services\Search\SearchUnavailableException;
use Livewire\Livewire;

beforeEach(fn () => bootPublicSite());

class FakeLibrarySearch implements SearchesLibrary
{
    /** @var array<int, LibrarySearchRequest> */
    public array $requests = [];

    public function __construct(private ?Closure $respond = null) {}

    public function search(LibrarySearchRequest $request): LibrarySearchResult
    {
        $this->requests[] = $request;

        if ($this->respond !== null) {
            return ($this->respond)($request);
        }

        return new LibrarySearchResult(hits: [], totalHits: 0, totalPages: 0, facets: null);
    }
}

function bindFakeSearch(?Closure $respond = null): FakeLibrarySearch
{
    $fake = new FakeLibrarySearch($respond);
    app()->instance(SearchesLibrary::class, $fake);

    return $fake;
}

it('serves the browse page over HTTP', function () {
    bindFakeSearch();

    $this->get('/browse-all')->assertOk();
});

it('maps query, filters and page into the search request', function () {
    $tagType = TagType::factory()->shownInFilter()->create();
    $tag = Tag::factory()->ofType($tagType)->create();
    $troveType = TroveType::factory()->create();
    $fake = bindFakeSearch();

    Livewire::test(BrowseAll::class)
        ->set('query', 'gender')
        ->set("selectedTagsByType.{$tagType->id}", [(string) $tag->id])
        ->set('selectedTroveTypes', [(string) $troveType->id])
        ->set('selectedLanguages', ['fr'])
        ->set('page', 2);

    $request = end($fake->requests);

    expect($request->query)->toBe('gender')
        ->and($request->tagIdsByType[$tagType->id])->toBe([$tag->id])
        ->and($request->troveTypeIds)->toBe([$troveType->id])
        ->and($request->locales)->toBe(['fr'])
        ->and($request->page)->toBe(2)
        ->and($request->perPage)->toBe(24);
});

it('resets the page whenever the query or a filter changes', function () {
    bindFakeSearch();

    Livewire::test(BrowseAll::class)
        ->set('page', 3)
        ->assertSet('page', 3)
        ->set('query', 'gender')
        ->assertSet('page', 1)
        ->set('page', 5)
        ->set('selectedLanguages', ['en'])
        ->assertSet('page', 1);
});

it('reads its state from the query string', function () {
    $tagType = TagType::factory()->shownInFilter()->create();
    $tag = Tag::factory()->ofType($tagType)->create();
    $fake = bindFakeSearch();

    Livewire::withQueryParams([
        'q' => 'gender',
        'selectedTagsByType' => [$tagType->id => [(string) $tag->id]],
        'selectedLanguages' => ['fr'],
        'page' => 2,
    ])->test(BrowseAll::class);

    $request = $fake->requests[0];

    expect($request->query)->toBe('gender')
        ->and($request->tagIdsByType[$tagType->id])->toBe([$tag->id])
        ->and($request->locales)->toBe(['fr'])
        ->and($request->page)->toBe(2);
});

it('hydrates the page of hits in hit order', function () {
    $troveA = publishedTrove();
    $troveB = publishedTrove();
    $collection = Collection::factory()->create();

    bindFakeSearch(fn () => new LibrarySearchResult(
        hits: [
            new LibraryHit('collection', $collection->id, 0.9),
            new LibraryHit('trove', $troveB->id, 0.8),
            new LibraryHit('trove', $troveA->id, 0.7),
        ],
        totalHits: 3,
        totalPages: 1,
        facets: null,
    ));

    $items = Livewire::test(BrowseAll::class)->viewData('items');

    expect($items->pluck('id')->all())->toBe([$collection->id, $troveB->id, $troveA->id])
        ->and($items->pluck('type')->all())->toBe(['collection', 'resource', 'resource']);
});

it('drops hits whose row has lost public visibility since indexing', function () {
    $trove = publishedTrove();
    $privateCollection = Collection::factory()->private()->create();

    bindFakeSearch(fn () => new LibrarySearchResult(
        hits: [
            new LibraryHit('collection', $privateCollection->id, 0.9),
            new LibraryHit('trove', $trove->id, 0.8),
            new LibraryHit('trove', $trove->id + 999, 0.7),
        ],
        totalHits: 3,
        totalPages: 1,
        facets: null,
    ));

    $items = Livewire::test(BrowseAll::class)->viewData('items');

    expect($items->pluck('id')->all())->toBe([$trove->id]);
});

it('clamps a stale page number to the last available page', function () {
    $fake = bindFakeSearch(fn () => new LibrarySearchResult(hits: [], totalHits: 30, totalPages: 2, facets: null));

    Livewire::withQueryParams(['page' => 9])->test(BrowseAll::class)
        ->assertSet('page', 2);

    expect(end($fake->requests)->page)->toBe(2);
});

it('shows the empty-library message when nothing is published', function () {
    bindFakeSearch();

    Livewire::test(BrowseAll::class)
        ->assertSee('No resources or collections have been added yet.');
});

it('shows the no-matches message when active filters return nothing', function () {
    bindFakeSearch();

    Livewire::test(BrowseAll::class)
        ->set('query', 'matches-nothing')
        ->assertSee('No resources or collections match your search or filters.');
});

it('falls back to the database listing with a notice when search is unavailable', function () {
    $trove = publishedTrove();

    bindFakeSearch(function () {
        throw new SearchUnavailableException('engine down');
    });

    $component = Livewire::test(BrowseAll::class)
        ->assertSet('searchUnavailable', true)
        ->assertSee('Search is temporarily unavailable');

    expect($component->viewData('items')->pluck('id')->all())->toBe([$trove->id]);
});

it('passes facet counts to the view', function () {
    bindFakeSearch(fn () => new LibrarySearchResult(
        hits: [],
        totalHits: 0,
        totalPages: 0,
        facets: new LibraryFacets(tagCounts: [5 => 2], troveTypeCounts: [3 => 1], localeCounts: ['en' => 4]),
    ));

    $component = Livewire::test(BrowseAll::class);

    expect($component->viewData('facetsAvailable'))->toBeTrue()
        ->and($component->viewData('tagCounts'))->toBe([5 => 2])
        ->and($component->viewData('troveTypeCounts'))->toBe([3 => 1])
        ->and($component->viewData('localeCounts'))->toBe(['en' => 4]);
});

it('passes empty facet maps to the view when facets are unavailable', function () {
    bindFakeSearch();

    $component = Livewire::test(BrowseAll::class);

    expect($component->viewData('facetsAvailable'))->toBeFalse()
        ->and($component->viewData('tagCounts'))->toBe([])
        ->and($component->viewData('troveTypeCounts'))->toBe([])
        ->and($component->viewData('localeCounts'))->toBe([]);
});

// The tag-filter checkboxes bind to selectedTagsByType.{tagTypeId}. Livewire's client only
// treats a checkbox as part of a group when the bound value is already an array — an undefined
// key makes a click set the whole key to boolean true (checking every box in the group). Each
// filterable tag type's key must therefore exist as an array from mount onwards, and survive
// clearFilters(). See docs/plans/fix-tag-filter-checkbox-binding.md.
it('initialises an empty tag filter array per filterable tag type on mount', function () {
    $filterable = TagType::factory()->shownInFilter()->create();
    TagType::factory()->create();
    bindFakeSearch();

    Livewire::test(BrowseAll::class)
        ->assertSet('selectedTagsByType', [$filterable->id => []]);
});

it('keeps query-string tag selections while initialising the other tag filter arrays', function () {
    $selected = TagType::factory()->shownInFilter()->create();
    $other = TagType::factory()->shownInFilter()->create();
    $tag = Tag::factory()->ofType($selected)->create();
    bindFakeSearch();

    Livewire::withQueryParams(['selectedTagsByType' => [$selected->id => [(string) $tag->id]]])
        ->test(BrowseAll::class)
        ->assertSet('selectedTagsByType', [$selected->id => [$tag->id], $other->id => []]);
});

it('re-initialises the tag filter arrays when filters are cleared', function () {
    $filterable = TagType::factory()->shownInFilter()->create();
    $tag = Tag::factory()->ofType($filterable)->create();
    bindFakeSearch();

    Livewire::test(BrowseAll::class)
        ->set("selectedTagsByType.{$filterable->id}", [(string) $tag->id])
        ->set('query', 'gender')
        ->set('selectedLanguages', ['en'])
        ->call('clearFilters')
        ->assertSet('selectedTagsByType', [$filterable->id => []])
        ->assertSet('query', null)
        ->assertSet('selectedLanguages', [])
        ->assertSet('selectedTroveTypes', [])
        ->assertSet('page', 1);
});

it('clears only the query with clearSearch', function () {
    bindFakeSearch();

    Livewire::test(BrowseAll::class)
        ->set('query', 'gender')
        ->set('selectedLanguages', ['en'])
        ->call('clearSearch')
        ->assertSet('query', null)
        ->assertSet('selectedLanguages', ['en']);
});

// A 0-result count is only a prediction under the CURRENT selection; OR-within-type/dimension
// semantics mean ticking another option in the same group can still be a meaningful action, so
// zero-count filter checkboxes stay muted but clickable rather than disabled.
it('never disables a filter checkbox even when its facet count is zero', function () {
    config(['branding.locales' => ['en' => 'English', 'fr' => 'French']]);
    $tagType = TagType::factory()->shownInFilter()->create();
    $tag = Tag::factory()->ofType($tagType)->create();
    $troveType = TroveType::factory()->create();

    bindFakeSearch(fn () => new LibrarySearchResult(
        hits: [],
        totalHits: 0,
        totalPages: 0,
        facets: new LibraryFacets(
            tagCounts: [$tag->id => 0],
            troveTypeCounts: [$troveType->id => 0],
            localeCounts: ['en' => 0, 'fr' => 0],
        ),
    ));

    $html = Livewire::test(BrowseAll::class)->html();

    expect($html)->not->toContain('disabled');
});
