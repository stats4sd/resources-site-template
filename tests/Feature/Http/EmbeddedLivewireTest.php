<?php

use App\Livewire\CollectionTroves;
use App\Livewire\SearchBar;
use App\Livewire\TroveCollections;
use App\Livewire\TroveRelatedTroves;
use App\Models\Collection;
use Livewire\Livewire;

// Smoke: the small single-purpose components embedded in trove/collection views mount and
// render without error. (These carry no MySQL-only SQL, unlike BrowseAll.)

beforeEach(fn () => bootPublicSite());

it('mounts CollectionTroves', function () {
    $collection = Collection::factory()->create();
    $collection->troves()->attach(publishedTrove());

    Livewire::test(CollectionTroves::class, ['collection' => $collection])
        ->assertOk();
});

it('mounts TroveCollections', function () {
    $trove = publishedTrove();
    $trove->collections()->attach(Collection::factory()->create());

    Livewire::test(TroveCollections::class, ['resource' => $trove])
        ->assertOk();
});

it('excludes private collections from TroveCollections', function () {
    $trove = publishedTrove();
    $public = Collection::factory()->create(['title' => ['en' => 'Public Set']]);
    $private = Collection::factory()->private()->create(['title' => ['en' => 'Secret Set']]);
    $trove->collections()->attach([$public->id, $private->id]);

    Livewire::test(TroveCollections::class, ['resource' => $trove])
        ->assertSee('Public Set')
        ->assertDontSee('Secret Set');
});

it('mounts TroveRelatedTroves', function () {
    $collection = Collection::factory()->create();
    $trove = publishedTrove();
    $collection->troves()->attach([$trove->id, publishedTrove()->id]);

    Livewire::test(TroveRelatedTroves::class, ['resource' => $trove->fresh()])
        ->assertOk();
});

it('mounts SearchBar', function () {
    Livewire::test(SearchBar::class)->assertOk();
});
