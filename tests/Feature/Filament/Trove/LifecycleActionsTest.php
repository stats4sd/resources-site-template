<?php

use App\Filament\Resources\TroveResource\Pages\EditTrove;
use App\Models\Trove;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

beforeEach(fn () => $this->me = actingAsAdmin());

function shadowDraftFor(Trove $canonical, array $attributes = []): Trove
{
    return Trove::withoutSyncingToSearch(fn () => Trove::factory()->draftOf($canonical)->create($attributes));
}

it('mark_reviewed completes the review via TrovePublisher', function () {
    $canonical = publishedTrove();
    $draft = shadowDraftFor($canonical, ['review_requested_at' => now(), 'reviewer_id' => $this->me->id]);

    Livewire::test(EditTrove::class, ['record' => $draft->getKey()])
        ->callAction('mark_reviewed');

    $draft->refresh();
    expect($draft->reviewed_at)->not->toBeNull()
        ->and($draft->reviewer_id)->toBe($this->me->id);
});

it('discard_draft removes the shadow draft and keeps the canonical', function () {
    $canonical = publishedTrove(['title' => ['en' => 'Live Copy']]);
    $draft = shadowDraftFor($canonical, ['title' => ['en' => 'Draft Copy']]);

    Livewire::test(EditTrove::class, ['record' => $draft->getKey()])
        ->callAction('discard_draft');

    expect(Trove::withTrashed()->withDrafts()->find($draft->getKey()))->toBeNull()
        ->and($canonical->fresh()->getTranslation('title', 'en'))->toBe('Live Copy');
});

it('unpublish removes the canonical from the public site', function () {
    $canonical = publishedTrove();

    Livewire::test(EditTrove::class, ['record' => $canonical->getKey()])
        ->callAction('unpublish');

    expect(Trove::withDrafts()->find($canonical->getKey())->published_at)->toBeNull();
});

it('delete soft-deletes the canonical through TrovePublisher', function () {
    $canonical = publishedTrove();

    Livewire::test(EditTrove::class, ['record' => $canonical->getKey()])
        ->callAction(DeleteAction::class);

    expect(Trove::find($canonical->getKey()))->toBeNull()
        ->and(Trove::withTrashed()->withDrafts()->find($canonical->getKey())->trashed())->toBeTrue();
});
