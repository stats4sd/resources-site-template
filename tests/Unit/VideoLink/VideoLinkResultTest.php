<?php

use App\Support\VideoLink\VideoLinkResult;

it('serialises to the stored record shape with snake_case keys', function () {
    $result = new VideoLinkResult(
        url: 'https://youtu.be/q76bMs-NwRk',
        provider: 'youtube',
        embedUrl: 'https://www.youtube.com/embed/q76bMs-NwRk',
        embeddable: true,
        title: 'A video',
        resolvedUrl: 'https://youtu.be/q76bMs-NwRk',
    );

    expect($result->toArray())->toBe([
        'url' => 'https://youtu.be/q76bMs-NwRk',
        'provider' => 'youtube',
        'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
        'embeddable' => true,
        'title' => 'A video',
        'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
    ]);
});

it('defaults to a non-embeddable result with only a url', function () {
    $result = new VideoLinkResult(url: 'https://example.org/video');

    expect($result->toArray())->toBe([
        'url' => 'https://example.org/video',
        'provider' => null,
        'embed_url' => null,
        'embeddable' => false,
        'title' => null,
        'resolved_url' => null,
    ]);
});
