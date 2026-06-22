<?php

namespace App\Models;

use App\Models\Trove;
use App\Models\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organisation extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    public function troves(): HasMany
    {
        return $this->hasMany(Trove::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
    
}
