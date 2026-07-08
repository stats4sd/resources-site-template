<?php

use App\Contracts\ResolvesVideoLinks;
use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;
use App\Support\VideoLink\VideoLinkResult;

/**
 * troves:import — CSV bulk import (see docs/import/README.md).
 *
 * Successful media downloads are not exercised here (they need a reachable URL); most
 * fixtures simply omit cover_image_url. The failure path is exercised against a
 * fast-refusing local address (127.0.0.1:1) instead of a real download.
 */

/** Write a CSV (header + rows) to a temp file and return its path. */
function importCsv(array $header, array ...$rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'trove-import-').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, $header);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $path;
}

/** Bind a spying fake resolver: ecoagtube URLs resolve embeddable, everything else link-only. */
function fakeVideoResolver(): ResolvesVideoLinks
{
    $fake = new class implements ResolvesVideoLinks
    {
        public array $resolvedUrls = [];

        public function resolve(string $url): VideoLinkResult
        {
            $this->resolvedUrls[] = $url;

            if (str_contains($url, 'ecoagtube')) {
                return new VideoLinkResult(
                    url: $url,
                    provider: 'ecoagtube',
                    embedUrl: 'https://www.ecoagtube.org/embed/32021',
                    embeddable: true,
                    title: 'EcoAgTube video',
                    resolvedUrl: $url,
                );
            }

            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }
    };

    app()->instance(ResolvesVideoLinks::class, $fake);

    return $fake;
}

beforeEach(function () {
    config(['branding.locales' => ['en' => 'English', 'fr' => 'Français'], 'app.locales' => ['en' => 'English', 'fr' => 'Français']]);

    $this->uploader = User::factory()->create(['email' => 'importer@example.com']);
    $this->topics = TagType::create([
        'slug' => 'topics',
        'label' => ['en' => 'Topics'],
        'description' => ['en' => ''],
        'freetext' => false,
    ]);
    TroveType::create(['label' => ['en' => 'Video', 'fr' => 'Vidéo']]);
});

