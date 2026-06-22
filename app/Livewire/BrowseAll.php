<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\Tag;
use App\Models\Trove;
use App\Traits\UsesCustomSearchOptions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;

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

    public array $selectedResearchMethods = [];

    public array $selectedTopics = [];

    protected $listeners = ['queryUpdated' => 'updateResults'];

    public function mount()
    {
        $this->locale = session('locale', app()->getLocale());
        app()->setLocale($this->locale);

        $this->renderedItems = collect();

        $this->fetchInitialData();
    }

    public function fetchInitialData()
    {
        $this->resources = Trove::with(['troveTypes', 'themeAndTopicTags'])->where('is_published', 1)->get();
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
        // Fetch Resources (Trove)
        $resourceQuery = Trove::query()->where('is_published', 1);
        $resourceHits = [];

        if (!empty($this->query)) {
            $resourceHits = Trove::search($this->query, $this->getSearchWithOptions())->raw()['hits'] ?? [];
            $ids = collect($resourceHits)->pluck('id')->toArray();

            if ($ids) {
                $resourceQuery->whereIn('id', $ids)
                    ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')');
            }
        }

        if (!empty($this->selectedResearchMethods)) {
            foreach ($this->selectedResearchMethods as $methodId) {
                $resourceQuery->whereHas('tags', fn($q) => $q->where('tags.id', $methodId));
            }
        }

        if (!empty($this->selectedTopics)) {
            foreach ($this->selectedTopics as $topicId) {
                $resourceQuery->whereHas('tags', fn($q) => $q->where('tags.id', $topicId));
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
            $collectionHits = Collection::search($this->query, $this->getSearchWithOptions())->raw()['hits'] ?? [];
            $ids = collect($collectionHits)->pluck('id')->toArray();

            if ($ids) {
                $collectionQuery->whereIn('id', $ids)
                    ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')');
            }
        }

        if (!empty($this->selectedLanguages)) {
            $collectionQuery->whereLocales('title', $this->selectedLanguages);
        }

        $this->collections = $collectionQuery->get();

        // Merge with ranking preserved
        $this->mergeItems($resourceHits, $collectionHits);
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
                'troveTypes' => $r->troveTypes,
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
                'troveTypes' => null,
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
        $this->reset('query', 'selectedLanguages', 'selectedResearchMethods', 'selectedTopics');
        $this->dispatch('clearSearchInput');
        $this->search();
    }

    public function clearSearch()
    {
        $this->reset('query');
        $this->dispatch('clearSearchInput');
        $this->search();
    }

    public function getResearchMethodsProperty()
    {
        $locale = app()->getLocale();

        return Tag::whereHas('tagType', function ($query) {
            $query->where('slug', 'research-methods');
        })->orderByRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"$locale\"')))")->get();
    }

    public function getTopicsProperty()
    {
        $locale = app()->getLocale();

        return Tag::whereHas('tagType', function ($query) {
            $query->where('slug', 'topics');
        })->orderByRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"$locale\"')))")->get();
    }

    public function loadPage(int $page): void
    {
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
            'researchMethods' => $this->researchMethods,
            'topics' => $this->topics,
            'items' => $this->items,
        ]);
    }
}
