<?php

use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Services\Search\LibrarySearchRequest;
use App\Services\Search\MeilisearchLibrarySearch;
use Illuminate\Support\Facades\Artisan;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;

/**
 * Exercises the real Meilisearch protocol end to end — what a mocked Client can't catch:
 * scout:sync-index-settings applying the declared attributes, the federated request/response
 * shape, filter/facet semantics (including the disjunctive-facet recompute from Task 10), and
 * the shared sort_date federated sort across the two indexes. Skipped unless
 * MEILISEARCH_INTEGRATION=1 and a real Meilisearch instance is reachable at MEILISEARCH_HOST —
 * see README.md.
 */
beforeEach(function () {
    if (! env('MEILISEARCH_INTEGRATION')) {
        $this->markTestSkipped('Set MEILISEARCH_INTEGRATION=1, with a real Meilisearch instance reachable at MEILISEARCH_HOST, to run this suite.');
    }

    config([
        'scout.driver' => 'meilisearch',
        'scout.prefix' => 'integration_'.bin2hex(random_bytes(4)).'_',
    ]);

    $this->client = app(Client::class);
    $this->troveIndexUid = (new Trove)->searchableAs();
    $this->collectionIndexUid = (new Collection)->searchableAs();

    Artisan::call('scout:sync-index-settings');
    waitForMeilisearchIdle($this->client, [$this->troveIndexUid, $this->collectionIndexUid]);
});

afterEach(function () {
    if (! env('MEILISEARCH_INTEGRATION')) {
        return;
    }

    try {
        $this->client->deleteIndex($this->troveIndexUid);
        $this->client->deleteIndex($this->collectionIndexUid);
    } catch (Throwable) {
        // best-effort cleanup — a leftover throwaway index is harmless
    }
});

