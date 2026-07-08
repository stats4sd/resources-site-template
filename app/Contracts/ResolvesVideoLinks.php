<?php

namespace App\Contracts;

use App\Support\VideoLink\VideoLinkResult;

interface ResolvesVideoLinks
{
    public function resolve(string $url): VideoLinkResult;
}
