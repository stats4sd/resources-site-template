<?php

namespace App\Livewire;

use App\Contracts\SearchesLibrary;
use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Services\Search\DatabaseLibrarySearch;
use App\Services\Search\LibraryHit;
use App\Services\Search\LibrarySearchRequest;
use App\Services\Search\LibrarySearchResult;
use App\Services\Search\SearchUnavailableException;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The public search/browse surface: one SearchesLibrary request per render (text
 * search, tag/type/language filters, facet counts, cross-index ranking and
 * pagination), then one page of models hydrated fresh. No catalogue data lives in
 * Livewire state — public properties are just the current query/filter/page scalars.
 */
class BrowseAll extends Component
{
    #[Url(as: 'q')]
    public ?string $query = null;

    #[Url]
    public array $selectedTagsByType = [];

    #[Url]
    public array $selectedTroveTypes = [];

    #[Url]
    public array $selectedLanguages = [];

    #[Url]
    public int $page = 1;

    public bool $searchUnavailable = false;

    private int $perPage = 24;

    public function mount(): void
    {
        $this->initialiseTagFilters();
    }

    /**
     * Every filterable tag type needs its selectedTagsByType key present as an array
     * before the first render: the checkboxes bind to selectedTagsByType.{tagTypeId},
     * and Livewire's client only treats a checkbox as part of a group when the bound
     * value is already an array. An undefined key makes a click set the whole key to
     * boolean true — visually checking every box in the group and breaking the filter.
     * Selections already present (from the URL) are kept; unknown keys are dropped.
     */
    private function initialiseTagFilters(): void
    {
        $emptyFilters = $this->emptyTagFilters();

        $currentSelections = array_map(
            fn ($tagIds) => array_map(intval(...), (array) $tagIds),
            array_intersect_key($this->selectedTagsByType, $emptyFilters),
        );

        $this->selectedTagsByType = array_replace($emptyFilters, $currentSelections);
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function emptyTagFilters(): array
    {
        return TagType::where('show_in_filter', true)
            ->pluck('id')
            ->mapWithKeys(fn (int $tagTypeId) => [$tagTypeId => []])
            ->all();
    }

    public function updated(string $property): void
    {
        if ($property === 'page') {
            return;
        }

        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'selectedLanguages', 'selectedTroveTypes', 'page');
        $this->selectedTagsByType = $this->emptyTagFilters();
    }

    public function clearSearch(): void
    {
        $this->reset('query', 'page');
    }

    #[Computed]
    public function filterTagTypes(): SupportCollection
    {
        $locale = app()->getLocale();

        return TagType::where('show_in_filter', true)
            ->with('tags')
            ->get()
            ->sortBy(fn (TagType $tagType) => [
                $tagType->order_column === null ? 1 : 0,
                $tagType->order_column ?? 0,
                mb_strtolower($tagType->getTranslation('label', $locale)),
            ])
            ->values()
            ->each(function (TagType $tagType) use ($locale) {
                $sortedTags = $tagType->use_custom_tag_order
                    ? $tagType->tags->sortBy(fn (Tag $tag) => [
                        $tag->order_column === null ? 1 : 0,
                        $tag->order_column ?? 0,
                        mb_strtolower($tag->getTranslation('name', $locale)),
                    ])
                    : $tagType->tags->sortBy(fn (Tag $tag) => mb_strtolower($tag->getTranslation('name', $locale)));

                $tagType->setRelation('tags', $sortedTags->values());
            });
    }

    #[Computed]
    public function filterTroveTypes(): SupportCollection
    {
        $locale = app()->getLocale();

        return TroveType::all()
            ->sortBy(fn (TroveType $troveType) => mb_strtolower($troveType->getTranslation('label', $locale)))
            ->values();
    }

