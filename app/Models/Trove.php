<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\Support\MediaStream;
use Illuminate\Database\Eloquent\SoftDeletes;
use Oddvalue\LaravelDrafts\Concerns\HasDrafts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

class Trove extends Model implements HasMedia
{
    use HasDrafts;
    use HasFactory;
    use HasFilamentComments;
    use HasTranslations;
    use InteractsWithMedia;
    use Searchable;
    use SoftDeletes;

    protected $casts = [
        'id' => 'integer',
        'uploader_id' => 'integer',
        'creation_date' => 'date',
        'trove_type_id' => 'integer',
        'source' => 'boolean',
        'external_links' => 'array',
        'youtube_links' => 'array',
        'check_requested' => 'boolean',
        'previous_slugs' => 'array',
    ];

    public array $translatable = [
        'title',
        'description',
        'external_links',
        'youtube_links',
    ];

    protected array $draftableRelations = [
        'tags',
        'troveTypes',
        'collections',
    ];

    protected static function booted()
    {
        // listen to custom event 'drafted'
        static::registerModelEvent('drafted', function ($trove) {

            // clone media items to the newly created draft
            $draft = $trove->revisions()->where('is_current', true)->first();

            $trove->getRegisteredMediaCollections()->each(function (MediaCollection $collection) use ($trove, $draft) {

                $trove->getMedia($collection->name)->each(function (Media $media) use ($draft) {

                    $media->copy($draft, $media->collection_name, $media->disk);
                });
            });
        });

        static::saving(function (Trove $trove) {

            // don't generate a slug if it already exists
            if ($trove->slug) {
                return;
            }

            // set the slug to the first available title locale
            $locales = $trove->getTranslatedLocales('title');

            $trove->slug = Str::slug($trove->getTranslation('title', $locales[0])).'-'.Carbon::now()->format('Y-m-d');

            // check for uniqueness and append a number if necessary
            $uniquenessQuery = Trove::withTrashed()
                ->withDrafts()
                ->where('slug', $trove->slug);

            if ($trove->id) {
                $uniquenessQuery = $uniquenessQuery->where('id', '!=', $trove->id);
            }

            $count = $uniquenessQuery->count();

            if ($count > 0) {
                $trove->slug = $trove->slug.'-'.$count;
            }

        });

    }

    // Media Library - explicitly register collections
    public function registerMediaCollections(): void
    {
        foreach (config('app.locales') as $key => $locale) {
            $this->addMediaCollection("cover_image_{$key}")
                ->singleFile();
            $this->addMediaCollection("content_{$key}");
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
            get: fn () => $this->getFirstMediaUrl('cover_image_'.app()->getLocale()) ?? asset('images/default-cover-photo.jpg')
        );
    }

