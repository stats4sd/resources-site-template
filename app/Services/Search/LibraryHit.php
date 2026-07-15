<?php

namespace App\Services\Search;

final readonly class LibraryHit
{
    /**
     * @param  'trove'|'collection'  $type
     */
    public function __construct(
        public string $type,
        public int $id,
        public float $score,
    ) {}
}
