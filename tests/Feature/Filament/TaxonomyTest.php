<?php

use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Filament\Resources\TagTypeResource\Pages\EditTagType;
use App\Filament\Resources\TagTypeResource\Pages\ListTagTypes;
use App\Filament\Resources\TroveTypeResource\Pages\ListTroveTypes;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\TroveType;
use Livewire\Livewire;

beforeEach(fn () => actingAsAdmin());

it('creates a trove type through the manage-records modal', function () {
    Livewire::test(ListTroveTypes::class)
        ->callAction('create', ['label' => ['en' => 'Webinar']]);

    expect(TroveType::query()->get()->contains(
        fn (TroveType $t) => $t->getTranslation('label', 'en') === 'Webinar'
    ))->toBeTrue();
});

it('creates a tag type through the manage-records modal', function () {
    Livewire::test(ListTagTypes::class)
        ->callAction('create', [
            'slug' => 'authors',
            'label' => ['en' => 'Authors'],
            'description' => ['en' => 'Content authors'],
            'freetext' => true,
            'show_in_filter' => false,
        ]);

    $tagType = TagType::firstWhere('slug', 'authors');
    expect($tagType)->not->toBeNull()
        ->and($tagType->freetext)->toBeTrue()
        ->and($tagType->getTranslation('label', 'en'))->toBe('Authors');
});

it('creates a tag through the manage-records modal', function () {
    $type = TagType::factory()->create();

    Livewire::test(ListTags::class)
        ->callAction('create', [
            'name' => ['en' => 'Kenya'],
            'type_id' => $type->id,
        ]);

    expect(Tag::query()->where('type_id', $type->id)->get()->contains(
        fn (Tag $t) => $t->getTranslation('name', 'en') === 'Kenya'
    ))->toBeTrue();
});

it('resets tag type ordering to alphabetical via the bulk action', function () {
    $a = TagType::factory()->create(['order_column' => 3]);
    $b = TagType::factory()->create(['order_column' => 7]);

    Livewire::test(ListTagTypes::class)
        ->callTableBulkAction('resetOrder', [$a, $b]);

    expect($a->fresh()->order_column)->toBeNull()
        ->and($b->fresh()->order_column)->toBeNull();
});

it('persists the show_in_filter toggle from the edit form', function () {
    $tagType = TagType::factory()->create(['show_in_filter' => false]);

    Livewire::test(EditTagType::class, ['record' => $tagType->getKey()])
        ->set('data.show_in_filter', true);

    // The toggle is live with an afterStateUpdated hook that persists immediately.
    expect($tagType->fresh()->show_in_filter)->toBeTrue();
});
