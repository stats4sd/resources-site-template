<?php

namespace App\Support\VideoLink;

final readonly class VideoLinkResult
{
    public function __construct(
        public string $url,
        public ?string $provider = null,
        public ?string $embedUrl = null,
        public bool $embeddable = false,
        public ?string $title = null,
        public ?string $resolvedUrl = null,
    ) {}

    /** @return array{
     *     url: string,
     *     provider: ?string,
     *     embed_url: ?string,
     *     embeddable: bool,
     *     title: ?string,
     *     resolved_url: ?string
     * } */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'provider' => $this->provider,
            'embed_url' => $this->embedUrl,
            'embeddable' => $this->embeddable,
            'title' => $this->title,
            'resolved_url' => $this->resolvedUrl,
        ];
    }
}
