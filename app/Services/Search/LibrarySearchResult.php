<?php

namespace App\Services\Search;

final readonly class LibrarySearchResult
{
    /**
     * @param  array<int, LibraryHit>  $hits  one page of ranked hits
     * @param  ?LibraryFacets  $facets  null when the backend cannot provide facet counts
     */
    public function __construct(
        public array $hits,
        public int $totalHits,
        public int $totalPages,
        public ?LibraryFacets $facets,
    ) {}
}
