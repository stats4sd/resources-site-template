<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OldTag extends Model
{
    protected $connection = 'mysql_old_troves';
    protected $table = 'tags';

    public function troves(): BelongsToMany
    {
        return $this->belongsToMany(OldTrove::class, 'taggables', 'tag_id', 'taggable_id');
    }

    public function nameEn(): Attribute
    {
        return new Attribute(
            get: fn() => json_decode($this->name, true)['en'],
        );
    }
}
