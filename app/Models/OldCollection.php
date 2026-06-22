<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OldCollection extends Model
{
    protected $connection = 'mysql_old_troves';
    protected $table = 'collections';

    public function oldTroves(): BelongsToMany
    {
        return $this->belongsToMany(OldTrove::class, '_link_collections_troves', 'collection_id', 'trove_id');
    }

}
