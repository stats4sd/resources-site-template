<?php

namespace App\Services\Search;

use App\Contracts\SearchesLibrary;
use App\Models\Collection;
use App\Models\Tag;
use App\Models\Trove;
use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;
use Throwable;

/**
 * One federated /multi-search request covering both indexes: text search, filter
 * expressions, cross-index ranking (or shared sort_date ordering when browsing),
 * federation-level pagination and merged facet counts. When a filter dimension
 * (a tag type, the trove type, or the locale filter) has an active selection, its
 * own facet counts are recomputed disjunctively — same request, minus that one
 * dimension's own filter — so an OR-within-type selection never mutes its own
 * siblings just because the current selection narrows the result set.
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

            $result = $this->mapResponse($response, $troveIndex, $request);

            if ($result->facets !== null) {
                $result = $this->withDisjunctiveFacets($result, $request, $troveIndex, $collectionIndex);
            }
        } catch (Throwable $exception) {
            report($exception);

            throw new SearchUnavailableException('The search engine is unavailable.', previous: $exception);
        }

        return $result;
    }

    /**
     * Recompute facet counts for every dimension with an active selection, batched
     * into one extra (non-federated) /multi-search call, and overlay them onto the
     * conjunctive counts from the main request. Dimensions with no active selection
     * keep their conjunctive counts untouched (already correct — nothing of their
     * own is filtering the result set).
     */
    protected function withDisjunctiveFacets(
        LibrarySearchResult $result,
        LibrarySearchRequest $request,
        string $troveIndex,
        string $collectionIndex,
    ): LibrarySearchResult {
        $selectedTagTypeIds = array_keys(array_filter(
            $request->tagIdsByType,
            fn (array $tagIds) => $tagIds !== [],
        ));

        $recomputeTroveType = $request->troveTypeIds !== [];
        $recomputeLocales = $request->locales !== [];

        if ($selectedTagTypeIds === [] && ! $recomputeTroveType && ! $recomputeLocales) {
            return $result;
        }

        $queries = [];
        $dimensions = [];

        foreach ($selectedTagTypeIds as $tagTypeId) {
            $filter = $this->compileFilters($request, excludingTagTypeId: $tagTypeId);

            $queries[] = $this->disjunctiveFacetQuery($troveIndex, $filter, 'tag_ids');
            $queries[] = $this->disjunctiveFacetQuery($collectionIndex, $filter, 'tag_ids');
            $dimensions[] = ['attribute' => 'tag_ids', 'tagTypeId' => $tagTypeId];
        }

        if ($recomputeTroveType) {
            $filter = $this->compileFilters($request, excludingTroveType: true);

            $queries[] = $this->disjunctiveFacetQuery($troveIndex, $filter, 'trove_type_ids');
            $queries[] = $this->disjunctiveFacetQuery($collectionIndex, $filter, 'trove_type_ids');
            $dimensions[] = ['attribute' => 'trove_type_ids', 'tagTypeId' => null];
        }

        if ($recomputeLocales) {
            $filter = $this->compileFilters($request, excludingLocales: true);

            $queries[] = $this->disjunctiveFacetQuery($troveIndex, $filter, 'locales');
            $queries[] = $this->disjunctiveFacetQuery($collectionIndex, $filter, 'locales');
            $dimensions[] = ['attribute' => 'locales', 'tagTypeId' => null];
        }

        $response = $this->client->multiSearch($queries);

        return new LibrarySearchResult(
            hits: $result->hits,
            totalHits: $result->totalHits,
            totalPages: $result->totalPages,
            facets: $this->overlayDisjunctiveCounts($result->facets, $response['results'] ?? [], $dimensions),
        );
    }

    protected function disjunctiveFacetQuery(string $indexUid, array $filter, string $facetAttribute): SearchQuery
    {
        $query = (new SearchQuery)
            ->setIndexUid($indexUid)
            ->setLimit(0)
            ->setFacets([$facetAttribute]);

        if ($filter !== []) {
            $query->setFilter($filter);
        }

        return $query;
    }

    /**
     * @param  array<int, array<string, mixed>>  $perQueryResults  in the same trove/collection pair order the queries were built
     * @param  array<int, array{attribute: string, tagTypeId: int|null}>  $dimensions
     */
    protected function overlayDisjunctiveCounts(LibraryFacets $facets, array $perQueryResults, array $dimensions): LibraryFacets
    {
        $tagCounts = $facets->tagCounts;
        $troveTypeCounts = $facets->troveTypeCounts;
        $localeCounts = $facets->localeCounts;

        foreach ($dimensions as $index => $dimension) {
            $merged = $this->sumCountsByKey(
                $perQueryResults[$index * 2]['facetDistribution'][$dimension['attribute']] ?? [],
                $perQueryResults[$index * 2 + 1]['facetDistribution'][$dimension['attribute']] ?? [],
            );

            if ($dimension['attribute'] === 'trove_type_ids') {
                $troveTypeCounts = $this->intKeyedCounts($merged);

                continue;
            }

            if ($dimension['attribute'] === 'locales') {
                $localeCounts = array_map(intval(...), $merged);

                continue;
            }

            foreach (Tag::where('type_id', $dimension['tagTypeId'])->pluck('id') as $tagId) {
                $tagCounts[$tagId] = $merged[(string) $tagId] ?? 0;
            }
        }

        return new LibraryFacets($tagCounts, $troveTypeCounts, $localeCounts);
    }

    /**
     * @param  array<string|int, int|string>  $first
     * @param  array<string|int, int|string>  $second
     * @return array<string, int>
     */
    protected function sumCountsByKey(array $first, array $second): array
    {
        $summed = [];

        foreach ([$first, $second] as $distribution) {
            foreach ($distribution as $key => $count) {
                $summed[$key] = ($summed[$key] ?? 0) + (int) $count;
            }
        }

        return $summed;
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
     * (IN), AND across types and across the other filter dimensions. Any of the
     * three dimensions can be omitted from the compiled expression — used to
     * recompute a dimension's own disjunctive facet counts with everything else
     * still applied.
     *
     * @return array<int, string>
     */
    protected function compileFilters(
        LibrarySearchRequest $request,
        ?int $excludingTagTypeId = null,
        bool $excludingTroveType = false,
        bool $excludingLocales = false,
    ): array {
        $filters = [];

        foreach ($request->tagIdsByType as $tagTypeId => $tagIds) {
            if ($tagIds === [] || $tagTypeId === $excludingTagTypeId) {
                continue;
            }

            $filters[] = 'tag_ids IN ['.implode(', ', array_map(intval(...), $tagIds)).']';
        }

        if ($request->troveTypeIds !== [] && ! $excludingTroveType) {
            $filters[] = 'trove_type_ids IN ['.implode(', ', array_map(intval(...), $request->troveTypeIds)).']';
        }

        if ($request->locales !== [] && ! $excludingLocales) {
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
