<?php

use App\Models\Collection;
use App\Models\Tag;
use App\Models\Trove;
use App\Models\TroveType;
use App\Services\Search\DatabaseLibrarySearch;
use App\Services\Search\LibrarySearchRequest;

function searchDatabase(LibrarySearchRequest $request = new LibrarySearchRequest)
{
    return (new DatabaseLibrarySearch)->search($request);
}

it('lists published canonical troves and public collections only', function () {
    $published = publishedTrove();
    draftTrove();
    Trove::withoutSyncingToSearch(fn () => Trove::factory()->draftOf($published)->create());

    $public = Collection::factory()->create();
    Collection::factory()->private()->create();

    $result = searchDatabase();

    $hitKeys = collect($result->hits)->map(fn ($hit) => "{$hit->type}:{$hit->id}");

    expect($result->totalHits)->toBe(2)
        ->and($hitKeys)->toContain("trove:{$published->id}")
        ->and($hitKeys)->toContain("collection:{$public->id}");
});

it('merges troves and collections ordered by date descending', function () {
    $older = publishedTrove(['published_at' => now()->subDays(3)]);
    $newer = publishedTrove(['published_at' => now()->subDay()]);
    $middle = Collection::factory()->create(['created_at' => now()->subDays(2)]);

    $result = searchDatabase();

    $hitKeys = collect($result->hits)->map(fn ($hit) => "{$hit->type}:{$hit->id}")->all();

    expect($hitKeys)->toBe([
        "trove:{$newer->id}",
        "collection:{$middle->id}",
        "trove:{$older->id}",
    ]);
});

it('filters troves with OR within a tag type and AND across types', function () {
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $tagC = Tag::factory()->create();

    $matchesBoth = publishedTrove();
    $matchesBoth->tags()->attach([$tagA->id, $tagC->id]);

    $matchesOneType = publishedTrove();
    $matchesOneType->tags()->attach($tagA);

    $result = searchDatabase(new LibrarySearchRequest(tagIdsByType: [
        1 => [$tagA->id, $tagB->id],
        2 => [$tagC->id],
    ]));

    expect($result->totalHits)->toBe(1)
        ->and($result->hits[0]->type)->toBe('trove')
        ->and($result->hits[0]->id)->toBe($matchesBoth->id);
});

it('applies tag filters to collections through published members only', function () {
    $tag = Tag::factory()->create();

    $publishedMember = publishedTrove();
    $publishedMember->tags()->attach($tag);

    $draftMember = draftTrove();
    $draftMember->tags()->attach($tag);

    $matching = Collection::factory()->create();
    $matching->troves()->attach($publishedMember);

    $draftOnly = Collection::factory()->create();
    $draftOnly->troves()->attach($draftMember);

    Collection::factory()->create();

    $result = searchDatabase(new LibrarySearchRequest(tagIdsByType: [1 => [$tag->id]]));

    $collectionIds = collect($result->hits)->where('type', 'collection')->pluck('id');

    expect($collectionIds->all())->toBe([$matching->id]);
});

it('filters troves and collections by trove type', function () {
    $type = TroveType::factory()->create();

    $typedTrove = publishedTrove(['trove_type_id' => $type->id]);
    publishedTrove();

    $typedCollection = Collection::factory()->create();
    $typedCollection->troves()->attach($typedTrove);

    $untypedCollection = Collection::factory()->create();
    $untypedMember = publishedTrove();
    $untypedCollection->troves()->attach($untypedMember);

    $result = searchDatabase(new LibrarySearchRequest(troveTypeIds: [$type->id]));

    $hitKeys = collect($result->hits)->map(fn ($hit) => "{$hit->type}:{$hit->id}");

    expect($result->totalHits)->toBe(2)
        ->and($hitKeys)->toContain("trove:{$typedTrove->id}")
        ->and($hitKeys)->toContain("collection:{$typedCollection->id}");
});

it('filters by locale through title translations', function () {
    $french = publishedTrove(['title' => ['en' => 'English title', 'fr' => 'Titre']]);
    publishedTrove(['title' => ['en' => 'English only']]);

    $frenchCollection = Collection::factory()->create(['title' => ['fr' => 'Rassemblement']]);
    Collection::factory()->create(['title' => ['en' => 'English collection']]);

    $result = searchDatabase(new LibrarySearchRequest(locales: ['fr']));

    $hitKeys = collect($result->hits)->map(fn ($hit) => "{$hit->type}:{$hit->id}");

    expect($result->totalHits)->toBe(2)
        ->and($hitKeys)->toContain("trove:{$french->id}")
        ->and($hitKeys)->toContain("collection:{$frenchCollection->id}");
});

it('paginates the merged listing with correct totals', function () {
    foreach (range(1, 5) as $daysAgo) {
        publishedTrove(['published_at' => now()->subDays($daysAgo)]);
    }

    $pageOne = searchDatabase(new LibrarySearchRequest(page: 1, perPage: 2));
    $pageThree = searchDatabase(new LibrarySearchRequest(page: 3, perPage: 2));

    expect($pageOne->totalHits)->toBe(5)
        ->and($pageOne->totalPages)->toBe(3)
        ->and($pageOne->hits)->toHaveCount(2)
        ->and($pageThree->hits)->toHaveCount(1);
});

it('provides no facets and zero scores', function () {
    publishedTrove();

    $result = searchDatabase();

    expect($result->facets)->toBeNull()
        ->and($result->hits[0]->score)->toBe(0.0);
});
