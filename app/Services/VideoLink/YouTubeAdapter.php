<?php

namespace App\Services\VideoLink;

use App\Support\VideoLink\VideoLinkResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class YouTubeAdapter
{
    public static function extractId(string $url): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }

        foreach ([
            '/youtube\.com\/watch\?.*v=([A-Za-z0-9_-]{11})/',
            '/youtu\.be\/([A-Za-z0-9_-]{11})/',
            '/youtube(?:-nocookie)?\.com\/embed\/([A-Za-z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([A-Za-z0-9_-]{11})/',
            '/youtube\.com\/live\/([A-Za-z0-9_-]{11})/',
        ] as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function matches(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $bareHost = preg_replace('/^(www|m)\./', '', $host);

        return in_array($bareHost, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true);
    }

    public function resolve(string $url): VideoLinkResult
    {
        $videoId = self::extractId($url);

        if ($videoId === null) {
            return new VideoLinkResult(url: $url, provider: 'youtube', resolvedUrl: $url);
        }

        $watchUrl = "https://www.youtube.com/watch?v={$videoId}";

        try {
            $response = Http::timeout(5)->get('https://www.youtube.com/oembed', [
                'url' => $watchUrl,
                'format' => 'json',
            ]);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, provider: 'youtube', resolvedUrl: $url);
        }

        if (! $response->successful()) {
            return new VideoLinkResult(url: $url, provider: 'youtube', resolvedUrl: $url);
        }

        return new VideoLinkResult(
            url: $url,
            provider: 'youtube',
            embedUrl: "https://www.youtube.com/embed/{$videoId}",
            embeddable: true,
            title: $response->json('title'),
            resolvedUrl: $url,
        );
    }
}
