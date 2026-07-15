<?php

namespace App\Services\Search;

use App\Contracts\SearchesLibrary;
use App\Models\Collection;
use App\Models\Trove;
use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;
use Throwable;

/**
 * One federated /multi-search request covering both indexes: text search, filter
 * expressions, cross-index ranking (or shared sort_date ordering when browsing),
 * federation-level pagination and merged facet counts.
 */
class MeilisearchLibrarySearch implements SearchesLibrary
{
    public function __construct(protected Client $client) {}

    public function search(LibrarySearchRequest $request): LibrarySearchResult
    {
        $troveIndex = (new Trove)->searchableAs();
        $collectionIndex = (new Collection)->searchableAs();

        $facetAttributes = ['tag_ids', 'trove_type_ids', 'locales'];

        $federation = (new MultiSearchFederation)
            ->setLimit($request->perPage)
            ->setOffset(($request->page - 1) * $request->perPage)
            ->setFacetsByIndex([
                $troveIndex => $facetAttributes,
                $collectionIndex => $facetAttributes,
            ])
            ->setMergeFacets(['maxValuesPerFacet' => 1000]);

        try {
            $response = $this->client->multiSearch([
                $this->buildQuery($troveIndex, $request),
                $this->buildQuery($collectionIndex, $request),
            ], $federation);
        } catch (Throwable $exception) {
            report($exception);

            throw new SearchUnavailableException('The search engine is unavailable.', previous: $exception);
        }

        return $this->mapResponse($response, $troveIndex, $request);
    }

    protected function buildQuery(string $indexUid, LibrarySearchRequest $request): SearchQuery
    {
        $query = (new SearchQuery)
            ->setIndexUid($indexUid)
            ->setQuery((string) $request->query)
            ->setAttributesToRetrieve(['id']);

        $filters = $this->compileFilters($request);

        if ($filters !== []) {
            $query->setFilter($filters);
        }

        if (! $request->hasQuery()) {
            $query->setSort(['sort_date:desc']);
        }

        return $query;
    }

    /**
     * The same AND-joined expressions for both indexes: OR within a tag type
     * (IN), AND across types and across the other filter dimensions.
     *
     * @return array<int, string>
     */
    protected function compileFilters(LibrarySearchRequest $request): array
    {
        $filters = [];

        foreach ($request->tagIdsByType as $tagIds) {
            if ($tagIds === []) {
                continue;
            }

            $filters[] = 'tag_ids IN ['.implode(', ', array_map(intval(...), $tagIds)).']';
        }

        if ($request->troveTypeIds !== []) {
            $filters[] = 'trove_type_ids IN ['.implode(', ', array_map(intval(...), $request->troveTypeIds)).']';
        }

        if ($request->locales !== []) {
            $quotedLocales = array_map(
                fn (string $locale) => '"'.addcslashes($locale, '\\"').'"',
                $request->locales,
            );

            $filters[] = 'locales IN ['.implode(', ', $quotedLocales).']';
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function mapResponse(array $response, string $troveIndex, LibrarySearchRequest $request): LibrarySearchResult
    {
        $hits = [];

        foreach ($response['hits'] ?? [] as $hit) {
            $hits[] = new LibraryHit(
                type: ($hit['_federation']['indexUid'] ?? null) === $troveIndex ? 'trove' : 'collection',
                id: (int) $hit['id'],
                score: (float) ($hit['_federation']['weightedRankingScore'] ?? 0),
            );
        }

        $totalHits = (int) ($response['estimatedTotalHits'] ?? count($hits));

        return new LibrarySearchResult(
            hits: $hits,
            totalHits: $totalHits,
            totalPages: (int) ceil($totalHits / max(1, $request->perPage)),
            facets: $this->mapFacets($response),
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function mapFacets(array $response): ?LibraryFacets
    {
        $distribution = $response['facetDistribution'] ?? null;

        if ($distribution === null) {
            return null;
        }

        return new LibraryFacets(
            tagCounts: $this->intKeyedCounts($distribution['tag_ids'] ?? []),
            troveTypeCounts: $this->intKeyedCounts($distribution['trove_type_ids'] ?? []),
            localeCounts: array_map(intval(...), $distribution['locales'] ?? []),
        );
    }

    /**
     * Facet keys arrive from Meilisearch as strings; ID-keyed maps cast both ways.
     *
     * @param  array<string|int, int|string>  $counts
     * @return array<int, int>
     */
    protected function intKeyedCounts(array $counts): array
    {
        $intKeyed = [];

        foreach ($counts as $key => $count) {
            $intKeyed[(int) $key] = (int) $count;
        }

        return $intKeyed;
    }
}
