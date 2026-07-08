<?php

use App\Services\VideoLink\YouTubeAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('extracts the video id from all common url forms and bare ids', function (string $input) {
    expect(YouTubeAdapter::extractId($input))->toBe('q76bMs-NwRk');
})->with([
    'q76bMs-NwRk',
    'https://www.youtube.com/watch?v=q76bMs-NwRk',
    'https://www.youtube.com/watch?feature=shared&v=q76bMs-NwRk',
    'https://youtu.be/q76bMs-NwRk',
    'https://youtu.be/q76bMs-NwRk?si=abc',
    'https://www.youtube.com/embed/q76bMs-NwRk',
    'https://www.youtube-nocookie.com/embed/q76bMs-NwRk',
    'https://www.youtube.com/shorts/q76bMs-NwRk',
    'https://www.youtube.com/live/q76bMs-NwRk',
]);

it('returns null for urls without an extractable id', function () {
    expect(YouTubeAdapter::extractId('https://www.youtube.com/@somechannel'))->toBeNull()
        ->and(YouTubeAdapter::extractId('https://example.org/watch?v=q76bMs-NwRk'))->toBeNull();
});

it('matches youtube hosts only', function () {
    $adapter = new YouTubeAdapter;

    expect($adapter->matches('https://www.youtube.com/watch?v=q76bMs-NwRk'))->toBeTrue()
        ->and($adapter->matches('https://youtu.be/q76bMs-NwRk'))->toBeTrue()
        ->and($adapter->matches('https://m.youtube.com/watch?v=q76bMs-NwRk'))->toBeTrue()
        ->and($adapter->matches('https://www.ecoagtube.org/content/some-video'))->toBeFalse()
        ->and($adapter->matches('https://vimeo.com/12345'))->toBeFalse();
});

it('resolves an embeddable video via the oembed probe', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(['title' => 'Making a seedbed'], 200),
    ]);

    $result = (new YouTubeAdapter)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeTrue()
        ->and($result->provider)->toBe('youtube')
        ->and($result->embedUrl)->toBe('https://www.youtube.com/embed/q76bMs-NwRk')
        ->and($result->title)->toBe('Making a seedbed')
        ->and($result->url)->toBe('https://youtu.be/q76bMs-NwRk')
        ->and($result->resolvedUrl)->toBe('https://youtu.be/q76bMs-NwRk');
});

it('marks embed-disabled or missing videos as not embeddable', function (int $status) {
    Http::fake(['https://www.youtube.com/oembed*' => Http::response('', $status)]);

    $result = (new YouTubeAdapter)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeFalse()
        ->and($result->embedUrl)->toBeNull()
        ->and($result->provider)->toBe('youtube');
})->with([400, 401, 403]);

it('marks the video as not embeddable when the probe cannot connect', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $result = (new YouTubeAdapter)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeFalse();
});

it('returns a not-embeddable result for youtube urls without an id', function () {
    Http::fake();

    $result = (new YouTubeAdapter)->resolve('https://www.youtube.com/@somechannel');

    expect($result->embeddable)->toBeFalse()
        ->and($result->provider)->toBe('youtube');
    Http::assertNothingSent();
});
