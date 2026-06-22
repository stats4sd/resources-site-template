<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Collection;
use App\Traits\UsesCustomSearchOptions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class Collections extends Component
{
    use UsesCustomSearchOptions;
    
    public ?string $query = null;
    public EloquentCollection $collections;
    public int $totalCollections;
    public array $selectedLanguages = [];

    protected $listeners = ['queryUpdated' => 'updateResults'];

    public function __construct()
    {
        $this->collections = new EloquentCollection();  
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
        // Collections
            // Step 1: Start with a base query for collections
            $collectionsQuery = Collection::where('public', 1);
        
            // Step 2: Apply search term if there's a query
            if (!empty($this->query)) {
                $collectionResults = Collection::search($this->query, $this->getSearchWithOptions())->get();
                if ($collectionResults->isNotEmpty()) {
                    $collectionIds = $collectionResults->pluck('id')->toArray();

                    $this->collections = $collectionsQuery
                        ->whereIn('id', $collectionIds)
                        ->orderByRaw('FIELD(id, ' . implode(',', $collectionIds) . ')')
                        ->get();
                } else {
                    // No matching collections found, return empty collections
                    $this->collections = new EloquentCollection();
                    $this->totalCollections = 0;
                    return;
                }
            }
        
            // Step 3: Apply language filters if selected
            if (!empty($this->selectedLanguages)) {
                $collectionsQuery->whereLocales('title', $this->selectedLanguages);
            }
        
            // Step 4: Retrieve filtered collections
            $this->collections = $collectionsQuery->get();
            $this->totalCollections = $this->collections->count();
    }

    public function clearFilters()
    {
        $this->reset('query', 'selectedLanguages');
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
        return view('livewire.collections');
    }
}