function waitForMeilisearchIdle(Client $client, array $indexUids, int $timeoutMs = 10000): void
{
    $deadline = microtime(true) + $timeoutMs / 1000;

    do {
        $pending = $client->getTasks(
            (new TasksQuery)->setIndexUids($indexUids)->setStatuses(['enqueued', 'processing'])
        );

        if ($pending->getTotal() === 0) {
            return;
        }

        usleep(100_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('Timed out waiting for Meilisearch to finish indexing.');
}

/**
 * Force a fresh reindex of each fixture (covers relation changes made after the model's own
 * save, e.g. attaching tags, which Scout's saved() hook can't see) and wait for the engine to
 * settle before searching.
 */
function indexAndWait(Trove|Collection ...$models): void
{
    foreach ($models as $model) {
        $model->searchable();
    }

    $indexUids = collect($models)->map(fn ($model) => $model->searchableAs())->unique()->values()->all();

    waitForMeilisearchIdle(app(Client::class), $indexUids);
}

it('applies the declared filterable/sortable/searchable attributes via scout:sync-index-settings', function () {
    $troveSettings = $this->client->index($this->troveIndexUid)->getSettings();
    $collectionSettings = $this->client->index($this->collectionIndexUid)->getSettings();

    expect($troveSettings['filterableAttributes'])->toEqualCanonicalizing(['tag_ids', 'trove_type_ids', 'locales'])
        ->and($troveSettings['sortableAttributes'])->toBe(['sort_date'])
        ->and($troveSettings['searchableAttributes'])->toBe(['title', 'description', 'tag_names'])
        ->and($collectionSettings['filterableAttributes'])->toEqualCanonicalizing(['tag_ids', 'trove_type_ids', 'locales'])
        ->and($collectionSettings['sortableAttributes'])->toBe(['sort_date'])
        ->and($collectionSettings['searchableAttributes'])->toBe(['title', 'description']);
});

it('returns a federated, paginated result merging both indexes', function () {
    $troves = Trove::factory()->published()->count(3)->create();
    $collections = Collection::factory()->count(2)->create();

    indexAndWait(...$troves, ...$collections);

    $firstPage = (new MeilisearchLibrarySearch($this->client))->search(new LibrarySearchRequest(perPage: 3, page: 1));
    $secondPage = (new MeilisearchLibrarySearch($this->client))->search(new LibrarySearchRequest(perPage: 3, page: 2));

    expect($firstPage->totalHits)->toBe(5)
        ->and($firstPage->totalPages)->toBe(2)
        ->and($firstPage->hits)->toHaveCount(3)
        ->and($secondPage->hits)->toHaveCount(2);

    $seenIds = collect([...$firstPage->hits, ...$secondPage->hits])
        ->map(fn ($hit) => $hit->type.':'.$hit->id)
        ->sort()
        ->values();

    $expectedIds = collect($troves)->map(fn (Trove $trove) => 'trove:'.$trove->id)
        ->merge($collections->map(fn (Collection $collection) => 'collection:'.$collection->id))
        ->sort()
        ->values();

    expect($seenIds->all())->toBe($expectedIds->all());
});

it('narrows results via tag/type filters and reports disjunctive facet counts for the active dimensions', function () {
    $tagType = TagType::factory()->create();
    $matchingTag = Tag::factory()->ofType($tagType)->create();
    $otherTag = Tag::factory()->ofType($tagType)->create();
    $troveType = TroveType::factory()->create();
    $otherTroveType = TroveType::factory()->create();

    $matching = Trove::factory()->published()->create(['trove_type_id' => $troveType->id]);
    $matching->tags()->attach($matchingTag->id);

    $sameTagDifferentType = Trove::factory()->published()->create(['trove_type_id' => $otherTroveType->id]);
    $sameTagDifferentType->tags()->attach($matchingTag->id);

    $sameTypeDifferentTag = Trove::factory()->published()->create(['trove_type_id' => $troveType->id]);
    $sameTypeDifferentTag->tags()->attach($otherTag->id);

    indexAndWait($matching, $sameTagDifferentType, $sameTypeDifferentTag);

    $result = (new MeilisearchLibrarySearch($this->client))->search(new LibrarySearchRequest(
        tagIdsByType: [$tagType->id => [$matchingTag->id]],
        troveTypeIds: [$troveType->id],
    ));

    expect($result->totalHits)->toBe(1)
        ->and($result->hits[0]->id)->toBe($matching->id)
        ->and($result->facets->tagCounts[$matchingTag->id])->toBe(1)
        ->and($result->facets->troveTypeCounts[$troveType->id])->toBe(1)
        // Disjunctive proof: under the old conjunctive computation (both filters applied
        // together) these sibling options would read 0, since only $matching satisfies the
        // full AND — but each has one real match once its own dimension's filter is relaxed.
        ->and($result->facets->tagCounts[$otherTag->id])->toBe(1)
        ->and($result->facets->troveTypeCounts[$otherTroveType->id])->toBe(1);
});

it('orders a browse-mode (empty query) federated request by the shared sort_date across both indexes', function () {
    $older = Trove::factory()->published()->create(['published_at' => now()->subDays(3)]);
    $newest = Trove::factory()->published()->create(['published_at' => now()]);

    // created_at can't be set via the factory: Eloquent's insert always stamps it to "now",
    // regardless of what's passed in. A plain update afterwards only touches updated_at.
    $middleCollection = Collection::factory()->create();
    $middleCollection->created_at = now()->subDay();
    $middleCollection->save();

    indexAndWait($older, $newest, $middleCollection);

    $result = (new MeilisearchLibrarySearch($this->client))->search(new LibrarySearchRequest(perPage: 10));

    $order = collect($result->hits)->map(fn ($hit) => $hit->type.':'.$hit->id)->all();

    expect($order)->toBe([
        'trove:'.$newest->id,
        'collection:'.$middleCollection->id,
        'trove:'.$older->id,
    ]);
});

it('makes tag_names searchable — a query matching only a tag name surfaces the tagged trove', function () {
    $tagType = TagType::factory()->create();
    $tag = Tag::factory()->ofType($tagType)->create(['name' => ['en' => 'Gender']]);

    $tagged = Trove::factory()->published()->create();
    $tagged->tags()->attach($tag->id);

    $untagged = Trove::factory()->published()->create();

    indexAndWait($tagged, $untagged);

    $result = (new MeilisearchLibrarySearch($this->client))->search(new LibrarySearchRequest(query: 'gender'));

    $hitIds = collect($result->hits)->pluck('id');

    expect($hitIds)->toContain($tagged->id)
        ->and($hitIds)->not->toContain($untagged->id);
});
