<?php

use App\Services\VideoLink\EcoAgTubeAdapter;
use App\Services\VideoLink\YouTubeAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function ecoAgTubeAdapter(): EcoAgTubeAdapter
{
    return new EcoAgTubeAdapter(new YouTubeAdapter);
}

function ecoAgTubePage(string $body): string
{
    return "<html><head><meta property=\"og:title\" content=\"Biofertilizer formulation\" /></head><body>{$body}</body></html>";
}

it('matches ecoagtube.org urls only', function () {
    expect(ecoAgTubeAdapter()->matches('https://www.ecoagtube.org/content/biofertilizer-formulation-1'))->toBeTrue()
        ->and(ecoAgTubeAdapter()->matches('https://ecoagtube.org/content/some-video'))->toBeTrue()
        ->and(ecoAgTubeAdapter()->matches('https://www.youtube.com/watch?v=q76bMs-NwRk'))->toBeFalse();
});

it('resolves a natively-hosted video from the embed modal iframe', function () {
    Http::fake([
        'https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(
            '<textarea id="embed-share-video"><iframe width="560" height="315" src="https://www.ecoagtube.org/embed/32021"></iframe></textarea>'
        )),
    ]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/biofertilizer-formulation-1');

    expect($result->embeddable)->toBeTrue()
        ->and($result->provider)->toBe('ecoagtube')
        ->and($result->embedUrl)->toBe('https://www.ecoagtube.org/embed/32021')
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('sends a browser user agent when fetching the page', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(''))]);

    ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/some-video');

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla/5.0'));
});

it('delegates youtube-backed videos to the youtube oembed probe', function () {
    Http::fake([
        'https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(
            '<iframe src="https://www.youtube.com/embed/q76bMs-NwRk?rel=0"></iframe>'
        )),
        'https://www.youtube.com/oembed*' => Http::response(['title' => 'YouTube title'], 200),
    ]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/green-tv-live');

    expect($result->embeddable)->toBeTrue()
        ->and($result->provider)->toBe('ecoagtube')
        ->and($result->embedUrl)->toBe('https://www.youtube.com/embed/q76bMs-NwRk')
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('marks youtube-backed videos not embeddable when the oembed probe fails', function () {
    Http::fake([
        'https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(
            '<iframe src="https://www.youtube.com/embed/q76bMs-NwRk"></iframe>'
        )),
        'https://www.youtube.com/oembed*' => Http::response('', 401),
    ]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/green-tv-live');

    expect($result->embeddable)->toBeFalse()
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('falls back to a titled link when the page has no embed iframe', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage('<p>no embed here</p>'))]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/restricted-video');

    expect($result->embeddable)->toBeFalse()
        ->and($result->provider)->toBe('ecoagtube')
        ->and($result->embedUrl)->toBeNull()
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('falls back to a plain link on http errors or connection failures', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response('Forbidden', 403)]);

    expect(ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/x')->embeddable)->toBeFalse();

    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/x')->embeddable)->toBeFalse();
});

it('extracts the og:title regardless of attribute order', function () {
    $page = '<html><head><meta content="Reversed title" property="og:title" /></head><body><p>no embed</p></body></html>';
    Http::fake(['https://www.ecoagtube.org/*' => Http::response($page)]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/reversed');

    expect($result->title)->toBe('Reversed title');
});
