<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Collection extends Model implements HasMedia
{
    use HasFactory;
    use HasFilamentComments;
    use HasTranslations;
    use InteractsWithMedia;
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
                $locales = array_keys(config('branding.locales', ['en' => 'English'])); // Ordered fallback

                // Make sure current locale is checked first
                $orderedLocales = array_merge([$currentLocale], array_diff($locales, [$currentLocale]));

                foreach ($orderedLocales as $locale) {
                    $media = $this->getMedia('cover_image_'.$locale)->first();
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
                $locales = array_keys(config('branding.locales', ['en' => 'English'])); // fallback priority

                // Make sure current locale is checked first
                $orderedLocales = array_merge([$currentLocale], array_diff($locales, [$currentLocale]));

                foreach ($orderedLocales as $locale) {
                    $url = $this->getFirstMediaUrl('cover_image_'.$locale, 'cover_thumb');
                    if ($url) {
                        return $url;
                    }
                }

                // Default image if no media found
                return asset('images/default-cover-photo.jpg');
            }
        );
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

    public function shouldBeSearchable(): bool
    {
        return (bool) $this->public;
    }

    public function toSearchableArray(): array
    {
        $titles = [];
        $descriptions = [];

        foreach (config('app.locales') as $locale => $label) {
            $title = $this->getTranslation('title', $locale);
            $description = $this->getTranslation('description', $locale);

            // Only add unique, non-empty titles/descriptions
            if ($title && ! in_array($title, $titles)) {
                $titles[] = $title;
            }

            if ($description) {
                $description = strip_tags($description);
                if (! in_array($description, $descriptions)) {
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
