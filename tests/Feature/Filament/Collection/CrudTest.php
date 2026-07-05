<?php

use App\Filament\Resources\CollectionResource\Pages\CreateCollection;
use App\Filament\Resources\CollectionResource\Pages\EditCollection;
use App\Livewire\AllTrovesTable;
use App\Models\Collection;
use Livewire\Livewire;

beforeEach(fn () => actingAsAdmin());

it('creates a collection via the create form', function () {
    Livewire::test(CreateCollection::class)
        ->fillForm([
            'title' => ['en' => 'Getting Started'],
            'description' => ['en' => 'A curated set.'],
            'public' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $collection = Collection::firstWhere('id', '>', 0);
    expect($collection->getTranslation('title', 'en'))->toBe('Getting Started')
        ->and($collection->public)->toBeTrue();
});

it('edits a collection via the edit form', function () {
    $collection = Collection::factory()->create(['title' => ['en' => 'Before']]);

    Livewire::test(EditCollection::class, ['record' => $collection->getKey()])
        ->fillForm(['title' => ['en' => 'After']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($collection->fresh()->getTranslation('title', 'en'))->toBe('After');
});

// The collection screen manages its troves through the AllTrovesTable component (the
// TrovesRelationManager itself is render-smoke-covered by the ViewCollection/EditCollection
// page GETs in PanelAccessTest). record must be passed as the model, and activeLocale set.
it('attaches a trove through the AllTrovesTable component', function () {
    $collection = Collection::factory()->create();
    $trove = publishedTrove();

    Livewire::test(AllTrovesTable::class, ['record' => $collection, 'activeLocale' => 'en'])
        ->assertOk()
        ->callTableAction('attach_trove', $trove);

    expect($collection->troves()->whereKey($trove->id)->exists())->toBeTrue();
});

it('detaches a trove through the AllTrovesTable component', function () {
    $collection = Collection::factory()->create();
    $trove = publishedTrove();
    $collection->troves()->attach($trove);

    Livewire::test(AllTrovesTable::class, ['record' => $collection, 'activeLocale' => 'en'])
        ->callTableAction('detach_trove', $trove);

    expect($collection->troves()->whereKey($trove->id)->exists())->toBeFalse();
});
