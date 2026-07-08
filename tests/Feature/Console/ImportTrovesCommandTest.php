<?php

use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;

/**
 * troves:import — CSV bulk import (see docs/import/README.md).
 *
 * Media downloads are not exercised here (they need a reachable URL); rows in these
 * fixtures simply omit cover_image_url.
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
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
            'embeddable' => true,
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
