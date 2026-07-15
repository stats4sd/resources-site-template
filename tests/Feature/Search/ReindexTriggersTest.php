<?php

use App\Filament\Resources\CollectionResource\Pages\EditCollection;
use App\Filament\Resources\CollectionResource\RelationManagers\TrovesRelationManager;
use App\Livewire\AllTrovesTable;
use App\Models\Collection;
use App\Models\Tag;
use App\Models\Trove;
use App\Services\TrovePublisher;
use Illuminate\Support\Collection as SupportCollection;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Livewire\Livewire;

/**
 * Swap the Scout engine for a spy collecting every model pushed to the index.
 * Mutating the returned collection instance keeps it live across calls.
 */
function captureSearchIndexUpdates(): SupportCollection
{
    $updated = collect();

    $engine = Mockery::mock(Engine::class);
    $engine->shouldIgnoreMissing();
    $engine->shouldReceive('update')->andReturnUsing(function ($models) use ($updated) {
        $models->each(fn ($model) => $updated->push($model));
    });

    $manager = Mockery::mock(EngineManager::class);
    $manager->shouldReceive('engine')->andReturn($engine);
    app()->instance(EngineManager::class, $manager);

    return $updated;
}

function indexedTroveIds(SupportCollection $updated): SupportCollection
{
    return $updated->filter(fn ($model) => $model instanceof Trove)->pluck('id');
}

function indexedCollectionIds(SupportCollection $updated): SupportCollection
{
    return $updated->filter(fn ($model) => $model instanceof Collection)->pluck('id');
}

describe('tag changes', function () {
    it('reindexes member troves and their collections when a tag is renamed', function () {
        $tag = Tag::factory()->create();
        $taggedTrove = publishedTrove();
        $taggedTrove->tags()->attach($tag);
        publishedTrove();

        $collection = Collection::factory()->create();
        $collection->troves()->attach($taggedTrove);
        $untaggedCollection = Collection::factory()->create();

        $updated = captureSearchIndexUpdates();

        $tag->update(['name' => ['en' => 'Renamed']]);

        expect(indexedTroveIds($updated)->all())->toBe([$taggedTrove->id])
            ->and(indexedCollectionIds($updated)->all())->toBe([$collection->id])
            ->and(indexedCollectionIds($updated))->not->toContain($untaggedCollection->id);
    });

    it('does not reindex anything when a tag is saved without a name change', function () {
        $tag = Tag::factory()->create();
        $taggedTrove = publishedTrove();
        $taggedTrove->tags()->attach($tag);

        $updated = captureSearchIndexUpdates();

        $tag->update(['order_column' => 5]);

        expect($updated)->toBeEmpty();
    });

    it('does not reindex draft members on a tag rename', function () {
        $tag = Tag::factory()->create();
        $draft = draftTrove();
        $draft->tags()->attach($tag);

        $updated = captureSearchIndexUpdates();

        $tag->update(['name' => ['en' => 'Renamed']]);

        expect($updated)->toBeEmpty();
    });

    it('reindexes former member troves and collections when a tag is deleted', function () {
        $tag = Tag::factory()->create();
        $taggedTrove = publishedTrove();
        $taggedTrove->tags()->attach($tag);

        $collection = Collection::factory()->create();
        $collection->troves()->attach($taggedTrove);

        $updated = captureSearchIndexUpdates();

        $tag->delete();

        expect(indexedTroveIds($updated)->all())->toBe([$taggedTrove->id])
            ->and(indexedCollectionIds($updated)->all())->toBe([$collection->id]);
    });
});

describe('collection membership changes', function () {
    beforeEach(fn () => actingAsAdmin());

    it('reindexes the collection when a trove is attached via the AllTrovesTable', function () {
        $collection = Collection::factory()->create();
        $trove = publishedTrove();

        $updated = captureSearchIndexUpdates();

        Livewire::test(AllTrovesTable::class, ['record' => $collection, 'activeLocale' => 'en'])
            ->callTableAction('attach_trove', $trove);

        expect($collection->troves()->whereKey($trove->id)->exists())->toBeTrue()
            ->and(indexedCollectionIds($updated))->toContain($collection->id);
    });

    it('reindexes the collection when a trove is detached via the AllTrovesTable', function () {
        $collection = Collection::factory()->create();
        $trove = publishedTrove();
        $collection->troves()->attach($trove);

        $updated = captureSearchIndexUpdates();

        Livewire::test(AllTrovesTable::class, ['record' => $collection, 'activeLocale' => 'en'])
            ->callTableAction('detach_trove', $trove);

        expect($collection->troves()->whereKey($trove->id)->exists())->toBeFalse()
            ->and(indexedCollectionIds($updated))->toContain($collection->id);
    });

    it('reindexes the collection when troves are bulk-attached via the AllTrovesTable', function () {
        $collection = Collection::factory()->create();
        $troveA = publishedTrove();
        $troveB = publishedTrove();

        $updated = captureSearchIndexUpdates();

        Livewire::test(AllTrovesTable::class, ['record' => $collection, 'activeLocale' => 'en'])
            ->callTableBulkAction('attach', [$troveA->id, $troveB->id]);

        expect($collection->troves()->count())->toBe(2)
            ->and(indexedCollectionIds($updated))->toContain($collection->id);
    });

    it('reindexes the collection when a trove is detached via the relation manager', function () {
        $collection = Collection::factory()->create();
        $trove = publishedTrove();
        $collection->troves()->attach($trove);

        $updated = captureSearchIndexUpdates();

        Livewire::test(TrovesRelationManager::class, [
            'ownerRecord' => $collection,
            'pageClass' => EditCollection::class,
            'activeLocale' => 'en',
        ])->callTableAction('detach', $trove);

        expect($collection->troves()->whereKey($trove->id)->exists())->toBeFalse()
            ->and(indexedCollectionIds($updated))->toContain($collection->id);
    });
});

describe('publish lifecycle', function () {
    it('reindexes the collections of a trove published in place', function () {
        $trove = draftTrove();
        $collection = Collection::factory()->create();
        $collection->troves()->attach($trove);

        $updated = captureSearchIndexUpdates();

        (new TrovePublisher)->publish($trove);

        expect(indexedCollectionIds($updated))->toContain($collection->id);
    });

    it('reindexes collections on both sides of a membership change when a draft is published', function () {
        $canonical = publishedTrove();
        $keptCollection = Collection::factory()->create();
        $leftCollection = Collection::factory()->create();
        $canonical->collections()->attach([$keptCollection->id, $leftCollection->id]);

        $publisher = new TrovePublisher;
        $draft = $publisher->draftFor($canonical->fresh());
        $draft->collections()->sync([$keptCollection->id]);

        $updated = captureSearchIndexUpdates();

        $publisher->publish($draft->fresh());

        expect(indexedCollectionIds($updated))->toContain($keptCollection->id)
            ->and(indexedCollectionIds($updated))->toContain($leftCollection->id);
    });

    it('reindexes the collections of an unpublished trove', function () {
        $trove = publishedTrove();
        $collection = Collection::factory()->create();
        $collection->troves()->attach($trove);

        $updated = captureSearchIndexUpdates();

        (new TrovePublisher)->unpublish($trove->fresh());

        expect(indexedCollectionIds($updated))->toContain($collection->id);
    });

    it('reindexes the collections of a deleted trove', function () {
        $trove = publishedTrove();
        $collection = Collection::factory()->create();
        $collection->troves()->attach($trove);

        $updated = captureSearchIndexUpdates();

        (new TrovePublisher)->delete($trove->fresh());

        expect(indexedCollectionIds($updated))->toContain($collection->id);
    });
});
