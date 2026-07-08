<?php

namespace App\Services\VideoLink;

use App\Support\VideoLink\VideoLinkResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class EcoAgTubeAdapter
{
    public function __construct(private YouTubeAdapter $youTube) {}

    /**
     * EcoAgTube (and the Embed fallback client) sit behind CDNs that 403 non-browser
     * user agents, so all page fetches identify as a real browser.
     */
    public static function browserUserAgent(): string
    {
        return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
    }

    public function matches(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host) === 'ecoagtube.org';
    }

    public function resolve(string $url): VideoLinkResult
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::browserUserAgent()])
                ->timeout(5)
                ->get($url);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, provider: 'ecoagtube', resolvedUrl: $url);
        }

        if (! $response->successful()) {
            return new VideoLinkResult(url: $url, provider: 'ecoagtube', resolvedUrl: $url);
        }

        $html = $response->body();
        $title = $this->extractOgTitle($html);

        if (preg_match('/<iframe[^>]+src="(https:\/\/(?:www\.)?ecoagtube\.org\/embed\/\d+)"/i', $html, $matches)) {
            return new VideoLinkResult(
                url: $url,
                provider: 'ecoagtube',
                embedUrl: $matches[1],
                embeddable: true,
                title: $title,
                resolvedUrl: $url,
            );
        }

        if (preg_match('/<iframe[^>]+src="(https:\/\/www\.youtube(?:-nocookie)?\.com\/embed\/[A-Za-z0-9_-]{11})/i', $html, $matches)) {
            $youTubeResult = $this->youTube->resolve($matches[1]);

            return new VideoLinkResult(
                url: $url,
                provider: 'ecoagtube',
                embedUrl: $youTubeResult->embeddable ? $youTubeResult->embedUrl : null,
                embeddable: $youTubeResult->embeddable,
                title: $title ?? $youTubeResult->title,
                resolvedUrl: $url,
            );
        }

        return new VideoLinkResult(url: $url, provider: 'ecoagtube', title: $title, resolvedUrl: $url);
    }

    private function extractOgTitle(string $html): ?string
    {
        if (! preg_match('/<meta\s+property="og:title"\s+content="([^"]*)"/i', $html, $matches)) {
            return null;
        }

        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $title === '' ? null : $title;
    }
}
