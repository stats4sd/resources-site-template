<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\TagType;
use App\Models\Trove;
use App\Traits\UsesCustomSearchOptions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

class BrowseAll extends Component
{
    use UsesCustomSearchOptions;

    public ?string $query = null;

    public EloquentCollection $resources;

    public EloquentCollection $collections;

    public SupportCollection $items;

    public SupportCollection $renderedItems; // subset of items made with custom pagination

    public int $currentPage = 1;

    public int $pageCount = 1;

    public int $perPage = 100;

    public int $totalResourcesAndCollections = 0;

    public int $renderedResourcesAndCollections = 0;

    public array $selectedLanguages = [];

    public array $selectedTagsByType = [];

    public bool $searchUnavailable = false;

    protected $listeners = ['queryUpdated' => 'updateResults'];

    public function mount()
    {
        // The set.locale middleware has already resolved and set the app locale for this request.
        $this->renderedItems = collect();

        $this->initialiseTagFilters();
        $this->fetchInitialData();
    }

    /**
     * Every filterable tag type needs its selectedTagsByType key present as an array before
     * the first render: the checkboxes bind to selectedTagsByType.{tagTypeId}, and Livewire's
     * client only treats a checkbox as part of a group when the bound value is already an
     * array. An undefined key makes a click set the whole key to boolean true — visually
     * checking every box in the group and crashing search()'s whereIn.
     */
    private function initialiseTagFilters(): void
    {
        $this->selectedTagsByType = TagType::where('show_in_filter', true)
            ->pluck('id')
            ->mapWithKeys(fn (int $tagTypeId) => [$tagTypeId => []])
            ->all();
    }

    public function fetchInitialData()
    {
        $this->resources = Trove::with(['troveType', 'themeAndTopicTags'])->whereNotNull('published_at')->get();
        $this->collections = Collection::where('public', 1)->get();
        $this->mergeItems();
    }

    public function updateResults($query)
    {
        if ($query !== $this->query) {
            $this->query = $query;
            $this->search();
        }
    }

    public function search()
    {
        $this->searchUnavailable = false;

        // Fetch Resources (Trove)
        $resourceQuery = Trove::query()->whereNotNull('published_at');
        $resourceHits = [];

        if (!empty($this->query)) {
            $resourceHits = $this->searchHits(Trove::class);
            $ids = collect($resourceHits)->pluck('id')->toArray();

            if ($ids) {
                $resourceQuery->whereIn('id', $ids)
                    ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')');
            } else {
                // A non-empty query with no hits must return nothing, not the whole library.
                $resourceQuery->whereRaw('1 = 0');
            }
        }

        foreach ($this->selectedTagsByType as $tagIds) {
            if (!empty($tagIds)) {
                $resourceQuery->whereHas('tags', fn($q) => $q->whereIn('tags.id', $tagIds));
            }
        }

        if (!empty($this->selectedLanguages)) {
            $resourceQuery->whereLocales('title', $this->selectedLanguages);
        }

        $this->resources = $resourceQuery->get();

        // Fetch Collections
        $collectionQuery = Collection::query()->where('public', 1);
        $collectionHits = [];

        if (!empty($this->query)) {
            $collectionHits = $this->searchHits(Collection::class);
            $ids = collect($collectionHits)->pluck('id')->toArray();

            if ($ids) {
                $collectionQuery->whereIn('id', $ids)
                    ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')');
            } else {
                $collectionQuery->whereRaw('1 = 0');
            }
        }

        if (!empty($this->selectedLanguages)) {
            $collectionQuery->whereLocales('title', $this->selectedLanguages);
        }

        $this->collections = $collectionQuery->get();

        // Merge with ranking preserved
        $this->mergeItems($resourceHits, $collectionHits);
    }

    /**
     * Run the Scout query and return its raw hits. A search-engine outage sets the
     * unavailable flag and yields no hits rather than 500-ing the whole page.
     *
     * @param  class-string  $model
     * @return array<int, array<string, mixed>>
     */
    private function searchHits(string $model): array
    {
        try {
            return $model::search($this->query, $this->getSearchWithOptions())->raw()['hits'] ?? [];
        } catch (Throwable $exception) {
            report($exception);
            $this->searchUnavailable = true;

            return [];
        }
    }

    public function mergeItems(array $resourceHits = [], array $collectionHits = [])
    {
        $resources = $this->resources->map(function($r) use ($resourceHits) {
            $hit = collect($resourceHits)->firstWhere('id', $r->id);
            return [
                'type' => 'resource',
                'id' => $r->id,
                'slug' => $r->slug,
                'title' => $r->title,
                'description' => $r->description,
                'troveType' => $r->troveType,
                'tags' => $r->themeAndTopicTags,
                'cover_image_thumb' => $r->cover_image_thumb,
                'score' => $hit['_rankingScore'] ?? 0,
            ];
        });

        $collections = $this->collections->map(function($c) use ($collectionHits) {
            $hit = collect($collectionHits)->firstWhere('id', $c->id);
            return [
                'type' => 'collection',
                'id' => $c->id,
                'slug' => null, // collections use ID instead of slug
                'title' => $c->title,
                'description' => $c->description,
                'troveType' => null,
                'tags' => null,
                'cover_image_thumb' => $c->cover_image_thumb,
                'score' => $hit['_rankingScore'] ?? 0,
            ];
        });

        // Merge and sort by score
        $this->items = collect($resources)
            ->merge($collections)
            ->sortByDesc('score')
            ->values();

        $this->totalResourcesAndCollections = $this->items->count();
        $this->pageCount = ceil($this->totalResourcesAndCollections / $this->perPage);

        $this->loadPage(1);
    }

    public function clearFilters()
    {
        $this->reset('query', 'selectedLanguages');
        $this->initialiseTagFilters();
        $this->dispatch('clearSearchInput');
        $this->search();
    }

    public function clearSearch()
    {
        $this->reset('query');
        $this->dispatch('clearSearchInput');
        $this->search();
    }

    public function getFilterTagTypesProperty()
    {
        $locale = app()->getLocale();

        return TagType::where('show_in_filter', true)
            ->orderByRaw('ISNULL(order_column), order_column ASC')
            ->orderByRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(label, '$.\"$locale\"')))")
            ->with(['tags' => fn($q) => $q
                ->orderByRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"$locale\"')))")
            ])
            ->get()
            ->each(function ($tagType) {
                if ($tagType->use_custom_tag_order) {
                    $tagType->setRelation('tags',
                        $tagType->tags
                            ->sortBy(fn($tag) => [$tag->order_column === null ? 1 : 0, $tag->order_column ?? PHP_INT_MAX])
                            ->values()
                    );
                }
            });
    }

    public function loadPage(int $page): void
    {
        $page = max(1, min($page, $this->pageCount));

        $this->currentPage = $page;
        $this->renderedItems = $this->items->skip(($page - 1) * $this->perPage)->take($this->perPage);
        $this->renderedResourcesAndCollections = $this->renderedItems->count();
    }

    #[Computed]
    public function startOfPage(): int
    {
        return ($this->currentPage-1) * $this->perPage + 1;
    }

    #[Computed]
    public function endOfPage(): int
    {
        return min($this->currentPage * $this->perPage, $this->totalResourcesAndCollections);
    }

    public function render()
    {
        return view('livewire.browse-all', [
            'filterTagTypes' => $this->filterTagTypes,
            'items' => $this->items,
        ]);
    }
}
