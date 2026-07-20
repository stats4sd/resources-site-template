<?php

namespace App\Models;

use App\Observers\TagObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

#[ObservedBy(TagObserver::class)]
class Tag extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $casts = [
        'id' => 'integer',
        'type_id' => 'integer',
        'order_column' => 'integer',
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
}
