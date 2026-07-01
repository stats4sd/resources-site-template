<?php

namespace App\Livewire;

use App\Models\Trove;
use Livewire\Component;

class TroveRelatedTroves extends Component
{
    public Trove $resource;
    public $relatedTroves;

    public function mount(Trove $resource)
    {
        $this->resource = $resource;
        $this->relatedTroves = $resource->relatedTroves();
    }

    public function render()
    {
        return view('livewire.trove-related-troves');
    }
}
