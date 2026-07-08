<?php

use App\Filament\Resources\CollectionResource\Pages\CreateCollection;
use App\Filament\Resources\CollectionResource\Pages\EditCollection;
use App\Filament\Resources\CollectionResource\Pages\ListCollections;
use App\Livewire\AllTrovesTable;
use App\Models\Collection;
use App\Services\TrovePublisher;
use Filament\Facades\Filament;
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

it('sanitises the description HTML on save, stripping scripts and event handlers', function () {
    Livewire::test(CreateCollection::class)
        ->fillForm([
            'title' => ['en' => 'Dangerous Collection'],
            'description' => ['en' => '<p>Safe <em>note</em></p><script>alert(1)</script><a href="javascript:evil()">x</a>'],
            'public' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = Collection::query()->latest('id')->first()->getTranslation('description', 'en');

    expect($stored)->toContain('<em>note</em>')
        ->and($stored)->not->toContain('<script>')
        ->and($stored)->not->toContain('javascript:');
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

it('lists a pending-changes trove only once in the AllTrovesTable', function () {
    $collection = Collection::factory()->create();
    $trove = publishedTrove();
    (new TrovePublisher)->draftFor($trove->fresh());

    Livewire::test(AllTrovesTable::class, ['record' => $collection, 'activeLocale' => 'en'])
        ->assertOk()
        ->assertCountTableRecords(1);
});

it('counts a pending-changes trove once in the collection troves count', function () {
    $collection = Collection::factory()->create();
    $trove = publishedTrove();
    $collection->troves()->attach($trove);

    // Forking a draft copies the collection relation onto the draft, so both rows are attached.
    (new TrovePublisher)->draftFor($trove->fresh());

    // Pin the admin panel so PublishedScope is off (as it is in the real panel), which is the
    // only context where the canonical + draft would otherwise both be counted.
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListCollections::class)
        ->assertTableColumnStateSet('troves_count', 1, $collection);
});