it('imports troves with tags and collections from a csv', function () {
    $existingTag = $this->topics->tags()->create(['name' => ['en' => 'Composting']]);

    $path = importCsv(
        ['title:en', 'title:fr', 'description:en', 'trove_type', 'creation_date', 'link_url', 'link_title', 'collections', 'tag:topics'],
        ['Compost basics', 'Les bases du compost', 'How to compost.', 'Video', '2023-05-14', 'https://www.ecoagtube.org/w/abc', 'Watch on EcoAgtube', 'Soil Health', 'composting|Soil fertility'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();
    expect($trove->getTranslation('title', 'en'))->toBe('Compost basics')
        ->and($trove->getTranslation('title', 'fr'))->toBe('Les bases du compost')
        ->and($trove->getTranslation('description', 'en'))->toBe('How to compost.')
        ->and($trove->troveType->getTranslation('label', 'en'))->toBe('Video')
        ->and($trove->creation_date->toDateString())->toBe('2023-05-14')
        ->and($trove->uploader_id)->toBe($this->uploader->id)
        ->and($trove->getTranslation('external_links', 'en'))->toBe([['link_url' => 'https://www.ecoagtube.org/w/abc', 'link_title' => 'Watch on EcoAgtube']])
        ->and($trove->published_at)->toBeNull();

    // "composting" reuses the existing tag (case-insensitive); "Soil fertility" is created.
    expect($trove->tags)->toHaveCount(2)
        ->and($trove->tags->pluck('id'))->toContain($existingTag->id)
        ->and(Tag::count())->toBe(2);

    $collection = Collection::firstOrFail();
    expect($collection->getTranslation('title', 'en'))->toBe('Soil Health')
        ->and($collection->public)->toBeTrue()
        ->and($trove->collections->pluck('id'))->toContain($collection->id);
});

it('publishes imported troves with --publish', function () {
    fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'youtube_url'],
        ['A video', 'https://www.youtube.com/watch?v=q76bMs-NwRk'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com', '--publish' => true])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();
    expect($trove->published_at)->not->toBeNull()
        ->and($trove->getTranslation('video_links', 'en'))->toBe([[
            'url' => 'https://www.youtube.com/watch?v=q76bMs-NwRk',
            'provider' => null,
            'embed_url' => null,
            'embeddable' => false,
            'title' => null,
            'resolved_url' => 'https://www.youtube.com/watch?v=q76bMs-NwRk',
        ]]);
});

it('writes nothing on --dry-run', function () {
    $path = importCsv(
        ['title:en', 'collections', 'tag:topics'],
        ['A trove', 'New Collection', 'New Tag'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com', '--dry-run' => true])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(0)
        ->and(Collection::count())->toBe(0)
        ->and(Tag::count())->toBe(0);
});

it('skips rows whose source url already exists', function () {
    publishedTrove([
        'uploader_id' => $this->uploader->id,
        'external_links' => ['en' => [['link_url' => 'https://www.ecoagtube.org/w/abc', 'link_title' => 'Existing']]],
    ]);

    $path = importCsv(
        ['title:en', 'link_url'],
        ['Duplicate of existing', 'https://www.ecoagtube.org/w/abc'],
        ['Fresh trove', 'https://www.ecoagtube.org/w/xyz'],
        ['Duplicate within file', 'https://www.ecoagtube.org/w/xyz'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(2); // the pre-existing trove + "Fresh trove"
    expect(Trove::withDrafts()->get()->map(fn ($t) => $t->getTranslation('title', 'en')))
        ->toContain('Fresh trove');
});

it('rejects a column defined both flat and with locale suffixes', function () {
    $path = importCsv(
        ['title:en', 'link_url', 'link_url:fr'],
        ['A trove', 'https://example.org/a', 'https://example.org/a-fr'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->expectsOutputToContain('is defined both as a flat column and with locale suffixes')
        ->assertExitCode(1);

    expect(Trove::withDrafts()->count())->toBe(0);
});

it('imports per-locale link_url and link_title columns', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_url:fr', 'link_title:en', 'link_title:fr'],
        ['Compost basics', 'Les bases du compost', 'https://example.org/en', 'https://example.org/fr', 'Read more', 'En savoir plus'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();
    expect($trove->getTranslation('external_links', 'en'))->toBe([['link_url' => 'https://example.org/en', 'link_title' => 'Read more']])
        ->and($trove->getTranslation('external_links', 'fr'))->toBe([['link_url' => 'https://example.org/fr', 'link_title' => 'En savoir plus']]);
});

it('defaults link_title to "View resource" per locale when omitted', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_url:fr'],
        ['A trove', 'Un trove', 'https://example.org/en', 'https://example.org/fr'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();
    expect($trove->getTranslation('external_links', 'fr'))->toBe([['link_url' => 'https://example.org/fr', 'link_title' => 'View resource']]);
});

it('errors when link_title has no matching link_url for that locale', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_title:en', 'link_title:fr'],
        ['A trove', 'Un trove', 'https://example.org/en', 'Read more', 'En savoir plus'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->expectsOutputToContain('link_title has no matching link_url for locale "fr"')
        ->assertExitCode(1);

    expect(Trove::withDrafts()->count())->toBe(0);
});

it('dedupes rows whose link_url matches an already-imported locale-specific link', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_url:fr'],
        ['First trove', 'Premier trove', 'https://example.org/shared', 'https://example.org/fr-only'],
        ['Second trove', 'Deuxieme trove', 'https://example.org/other', 'https://example.org/shared'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});

it('aborts on unknown tag type slugs unless --create-tag-types is passed', function () {
    $path = importCsv(
        ['title:en', 'tag:formats'],
        ['A trove', 'Podcast'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(1);
    expect(Trove::withDrafts()->count())->toBe(0);

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com', '--create-tag-types' => true])
        ->assertExitCode(0);

    $tagType = TagType::where('slug', 'formats')->firstOrFail();
    expect($tagType->getTranslation('label', 'en'))->toBe('Formats')
        ->and($tagType->tags()->count())->toBe(1);
});

it('aborts without importing anything when any row is invalid', function () {
    $path = importCsv(
        ['title:en', 'trove_type', 'creation_date'],
        ['Valid row', 'Video', '2023-01-01'],
        ['', 'Video', '2023-01-01'],                    // no title
        ['Bad type', 'Hologram', '2023-01-01'],        // unknown trove type
        ['Bad date', 'Video', 'not-a-date'],           // unparseable date
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(1);

    expect(Trove::withDrafts()->count())->toBe(0);
});

it('rejects unrecognised and misconfigured header columns', function () {
    $path = importCsv(
        ['title:en', 'titel:en'],
        ['A trove', 'typo'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(1);

    $path = importCsv(
        ['title:de'],
        ['Unconfigured locale'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(1);

    expect(Trove::withDrafts()->count())->toBe(0);
});

it('fails cleanly on a missing uploader or unreadable file', function () {
    $path = importCsv(['title:en'], ['A trove']);

    $this->artisan('troves:import', ['file' => $path])
        ->assertExitCode(1);

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'nobody@example.com'])
        ->assertExitCode(1);

    $this->artisan('troves:import', ['file' => '/nonexistent.csv', '--uploader' => 'importer@example.com'])
        ->assertExitCode(1);
});

it('imports a video_url row resolved through the video link resolver', function () {
    $resolver = fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'video_url'],
        ['Eco video', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();

    expect($resolver->resolvedUrls)->toBe(['https://www.ecoagtube.org/content/biofertilizer-formulation-1'])
        ->and($trove->getTranslation('video_links', 'en'))->toBe([[
            'url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
            'provider' => 'ecoagtube',
            'embed_url' => 'https://www.ecoagtube.org/embed/32021',
            'embeddable' => true,
            'title' => 'EcoAgTube video',
            'resolved_url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
        ]]);
});

it('skips duplicate video urls within a file and against the database', function () {
    fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'video_url'],
        ['Eco video', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
        ['Eco video again', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});

it('does not resolve video urls during a dry run', function () {
    $resolver = fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'video_url'],
        ['Eco video', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com', '--dry-run' => true])
        ->assertExitCode(0);

    expect($resolver->resolvedUrls)->toBe([])
        ->and(Trove::withDrafts()->count())->toBe(0);
});

it('resolves video_url per locale', function () {
    $resolver = fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'title:fr', 'video_url:en', 'video_url:fr'],
        ['A video', 'Une vidéo', 'https://www.youtube.com/watch?v=q76bMs-NwRk', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();

    expect($resolver->resolvedUrls)->toBe([
        'https://www.youtube.com/watch?v=q76bMs-NwRk',
        'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
    ])
        ->and($trove->getTranslation('video_links', 'en')[0]['url'])->toBe('https://www.youtube.com/watch?v=q76bMs-NwRk')
        ->and($trove->getTranslation('video_links', 'fr')[0]['provider'])->toBe('ecoagtube');
});

it('dedupes rows whose video_url matches an already-imported locale-specific video', function () {
    fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'title:fr', 'video_url:en', 'video_url:fr'],
        ['First', 'Premier', 'https://www.youtube.com/watch?v=q76bMs-NwRk', 'https://www.ecoagtube.org/content/x'],
        ['Second', 'Deuxieme', 'https://www.ecoagtube.org/content/x', 'https://www.youtube.com/watch?v=xNN7iTA57jM'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});

it('downloads cover images per locale and warns independently on failure', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'cover_image_url:en', 'cover_image_url:fr'],
        ['A trove', 'Un trove', 'http://127.0.0.1:1/en.jpg', 'http://127.0.0.1:1/fr.jpg'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->expectsOutputToContain('cover image download failed for locale "en"')
        ->expectsOutputToContain('cover image download failed for locale "fr"')
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});