    protected function coverImageThumb(): Attribute
    {
        return new Attribute(
            get: function () {
                $currentLocale = app()->getLocale();
                $locales = array_keys(config('branding.locales', ['en' => 'English']));

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function troveTypes(): BelongsToMany
    {
        return $this->belongsToMany(TroveType::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)
            ->withPivot('id');
    }

    public function relatedTroves()
    {
        // Using the collections to get related troves
        return Trove::whereHas('collections', function ($query) {
            $query->whereIn('collections.id', $this->collections->pluck('id'));
        })
            ->where('id', '!=', $this->id)  // Exclude itself
            ->get();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function hasPublishedVersion(): Attribute
    {
        return new Attribute(
            get: fn () => $this->revisions()->where('is_published', true)->exists()
        );
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
            'is_published' => (int) $this->is_published,
            'id' => $this->id,
        ];
    }

    public function themeAndTopicTags(): MorphToMany
    {
        return $this->tags()->whereHas('tagType', function ($query) {
            $query->whereIn('slug', ['themes', 'topics']);
        });
    }

    public function downloadAllFilesAsZip()
    {
        // Get the current app locale
        $locale = app()->getLocale();

        // Get the media collection name
        $collectionName = 'content_'.$locale;

        // Get all media files for this locale
        $troveFiles = $this->getMedia($collectionName);

        // Get external/YouTube links for this locale
        $links = $this->getDownloadableLinks($locale);

        // Check if there is anything to download
        if ($troveFiles->isEmpty() && $links->isEmpty()) {
            return redirect()->back()->with('error', __('No downloadable files are available.'));
        }

        // Return the ZIP of all files
        $filename = Str::slug($this->title)."-{$locale}-files.zip";

        $mediaStream = MediaStream::create($filename)->addMedia($troveFiles);

        // No links to add, so the plain media stream is sufficient
        if ($links->isEmpty()) {
            return $mediaStream;
        }

        // Otherwise stream the media zip with an extra links manifest file appended
        $manifest = $this->buildLinksManifest($links);

        return response()->stream(function () use ($mediaStream, $manifest) {
            $zip = $mediaStream->getZipStream(finish: false);
            $zip->addFile('links.txt', $manifest);
            $zip->finish();
        }, 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    protected function getDownloadableLinks(string $locale): \Illuminate\Support\Collection
    {
        $externalLinks = $this->getTranslation('external_links', $locale) ?? [];
        if (isset($externalLinks['link_url'])) {
            $externalLinks = [$externalLinks];
        }

        $youtubeLinks = $this->getTranslation('youtube_links', $locale) ?? [];
        if (isset($youtubeLinks['youtube_id'])) {
            $youtubeLinks = [$youtubeLinks];
        }

        $links = collect($externalLinks)
            ->filter(fn ($link) => ! empty($link['link_url']) && ! empty($link['link_title']))
            ->map(fn ($link) => ['title' => $link['link_title'], 'url' => $link['link_url']])
            ->values();

        foreach ($youtubeLinks as $link) {
            if ($youtubeId = $link['youtube_id'] ?? null) {
                $links->push([
                    'title' => 'YouTube video',
                    'url' => "https://www.youtube.com/watch?v={$youtubeId}",
                ]);
            }
        }

        return $links;
    }

    protected function buildLinksManifest(\Illuminate\Support\Collection $links): string
    {
        return $links
            ->map(fn ($link) => "{$link['title']}\n{$link['url']}")
            ->implode("\n\n");
    }

    public static function findBySlugOrRedirect($troveKey): ?self
    {
        // Try slug
        $trove = self::where('slug', $troveKey)
            ->where('is_published', 1)
            ->first();
        if ($trove) {
            return $trove;
        }

        // Try id
        if (is_numeric($troveKey)) {
            $trove = self::where('id', (int) $troveKey)
                ->where('is_published', 1)
                ->first();
            if ($trove) {
                return $trove;
            }
        }

        // Try previous_slugs (string)
        $trove = self::whereJsonContains('previous_slugs', (string) $troveKey)
            ->where('is_published', 1)
            ->first();
        if ($trove) {
            return $trove;
        }

        // Try previous_slugs (numeric)
        if (is_numeric($troveKey)) {
            $trove = self::whereJsonContains('previous_slugs', (int) $troveKey)
                ->where('is_published', 1)
                ->first();
            if ($trove) {
                return $trove;
            }
        }

        return null;
    }

    // get cover image URL
    public function getCoverImageUrl(): string
    {
        $currentLocale = app()->getLocale();
        $locales = ['en', 'es', 'fr'];

        // Ordered fallback: current locale first, then English, then any remaining
        $orderedLocales = array_merge([$currentLocale], array_diff($locales, [$currentLocale]));

        foreach ($orderedLocales as $locale) {
            $coverImage = $this->getMedia('cover_image_' . $locale)->first();
            if ($coverImage) {
                return $coverImage->getFullUrl();
            }
        }

        // Default image
        return asset('images/default-cover-photo.jpg');
    }

    public function getContentMedia(): \Illuminate\Support\Collection
    {
        $currentLocale = app()->getLocale();
        $locales = array_keys(config('branding.locales', ['en' => 'English'])); // fallback priority

        // Ordered fallback: current locale first, then English, then any remaining
        $orderedLocales = array_merge([$currentLocale], array_diff($locales, [$currentLocale]));

        foreach ($orderedLocales as $locale) {
            $media = $this->getMedia('content_' . $locale);
            if ($media->isNotEmpty()) {
                return $media;
            }
        }

        return collect(); // empty collection if no media found
    }

}
