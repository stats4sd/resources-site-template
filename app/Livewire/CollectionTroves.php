<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Collection;

class CollectionTroves extends Component
{
    public Collection $collection;
    public $resources;

    public function mount(Collection $collection)
    {
        $this->collection = $collection;
        $this->resources = $collection->troves;
    }

    public function render()
    {
        return view('livewire.collection-troves');
    }
}
