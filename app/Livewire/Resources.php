<?php

namespace App\Livewire;

use App\Models\Tag;
use App\Models\Trove;
use Livewire\Component;
use App\Traits\UsesCustomSearchOptions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class Resources extends Component
{
    use UsesCustomSearchOptions;
    
    public ?string $query = null;
    public EloquentCollection $resources;
    public int $totalResources = 0;
    public array $selectedLanguages = [];
    public array $selectedResearchMethods = [];
    public array $selectedTopics = [];

    protected $listeners = ['queryUpdated' => 'updateResults'];

    public function __construct()
    {
        $this->resources = new EloquentCollection();  
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

    public function search()
    {
        // Troves 
            // Step 1: Start with a base query for resources
            $query = Trove::where('is_published', 1);
        
            // Step 2: Apply research methods filters if selected
            if (!empty($this->selectedResearchMethods)) {
                foreach ($this->selectedResearchMethods as $methodId) {
                    $query->whereHas('tags', function ($q) use ($methodId) {
                        $q->where('tags.id', $methodId);
                    });
                }
            }
        
            // Step 3: Apply topics filters if selected
            if (!empty($this->selectedTopics)) {
                foreach ($this->selectedTopics as $topicId) {
                    $query->whereHas('tags', function ($q) use ($topicId) {
                        $q->where('tags.id', $topicId);
                    });
                }
            }

            // Step 4: Apply language filters if selected
            if (!empty($this->selectedLanguages)) {
                ray('langfilter selected');
                $query->whereLocales('title', $this->selectedLanguages);
            }

            // Step 5: Apply search term if there's a query
            if (!empty($this->query)) {
                $searchResults = Trove::search($this->query, $this->getSearchWithOptions())->get();
                if ($searchResults->isNotEmpty()) {
                    // Step 6: Filter the Eloquent query to only include matching IDs
                    $ids = $searchResults->pluck('id')->toArray();

                    $this->resources = $query->whereIn('id', $ids)->orderByRaw('FIELD(id,' . implode(',', $ids) . ')')->get();
                    $this->totalResources = $this->resources->count();
                } else {
                    // No search results, return empty for resources
                    $this->resources = new EloquentCollection();
                    $this->totalResources = 0;
                }
            }
            else {
                $this->resources = $query->get();
                $this->totalResources = $this->resources->count();
            }
    }

    public function updateResults($query)
    {
        if ($query !== $this->query) {
            $this->query = $query;
            $this->search();
        }
    }
    
    public function clearFilters()
    {
        $this->reset('query', 'selectedLanguages', 'selectedResearchMethods', 'selectedTopics');
        $this->dispatch('clearSearchInput');
    }

    public function clearSearch()
    {
        $this->reset('query');
        $this->dispatch('clearSearchInput');
        $this->search();
    }

    public function render()
    {
        return view('livewire.resources', [
            'researchMethods' => $this->researchMethods,
            'topics' => $this->topics,
        ]);
    }
}
