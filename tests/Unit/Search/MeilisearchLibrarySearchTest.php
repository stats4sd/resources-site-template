<?php

use App\Models\Collection;
use App\Models\Trove;
use App\Services\Search\LibrarySearchRequest;
use App\Services\Search\MeilisearchLibrarySearch;
use App\Services\Search\SearchUnavailableException;
use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;

function troveIndexUid(): string
{
    return (new Trove)->searchableAs();
}

function collectionIndexUid(): string
{
    return (new Collection)->searchableAs();
}

/**
 * Mock the Meilisearch client, capture the multiSearch payload into $captured, and
 * respond with $response.
 */
function mockMeilisearchClient(array &$captured, array $response): Client
{
    $client = Mockery::mock(Client::class);

    $client->shouldReceive('multiSearch')
        ->once()
        ->withArgs(function (array $queries, MultiSearchFederation $federation) use (&$captured) {
            $captured = [
                'queries' => array_map(fn (SearchQuery $query) => $query->toArray(), $queries),
                'federation' => $federation->toArray(),
            ];

            return true;
        })
        ->andReturn($response);

    return $client;
}

function emptyFederatedResponse(): array
{
    return ['hits' => [], 'estimatedTotalHits' => 0];
}

it('sends one federated request carrying both index queries, filters, pagination and facets', function () {
    $captured = [];
    $search = new MeilisearchLibrarySearch(mockMeilisearchClient($captured, emptyFederatedResponse()));

    $search->search(new LibrarySearchRequest(
        query: 'gender',
        tagIdsByType: [1 => [10, 11], 2 => [], 3 => [20]],
        troveTypeIds: [4],
        locales: ['en', 'fr'],
        page: 3,
        perPage: 24,
    ));

    [$troveQuery, $collectionQuery] = $captured['queries'];

    $expectedFilters = [
        'tag_ids IN [10, 11]',
        'tag_ids IN [20]',
        'trove_type_ids IN [4]',
        'locales IN ["en", "fr"]',
    ];

    expect($troveQuery['indexUid'])->toBe(troveIndexUid())
        ->and($collectionQuery['indexUid'])->toBe(collectionIndexUid())
        ->and($troveQuery['q'])->toBe('gender')
        ->and($collectionQuery['q'])->toBe('gender')
        ->and($troveQuery['filter'])->toBe($expectedFilters)
        ->and($collectionQuery['filter'])->toBe($expectedFilters)
        ->and($troveQuery)->not->toHaveKeys(['sort', 'limit', 'offset', 'page', 'hitsPerPage', 'facets'])
        ->and($captured['federation']['limit'])->toBe(24)
        ->and($captured['federation']['offset'])->toBe(48)
        ->and($captured['federation']['facetsByIndex'])->toBe([
            troveIndexUid() => ['tag_ids', 'trove_type_ids', 'locales'],
            collectionIndexUid() => ['tag_ids', 'trove_type_ids', 'locales'],
        ])
        ->and($captured['federation']['mergeFacets'])->toBe(['maxValuesPerFacet' => 1000]);
});

it('omits filters entirely when nothing is selected', function () {
    $captured = [];
    $search = new MeilisearchLibrarySearch(mockMeilisearchClient($captured, emptyFederatedResponse()));

    $search->search(new LibrarySearchRequest(query: 'gender'));

    expect($captured['queries'][0])->not->toHaveKey('filter')
        ->and($captured['queries'][1])->not->toHaveKey('filter');
});

it('sorts both queries by sort_date descending when the query is blank', function (?string $blankQuery) {
    $captured = [];
    $search = new MeilisearchLibrarySearch(mockMeilisearchClient($captured, emptyFederatedResponse()));

    $search->search(new LibrarySearchRequest(query: $blankQuery));

    expect($captured['queries'][0]['sort'])->toBe(['sort_date:desc'])
        ->and($captured['queries'][1]['sort'])->toBe(['sort_date:desc']);
})->with([null, '', '   ']);

it('maps the federated response into hits, totals and int-cast facets', function () {
    $response = [
        'hits' => [
            ['id' => 5, '_federation' => ['indexUid' => troveIndexUid(), 'queriesPosition' => 0, 'weightedRankingScore' => 0.91]],
            ['id' => 7, '_federation' => ['indexUid' => collectionIndexUid(), 'queriesPosition' => 1, 'weightedRankingScore' => 0.85]],
            ['id' => 6, '_federation' => ['indexUid' => troveIndexUid(), 'queriesPosition' => 0, 'weightedRankingScore' => 0.42]],
        ],
        'estimatedTotalHits' => 51,
        'facetDistribution' => [
            'tag_ids' => ['10' => 3, '11' => 1],
            'trove_type_ids' => ['4' => 2],
            'locales' => ['en' => 5, 'fr' => 2],
        ],
    ];

    $captured = [];
    $search = new MeilisearchLibrarySearch(mockMeilisearchClient($captured, $response));

    $result = $search->search(new LibrarySearchRequest(query: 'gender', perPage: 24));

    expect($result->totalHits)->toBe(51)
        ->and($result->totalPages)->toBe(3)
        ->and($result->hits)->toHaveCount(3)
        ->and($result->hits[0]->type)->toBe('trove')
        ->and($result->hits[0]->id)->toBe(5)
        ->and($result->hits[0]->score)->toBe(0.91)
        ->and($result->hits[1]->type)->toBe('collection')
        ->and($result->hits[1]->id)->toBe(7)
        ->and($result->facets->tagCounts)->toBe([10 => 3, 11 => 1])
        ->and($result->facets->troveTypeCounts)->toBe([4 => 2])
        ->and($result->facets->localeCounts)->toBe(['en' => 5, 'fr' => 2]);
});

it('returns null facets when the response has no facet distribution', function () {
    $captured = [];
    $search = new MeilisearchLibrarySearch(mockMeilisearchClient($captured, emptyFederatedResponse()));

    $result = $search->search(new LibrarySearchRequest);

    expect($result->facets)->toBeNull();
});

it('wraps any client failure in a SearchUnavailableException', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('multiSearch')->once()->andThrow(new RuntimeException('connection refused'));

    $search = new MeilisearchLibrarySearch($client);

    expect(fn () => $search->search(new LibrarySearchRequest(query: 'gender')))
        ->toThrow(SearchUnavailableException::class);
});