    public function render()
    {
        $this->searchUnavailable = false;

        $result = $this->runSearch($this->buildSearchRequest());

        if ($this->page > $result->totalPages && $result->totalPages > 0) {
            $this->page = $result->totalPages;
            $result = $this->runSearch($this->buildSearchRequest());
        }

        $items = $this->hydrateItems($result->hits);

        $startOfPage = $result->totalHits === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;

        return view('livewire.browse-all', [
            'items' => $items,
            'filterTagTypes' => $this->filterTagTypes,
            'filterTroveTypes' => $this->filterTroveTypes,
            'totalHits' => $result->totalHits,
            'totalPages' => $result->totalPages,
            'startOfPage' => $startOfPage,
            'endOfPage' => $startOfPage === 0 ? 0 : $startOfPage + $items->count() - 1,
            'pageWindow' => $this->pageWindow($result->totalPages),
            'facetsAvailable' => $result->facets !== null,
            'tagCounts' => $result->facets->tagCounts ?? [],
            'troveTypeCounts' => $result->facets->troveTypeCounts ?? [],
            'localeCounts' => $result->facets->localeCounts ?? [],
        ]);
    }

    protected function buildSearchRequest(): LibrarySearchRequest
    {
        return new LibrarySearchRequest(
            query: $this->query,
            tagIdsByType: array_map(
                fn ($tagIds) => array_map(intval(...), (array) $tagIds),
                $this->selectedTagsByType,
            ),
            troveTypeIds: array_map(intval(...), $this->selectedTroveTypes),
            locales: array_map(strval(...), $this->selectedLanguages),
            page: max(1, $this->page),
            perPage: $this->perPage,
        );
    }

    /**
     * On an engine outage the page stays usable: flag the notice and serve the
     * date-ordered database fallback instead.
     */
    protected function runSearch(LibrarySearchRequest $request): LibrarySearchResult
    {
        try {
            return app(SearchesLibrary::class)->search($request);
        } catch (SearchUnavailableException) {
            $this->searchUnavailable = true;

            return app(DatabaseLibrarySearch::class)->search($request);
        }
    }

    /**
     * Hydrate the page's hits into card item arrays, in hit order. Hits whose row has
     * vanished or lost public visibility since indexing are dropped silently.
     *
     * @param  array<int, LibraryHit>  $hits
     */
    protected function hydrateItems(array $hits): SupportCollection
    {
        $hitIds = fn (string $type) => collect($hits)->where('type', $type)->pluck('id')->all();

        $troves = Trove::query()
            ->whereNotNull('published_at')
            ->whereNull('published_id')
            ->whereIn('id', $hitIds('trove'))
            ->with(['media', 'troveType'])
            ->get()
            ->keyBy('id');

        $collections = Collection::query()
            ->where('public', true)
            ->whereIn('id', $hitIds('collection'))
            ->with('media')
            ->get()
            ->keyBy('id');

        return collect($hits)
            ->map(function (LibraryHit $hit) use ($troves, $collections) {
                if ($hit->type === 'trove') {
                    return $this->troveItem($troves->get($hit->id));
                }

                return $this->collectionItem($collections->get($hit->id));
            })
            ->filter()
            ->values();
    }

    private function troveItem(?Trove $trove): ?array
    {
        if ($trove === null) {
            return null;
        }

        return [
            'type' => 'resource',
            'id' => $trove->id,
            'slug' => $trove->slug,
            'title' => $trove->title,
            'description' => $trove->description,
            'troveType' => $trove->troveType,
            'tags' => null,
            'cover_image_thumb' => $trove->cover_image_thumb,
        ];
    }

    private function collectionItem(?Collection $collection): ?array
    {
        if ($collection === null) {
            return null;
        }

        return [
            'type' => 'collection',
            'id' => $collection->id,
            'slug' => null,
            'title' => $collection->title,
            'description' => $collection->description,
            'troveType' => null,
            'tags' => null,
            'cover_image_thumb' => $collection->cover_image_thumb,
        ];
    }

    /**
     * The page numbers to render as links: a window around the current page.
     *
     * @return array<int, int>
     */
    protected function pageWindow(int $totalPages): array
    {
        $windowStart = max(1, $this->page - 2);
        $windowEnd = min($totalPages, $this->page + 2);

        return $windowEnd < $windowStart ? [] : range($windowStart, $windowEnd);
    }
}
