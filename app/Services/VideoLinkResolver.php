<?php

namespace App\Services;

use App\Contracts\ResolvesVideoLinks;
use App\Services\VideoLink\EcoAgTubeAdapter;
use App\Services\VideoLink\GenericVideoAdapter;
use App\Services\VideoLink\YouTubeAdapter;
use App\Support\VideoLink\VideoLinkResult;
use Throwable;

class VideoLinkResolver implements ResolvesVideoLinks
{
    public function __construct(
        private YouTubeAdapter $youTube,
        private EcoAgTubeAdapter $ecoAgTube,
        private GenericVideoAdapter $generic,
    ) {}

    public function resolve(string $url): VideoLinkResult
    {
        $url = trim($url);

        if (! $this->isResolvableUrl($url)) {
            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }

        try {
            if ($this->youTube->matches($url)) {
                return $this->youTube->resolve($url);
            }

            if ($this->ecoAgTube->matches($url)) {
                return $this->ecoAgTube->resolve($url);
            }

            return $this->generic->resolve($url);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }
    }

    private function isResolvableUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user'])) {
            return false;
        }

        if (isset($parts['pass'])) {
            return false;
        }

        return true;
    }
}
