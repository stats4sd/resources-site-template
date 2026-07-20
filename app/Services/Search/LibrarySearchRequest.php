<?php

namespace App\Services\Search;

final readonly class LibrarySearchRequest
{
    /**
     * @param  array<int, array<int, int>>  $tagIdsByType  tag type id => selected tag ids (OR within a type, AND across types)
     * @param  array<int, int>  $troveTypeIds
     * @param  array<int, string>  $locales
     */
    public function __construct(
        public ?string $query = null,
        public array $tagIdsByType = [],
        public array $troveTypeIds = [],
        public array $locales = [],
        public int $page = 1,
        public int $perPage = 24,
    ) {}

    public function hasQuery(): bool
    {
        return $this->query !== null && trim($this->query) !== '';
    }
}
