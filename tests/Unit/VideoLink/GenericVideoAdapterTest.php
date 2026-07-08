<?php

use App\Services\VideoLink\GenericVideoAdapter;
use Embed\Embed;
use Embed\Http\Crawler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;

function genericAdapterWithResponses(Response ...$responses): GenericVideoAdapter
{
    $handler = HandlerStack::create(new MockHandler($responses));
    $guzzle = new GuzzleClient(['handler' => $handler]);

    return new GenericVideoAdapter(new Embed(new Crawler($guzzle, new RequestFactory, new UriFactory)));
}

it('resolves an embeddable video from an oembed-discoverable page', function () {
    $page = <<<'HTML'
    <html><head>
        <title>A Vimeo video</title>
        <link rel="alternate" type="application/json+oembed" href="https://example.org/oembed?url=x" title="oEmbed">
    </head><body></body></html>
    HTML;

    $oembed = json_encode([
        'type' => 'video',
        'version' => '1.0',
        'title' => 'A Vimeo video',
        'html' => '<iframe src="https://player.example.org/video/123" width="640" height="360"></iframe>',
    ]);

    $adapter = genericAdapterWithResponses(
        new Response(200, ['Content-Type' => 'text/html'], $page),
        new Response(200, ['Content-Type' => 'application/json'], $oembed),
    );

    $result = $adapter->resolve('https://example.org/videos/123');

    expect($result->embeddable)->toBeTrue()
        ->and($result->embedUrl)->toBe('https://player.example.org/video/123')
        ->and($result->title)->toBe('A Vimeo video');
});

it('falls back to a titled link for pages without any embed code', function () {
    $page = <<<'HTML'
    <html><head>
        <title>Crop rotation with legumes | Access Agriculture</title>
        <meta property="og:title" content="Crop rotation with legumes">
    </head><body>no embed here</body></html>
    HTML;

    $adapter = genericAdapterWithResponses(
        new Response(200, ['Content-Type' => 'text/html'], $page),
        new Response(200, ['Content-Type' => 'text/html'], $page),
    );

    $result = $adapter->resolve('https://www.accessagriculture.org/crop-rotation-legumes');

    expect($result->embeddable)->toBeFalse()
        ->and($result->embedUrl)->toBeNull()
        ->and($result->title)->toContain('Crop rotation');
});

it('rejects non-https iframe sources', function () {
    $page = <<<'HTML'
    <html><head>
        <link rel="alternate" type="application/json+oembed" href="https://example.org/oembed?url=x">
    </head><body></body></html>
    HTML;

    $oembed = json_encode([
        'type' => 'video',
        'version' => '1.0',
        'html' => '<iframe src="http://insecure.example.org/video/123"></iframe>',
    ]);

    $adapter = genericAdapterWithResponses(
        new Response(200, ['Content-Type' => 'text/html'], $page),
        new Response(200, ['Content-Type' => 'application/json'], $oembed),
    );

    expect($adapter->resolve('https://example.org/videos/123')->embeddable)->toBeFalse();
});

it('falls back to a plain link when the fetch fails entirely', function () {
    $adapter = genericAdapterWithResponses(new Response(403, [], 'Forbidden'));

    $result = $adapter->resolve('https://example.org/videos/123');

    expect($result->embeddable)->toBeFalse()
        ->and($result->url)->toBe('https://example.org/videos/123');
});
