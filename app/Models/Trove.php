<?php

namespace App\Models;

use Carbon\Carbon;
use App\Enums\ReviewState;
use App\Enums\PublicationState;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use App\Models\Scopes\PublishedScope;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\Support\MediaStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

class Trove extends Model implements HasMedia
{
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
        'published_at' => 'datetime',
        'previous_slugs' => 'array',
        'review_requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Relations copied from a canonical row onto its shadow draft (and synced back
     * on publish) by App\Services\TrovePublisher.
     */
    protected array $draftableRelations = [
        'tags',
        'collections',
    ];

    public array $translatable = [
        'title',
        'description',
        'external_links',
        'youtube_links',
    ];

    protected static function booted(): void
    {
        // Public visibility (R1): only published canonical rows by default.
        static::addGlobalScope(new PublishedScope);

        static::creating(function(self $trove) {
            $trove->slug = $trove->generateSlug();
        });

        static::saving(function (Trove $trove) {

            if($trove->isDirty('title')) {

                $trove->slug = $trove->generateSlug();
            }



        });
    }

    /**
     * The PUBLICATION axis of this working row (orthogonal to reviewState()). Derived from
     * published_at / published_id ALONE — no review information, no cross-axis precedence.
     * A shadow draft of a live row (published_id set) is PendingChanges regardless of
     * whether a review is outstanding on it.
     */
    protected function publicationState(): Attribute
    {
        return Attribute::get(function (): PublicationState {
            if ($this->published_id !== null) {
                return PublicationState::PendingChanges;
            }

            if ($this->published_at !== null) {
                return PublicationState::Published;
            }

            return PublicationState::Draft;
        });
    }

    /**
     * DB-side mirror of publicationState(): filters to rows resolving to any of the given
     * PublicationState cases. Single-axis — each case maps to a plain predicate on
     * published_id / published_at, with no cross-axis (review) guards. Kept next to
     * publicationState() on purpose — edit both together, or they drift. See
     * docs/plans/trove-review-status-parity-test.md for the parity test that locks this.
     */
    public function scopeWithPublicationState(Builder $query, PublicationState ...$states): Builder
    {
        $predicate = [
            PublicationState::PendingChanges->value => fn (Builder $q) => $q
                ->whereNotNull('published_id'),
            PublicationState::Published->value => fn (Builder $q) => $q
                ->whereNull('published_id')
                ->whereNotNull('published_at'),
            PublicationState::Draft->value => fn (Builder $q) => $q
                ->whereNull('published_id')
                ->whereNull('published_at'),
        ];

        return $query->where(function (Builder $q) use ($states, $predicate) {
            foreach ($states as $state) {
                $q->orWhere($predicate[$state->value]);
            }
        });
    }

    /**
     * The REVIEW axis of this working row (orthogonal to publicationState()). Derived from
     * the review columns ALONE. reviewed_at wins over a lingering review_requested_at
     * (precedence WITHIN this axis only — completeReview()/requestReview() keep them
     * consistent anyway).
     */
    protected function reviewState(): Attribute
    {
        return Attribute::get(function (): ReviewState {
            if ($this->reviewed_at !== null) {
                return ReviewState::Reviewed;
            }

            if ($this->review_requested_at !== null) {
                return ReviewState::InReview;
            }

            return ReviewState::None;
        });
    }

    /**
     * DB-side mirror of reviewState(): filters to rows resolving to any of the given
     * ReviewState cases. Single-axis — reviewed_at decides Reviewed vs the rest, then
     * review_requested_at decides InReview vs None. No cross-axis (publication) guards.
     * Kept next to reviewState() on purpose — edit both together, or they drift.
     */
    public function scopeWithReviewState(Builder $query, ReviewState ...$states): Builder
    {
        $predicate = [
            ReviewState::Reviewed->value => fn (Builder $q) => $q
                ->whereNotNull('reviewed_at'),
            ReviewState::InReview->value => fn (Builder $q) => $q
                ->whereNull('reviewed_at')
                ->whereNotNull('review_requested_at'),
            ReviewState::None->value => fn (Builder $q) => $q
                ->whereNull('reviewed_at')
                ->whereNull('review_requested_at'),
        ];

        return $query->where(function (Builder $q) use ($states, $predicate) {
            foreach ($states as $state) {
                $q->orWhere($predicate[$state->value]);
            }
        });
    }

    /**
     * Published state is derived from published_at (there is no is_published column).
     * Read-only: to publish/unpublish, set published_at via App\Services\TrovePublisher.
     */
    protected function isPublished(): Attribute
    {
        return Attribute::get(fn () => $this->published_at !== null);
    }


    /** @return string[] relation names copied between a canonical row and its shadow draft */
    public function getDraftableRelations(): array
    {
        return $this->draftableRelations;
    }

    /** The single shadow draft holding pending edits to this (canonical) row, if any. */
    public function draft(): HasOne
    {
        return $this->hasOne(Trove::class, 'published_id')
            ->withoutGlobalScope(PublishedScope::class);
    }

    /** For a shadow draft, the canonical published row it belongs to. */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(Trove::class, 'published_id')
            ->withoutGlobalScope(PublishedScope::class);
    }

    /** Include shadow drafts and never-published canonicals (admin/preview opt-out of R1). */
    public function scopeWithDrafts(Builder $query): Builder
    {
        return $query->withoutGlobalScope(PublishedScope::class);
    }

    /**
     * Exactly one editable row per logical Trove: the shadow draft when one exists,
     * otherwise the canonical row.
     */
    public function scopeWorkingVersions(Builder $query): Builder
    {
        return $query->withoutGlobalScope(PublishedScope::class)
            ->where(fn (Builder $q) => $q
                ->whereNotNull('published_id')
                ->orWhereDoesntHave('draft'));
    }

    /** The personal queue: working rows with an outstanding review assigned to $userId. */
    public function scopeAwaitingReviewBy(Builder $query, int $userId): Builder
    {
        return $query->workingVersions()
            ->whereNotNull('review_requested_at')
            ->whereNull('reviewed_at')
            ->where('reviewer_id', $userId);
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

    /**
     * The reviewer: while a review is outstanding this is the assigned person; once the
     * review is completed it is whoever ACTUALLY reviewed + approved (see TrovePublisher).
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function troveType(): BelongsTo
    {
        return $this->belongsTo(TroveType::class);
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

    /**
     * Whether a published version of this logical Trove exists — true for a live
     * canonical row, and for a shadow draft (whose canonical is by definition live).
     * Drives the "Publish" vs "Publish changes" button label.
     */
    public function hasPublishedVersion(): Attribute
    {
        return Attribute::get(fn () => $this->published_id !== null || $this->is_published);
    }

    /** Only published canonical rows are indexed; shadow drafts never pollute search. */
    public function shouldBeSearchable(): bool
    {
        return $this->published_at !== null && $this->published_id === null;
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
        // The PublishedScope global scope already restricts to published canonical rows
        // (published_at not null); whereNull('published_id') is an explicit belt-and-braces
        // guard that we only ever resolve a canonical row, never a shadow draft.

        // Try slug
        $trove = self::where('slug', $troveKey)
            ->whereNull('published_id')
            ->first();
        if ($trove) {
            return $trove;
        }

        // Try id
        if (is_numeric($troveKey)) {
            $trove = self::where('id', (int) $troveKey)
                ->whereNull('published_id')
                ->first();
            if ($trove) {
                return $trove;
            }
        }

        // Try previous_slugs (string)
        $trove = self::whereJsonContains('previous_slugs', (string) $troveKey)
            ->whereNull('published_id')
            ->first();
        if ($trove) {
            return $trove;
        }

        // Try previous_slugs (numeric)
        if (is_numeric($troveKey)) {
            $trove = self::whereJsonContains('previous_slugs', (int) $troveKey)
                ->whereNull('published_id')
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

    public function generateSlug(): string
    {

        // never update the slug of a published version
        if($this->slug !== null && $this->has_published_version) {
            return $this->slug;
        }

        // set the slug to the first available title locale
        $locales = $this->getTranslatedLocales('title');

        $slug = Str::slug($this->getTranslation('title', $locales[0]));

        // check for uniqueness and append a number if necessary
        $uniquenessQuery = $this::withTrashed()
            ->withDrafts()
            ->where('slug', $slug);

        if ($this->id) {
            $uniquenessQuery = $uniquenessQuery->where('id', '!=', $this->id);
        }

        $count = $uniquenessQuery->count();

        if ($count > 0) {
            $slug = $slug.'-'.$count;
        }

        return $slug;
    }
}
