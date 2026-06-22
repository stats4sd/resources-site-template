<?php

namespace App\Models;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hub extends Model
{
    use HasFactory;
    use HasTranslations;
    
    public array $translatable = [
        'name',
        'description'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
    
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'hub_tag');
    }

    public function themeTags()
    {
        return $this->tags()->whereHas('tagType', function ($query) {
            $query->where('slug', 'themes');
        });
    }
}
