<?php

namespace App\Services\VideoLink;

use App\Support\VideoLink\VideoLinkResult;
use Embed\Embed;
use Throwable;

class GenericVideoAdapter
{
    public function __construct(private Embed $embed) {}

    public function resolve(string $url): VideoLinkResult
    {
        try {
            $info = $this->embed->get($url);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }

        $provider = strtolower((string) $info->providerName) ?: null;
        $title = $info->title;
        $embedUrl = $this->extractIframeSrc((string) $info->code?->html);

        if ($embedUrl === null) {
            return new VideoLinkResult(url: $url, provider: $provider, title: $title, resolvedUrl: $url);
        }

        return new VideoLinkResult(
            url: $url,
            provider: $provider,
            embedUrl: $embedUrl,
            embeddable: true,
            title: $title,
            resolvedUrl: $url,
        );
    }

    private function extractIframeSrc(string $embedHtml): ?string
    {
        if (! preg_match('/<iframe[^>]+src="(https:\/\/[^"]+)"/i', $embedHtml, $matches)) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }
}
