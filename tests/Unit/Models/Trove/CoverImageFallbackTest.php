<?php

use App\Models\Collection;
use App\Models\Trove;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    // Register the es/fr media collections + conversions too.
    config(['app.locales' => ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French']]);
    config(['branding.locales' => ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French']]);
    app()->setLocale('en');
});

function attachCover(Trove|Collection $model, string $locale): void
{
    $model->addMedia(UploadedFile::fake()->image("cover-{$locale}.jpg", 600, 400))
        ->toMediaCollection("cover_image_{$locale}");
}

it('falls back through configured locales for Collection::coverImage', function () {
    $collection = Collection::factory()->create();
    // Only Spanish has a cover; current locale is English.
    attachCover($collection, 'es');

    $expected = $collection->fresh()->getFirstMedia('cover_image_es')->getFullUrl();

    expect($collection->fresh()->coverImage)->toBe($expected);
});

it('returns the default cover when a Collection has no media', function () {
    $collection = Collection::factory()->create();

    expect($collection->coverImage)->toContain('default-cover-photo.jpg');
});

it('prefers the current locale first for coverImageThumb', function () {
    app()->setLocale('es');
    $collection = Collection::factory()->create();
    attachCover($collection, 'en');
    attachCover($collection, 'es');

    // Current locale (es) is checked first, so its thumb wins.
    $esThumb = $collection->fresh()->getFirstMediaUrl('cover_image_es', 'cover_thumb');

    expect($esThumb)->not->toBe('')
        ->and($collection->fresh()->coverImageThumb)->toBe($esThumb);
});

it('falls back to another locale thumb when the current locale has none', function () {
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()->create());
    attachCover($trove, 'fr'); // current locale en has none

    $frThumb = $trove->fresh()->getFirstMediaUrl('cover_image_fr', 'cover_thumb');

    expect($frThumb)->not->toBe('')
        ->and($trove->fresh()->coverImageThumb)->toBe($frThumb);
});

it('returns the default thumb when there is no cover media at all', function () {
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()->create());

    expect($trove->coverImageThumb)->toContain('default-cover-photo.jpg');
});

// Documented quirk (flagged for follow-up): getCoverImageUrl() hardcodes ['en','es','fr']
// (plus the current locale) instead of using config('branding.locales'). So a cover held
// in another configured locale that is NOT the current one is invisible to it — even
// though the config-driven coverImage accessor finds it fine.
it('getCoverImageUrl ignores a configured non-current locale outside its hardcoded en/es/fr list', function () {
    config(['app.locales' => ['en' => 'English', 'de' => 'German']]);
    config(['branding.locales' => ['en' => 'English', 'de' => 'German']]);
    app()->setLocale('en'); // current locale is en; the only cover is in de

    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()->create());
    attachCover($trove, 'de');
    $trove = $trove->fresh();

    // The German cover exists...
    expect($trove->getMedia('cover_image_de'))->toHaveCount(1)
        // ...but getCoverImageUrl only walks [current=en, en, es, fr], so it misses it.
        ->and($trove->getCoverImageUrl())->toContain('default-cover-photo.jpg');
});
