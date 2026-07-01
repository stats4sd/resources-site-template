<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TagType extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $casts = [
        'id' => 'integer',
        'freetext' => 'boolean',
        'show_in_filter' => 'boolean',
        'use_custom_tag_order' => 'boolean',
        'order_column' => 'integer',
    ];

    public array $translatable = [
        'label',
        'description'
    ];

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'type_id');
    }
}
