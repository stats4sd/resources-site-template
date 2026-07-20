<?php

namespace App\Services\Search;

final readonly class LibraryFacets
{
    /**
     * @param  array<int, int>  $tagCounts  tag id => hit count
     * @param  array<int, int>  $troveTypeCounts  trove type id => hit count
     * @param  array<string, int>  $localeCounts  locale code => hit count
     */
    public function __construct(
        public array $tagCounts,
        public array $troveTypeCounts,
        public array $localeCounts,
    ) {}
}
