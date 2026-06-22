<?php

namespace App\Livewire;

use App\Models\Trove;
use Livewire\Component;

class TroveCollections extends Component
{
    public Trove $resource;
    public $collections;

    public function mount(Trove $resource)
    {
        $this->resource = $resource;
        $this->collections = $resource->collections;
    }
    
    public function render()
    {
        return view('livewire.trove-collections');
    }
}
