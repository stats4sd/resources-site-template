<?php

namespace App\Models;

use App\Models\Organisation;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

class Collection extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use HasTranslations;
    use HasFilamentComments;
    use Searchable;

    protected $casts = [
        'public' => 'boolean',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    public function registerMediaCollections(): void
    {
        foreach (config('app.locales') as $key => $locale) {
            $this->addMediaCollection("cover_image_{$key}")
                ->singleFile();
        }

    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $collections = [];
        foreach (config('app.locales') as $key => $locale) {
            $collections[] = "cover_image_{$key}";
        }

        $this->addMediaConversion('cover_thumb')
            ->width(450)
            ->performOnCollections(...$collections);

    }

    protected function coverImage(): Attribute
    {
        return new Attribute(
            get: function () {
                $currentLocale = app()->getLocale();
                $locales = ['en', 'es', 'fr']; // Ordered fallback

                // Make sure current locale is checked first
                $orderedLocales = array_merge([$currentLocale], array_diff($locales, [$currentLocale]));

                foreach ($orderedLocales as $locale) {
                    $media = $this->getMedia('cover_image_' . $locale)->first();
                    if ($media) {
                        return $media->getFullUrl();
                    }
                }

                // Default image if no media found
                return asset('images/default-cover-photo.jpg');
            }
        );
    }

    protected function coverImageThumb(): Attribute
    {
        return new Attribute(
            get: function () {
                $currentLocale = app()->getLocale();
                $locales = ['en', 'es', 'fr']; // fallback priority

                // Make sure current locale is checked first
                $orderedLocales = array_merge([$currentLocale], array_diff($locales, [$currentLocale]));

                foreach ($orderedLocales as $locale) {
                    $url = $this->getFirstMediaUrl('cover_image_' . $locale, 'cover_thumb');
                    if ($url) {
                        return $url;
                    }
                }

                // Default image if no media found
                return asset('images/default-cover-photo.jpg');
            }
        );
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function troves(): BelongsToMany
    {
        return $this->belongsToMany(Trove::class)
            ->withPivot('id');
    }

    public function relatedCollections()
    {
        return Collection::whereHas('troves', function ($query) {
            $query->whereIn('collection_trove.trove_id', $this->troves->pluck('id'));
        })
            ->where('id', '!=', $this->id) // Exclude itself
            ->distinct()
            ->get();
    }


    public function toSearchableArray(): array
    {
        $titles = [];
        $descriptions = [];

        foreach (config('app.locales') as $locale => $label) {
            $title = $this->getTranslation('title', $locale);
            $description = $this->getTranslation('description', $locale);

            // Only add unique, non-empty titles/descriptions
            if ($title && !in_array($title, $titles)) {
                $titles[] = $title;
            }

            if ($description) {
                $description = strip_tags($description);
                if (!in_array($description, $descriptions)) {
                    $descriptions[] = $description;
                }
            }
        }

        return [
            'title' => implode(' ', $titles),
            'description' => implode(' ', $descriptions),
            'id' => $this->id,
            'public' => (int) $this->public,
        ];
    }
}
