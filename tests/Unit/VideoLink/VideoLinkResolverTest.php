<?php

use App\Contracts\ResolvesVideoLinks;
use App\Services\VideoLinkResolver;
use Illuminate\Support\Facades\Http;

it('is bound to the contract in the container', function () {
    expect(app(ResolvesVideoLinks::class))->toBeInstanceOf(VideoLinkResolver::class);
});

it('rejects unresolvable or unsafe urls without any http call', function (string $badUrl) {
    Http::fake();

    $result = app(ResolvesVideoLinks::class)->resolve($badUrl);

    expect($result->embeddable)->toBeFalse()
        ->and($result->provider)->toBeNull();
    Http::assertNothingSent();
})->with([
    'not a url at all',
    'ftp://example.org/video.mp4',
    'https://user:secret@example.org/video',
]);

it('routes youtube urls to the youtube adapter', function () {
    Http::fake(['https://www.youtube.com/oembed*' => Http::response(['title' => 'Yt'], 200)]);

    $result = app(ResolvesVideoLinks::class)->resolve('  https://youtu.be/q76bMs-NwRk  ');

    expect($result->provider)->toBe('youtube')
        ->and($result->embeddable)->toBeTrue()
        ->and($result->url)->toBe('https://youtu.be/q76bMs-NwRk');
});

it('routes ecoagtube urls to the ecoagtube adapter', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response('<html></html>', 200)]);

    $result = app(ResolvesVideoLinks::class)->resolve('https://www.ecoagtube.org/content/some-video');

    expect($result->provider)->toBe('ecoagtube');
});

it('returns a plain-link result when an adapter throws unexpectedly', function () {
    Http::fake(fn () => throw new RuntimeException('boom'));

    $result = app(ResolvesVideoLinks::class)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeFalse()
        ->and($result->url)->toBe('https://youtu.be/q76bMs-NwRk');
});
