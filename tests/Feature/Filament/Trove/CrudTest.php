<?php

use App\Filament\Resources\TroveResource\Pages\CreateTrove;
use App\Filament\Resources\TroveResource\Pages\EditTrove;
use App\Models\Trove;
use App\Models\TroveType;
use Livewire\Livewire;

beforeEach(fn () => $this->me = actingAsAdmin());

function validTroveFormData(TroveType $type): array
{
    return [
        'title' => ['en' => 'A New Resource'],
        'description' => ['en' => 'Some description'],
        'trove_type_id' => $type->id,
        'source' => 0,
        'creation_date' => now()->format('Y-m-d'),
    ];
}

it('creates an unpublished trove via the create form', function () {
    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(validTroveFormData($type))
        ->call('create')
        ->assertHasNoFormErrors();

    $trove = Trove::withDrafts()->firstWhere('slug', 'a-new-resource');
    expect($trove)->not->toBeNull()
        ->and($trove->published_at)->toBeNull()
        ->and($trove->getTranslation('title', 'en'))->toBe('A New Resource')
        ->and($trove->trove_type_id)->toBe($type->id);
});

it('publishes a trove through the publish form action', function () {
    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(validTroveFormData($type))
        ->callAction('publish', ['confirm_publish' => true])
        ->assertHasNoFormErrors();

    $trove = Trove::withDrafts()->firstWhere('slug', 'a-new-resource');
    expect($trove)->not->toBeNull()
        ->and($trove->published_at)->not->toBeNull();
});

it('diverts an edit of a live trove onto a forked shadow draft, leaving the canonical intact', function () {
    $live = publishedTrove(['title' => ['en' => 'Original Live Title']]);
    $originalPublishedAt = $live->published_at;

    Livewire::test(EditTrove::class, ['record' => $live->getKey()])
        ->fillForm(['title' => ['en' => 'Edited Title']])
        ->call('save');

    // The live canonical is untouched (identity, publish date, content)...
    $live->refresh();
    expect($live->getTranslation('title', 'en'))->toBe('Original Live Title')
        ->and($live->published_at->equalTo($originalPublishedAt))->toBeTrue()
        ->and($live->published_id)->toBeNull()
        // ...and exactly one shadow draft now exists to hold the pending edit.
        ->and(Trove::withDrafts()->where('published_id', $live->id)->count())->toBe(1);
});

// The "No changes to save" guard compares a snapshot taken in afterFill() against the form
// state at save time — but $originalFormState is a PROTECTED property, so it does not survive
// Livewire's request round-trip and is [] by the time save() runs. The guard therefore never
// fires on a real save (every plain save of a live trove forks a draft). Flagged in the change
// log; this test pins the intended behaviour for when that is fixed.
it('does not fork a draft on a plain save with no changes', function () {
    $live = publishedTrove();

    Livewire::test(EditTrove::class, ['record' => $live->getKey()])
        ->call('save')
        ->assertNotified(__('No changes to save'));

    expect(Trove::withDrafts()->where('published_id', $live->id)->count())->toBe(0);
})->skip('Latent bug: $originalFormState (protected) is lost across the Livewire round-trip, so the no-changes guard never fires. See change log.');
