<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $casts = [
        'id' => 'integer',
        'type_id' => 'integer',
    ];

    public array $translatable = [
        'name',
    ];

    public function troves(): MorphToMany
    {
        return $this->morphedByMany(Trove::class, 'taggable');
    }

    public function tagType(): BelongsTo
    {
        return $this->belongsTo(TagType::class, 'type_id');
    }

    public function hubs()
    {
        return $this->belongsToMany(Hub::class, 'hub_tag');
    }
}
