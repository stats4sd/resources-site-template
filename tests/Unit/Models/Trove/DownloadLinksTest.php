<?php

use App\Models\Trove;

// getDownloadableLinks()/buildLinksManifest() are protected and purely translate the
// external_links / youtube_links JSON — no DB or disk needed. Bind a closure to reach them.
function downloadableLinks(Trove $trove, string $locale = 'en'): \Illuminate\Support\Collection
{
    return (fn () => $this->getDownloadableLinks($locale))->call($trove);
}

function makeTroveWithLinks(array $attributes): Trove
{
    $trove = new Trove;
    foreach ($attributes as $key => $value) {
        $trove->{$key} = $value;
    }

    return $trove;
}

it('normalises a single external link object into a one-item list', function () {
    $trove = makeTroveWithLinks([
        'external_links' => ['en' => ['link_url' => 'https://example.org', 'link_title' => 'Example']],
    ]);

    expect(downloadableLinks($trove)->all())->toBe([
        ['title' => 'Example', 'url' => 'https://example.org'],
    ]);
});

it('keeps an array of external links and filters out incomplete ones', function () {
    $trove = makeTroveWithLinks([
        'external_links' => ['en' => [
            ['link_url' => 'https://a.test', 'link_title' => 'A'],
            ['link_url' => '', 'link_title' => 'Missing URL'],
            ['link_url' => 'https://c.test', 'link_title' => ''],
            ['link_url' => 'https://d.test', 'link_title' => 'D'],
        ]],
    ]);

    expect(downloadableLinks($trove)->all())->toBe([
        ['title' => 'A', 'url' => 'https://a.test'],
        ['title' => 'D', 'url' => 'https://d.test'],
    ]);
});

it('builds youtube watch URLs from single and array forms', function () {
    $single = makeTroveWithLinks(['youtube_links' => ['en' => ['youtube_id' => 'abc123']]]);
    $many = makeTroveWithLinks(['youtube_links' => ['en' => [
        ['youtube_id' => 'abc123'],
        ['youtube_id' => 'def456'],
    ]]]);

    expect(downloadableLinks($single)->all())->toBe([
        ['title' => 'YouTube video', 'url' => 'https://www.youtube.com/watch?v=abc123'],
    ])->and(downloadableLinks($many)->pluck('url')->all())->toBe([
        'https://www.youtube.com/watch?v=abc123',
        'https://www.youtube.com/watch?v=def456',
    ]);
});

it('returns an empty collection when there is nothing to download', function () {
    expect(downloadableLinks(new Trove)->all())->toBe([]);
});

it('builds a links manifest joining title and url', function () {
    $trove = new Trove;
    $links = collect([
        ['title' => 'A', 'url' => 'https://a.test'],
        ['title' => 'B', 'url' => 'https://b.test'],
    ]);

    $manifest = (fn () => $this->buildLinksManifest($links))->call($trove);

    expect($manifest)->toBe("A\nhttps://a.test\n\nB\nhttps://b.test");
});
