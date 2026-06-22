<?php

namespace App\Livewire;

use Livewire\Component;

class SearchBar extends Component
{
    public string $query = '';
    public ?string $inputClass = null;
    private string $previousQuery = '';

    protected $listeners = [
        'clearSearchInput' => 'clearQuery', 
        'queryUpdated' => 'updateQuery', 
    ];
    
    public function search()
    {
        if ($this->query === $this->previousQuery) {
            return;
        }

        $this->dispatch('queryUpdated', $this->query);

        $this->previousQuery = $this->query;

    }

    public function updateQuery($query)
    {
        if ($query === $this->query) {
            return;
        }

        $this->query = $query;
    }

    public function clearQuery()
    {
        $this->reset('query');
    }

    public function render()
    {
        return view('livewire.search-bar');
    }
}