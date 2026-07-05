<?php

use App\Filament\Resources\TroveResource\Pages\ListTroves;
use App\Models\Trove;
use App\Models\User;
use Livewire\Livewire;

function draftFor(Trove $canonical, array $attributes = []): Trove
{
    return Trove::withoutSyncingToSearch(fn () => Trove::factory()->draftOf($canonical)->create($attributes));
}

beforeEach(function () {
    $this->me = actingAsAdmin();

    $this->published = publishedTrove();          // Published, no draft
    $this->neverPublished = draftTrove();          // Draft

    $pcCanonical = publishedTrove();
    $this->pendingDraft = draftFor($pcCanonical);  // PendingChanges working row

    $reviewCanonical = publishedTrove();
    $this->reviewDraft = draftFor($reviewCanonical, [
        'review_requested_at' => now(),
        'reviewer_id' => $this->me->id,
    ]);                                            // PendingChanges + InReview, assigned to me
});

it('shows every working version on the All tab', function () {
    Livewire::test(ListTroves::class)
        ->assertCanSeeTableRecords([$this->published, $this->neverPublished, $this->pendingDraft, $this->reviewDraft])
        ->assertCountTableRecords(4);
});

it('shows drafts and pending changes not under review on the Drafts tab', function () {
    Livewire::test(ListTroves::class)
        ->set('activeTab', 'drafts')
        ->assertCanSeeTableRecords([$this->neverPublished, $this->pendingDraft])
        ->assertCanNotSeeTableRecords([$this->published, $this->reviewDraft]);
});

it('shows only in-review working rows on the In review tab', function () {
    Livewire::test(ListTroves::class)
        ->set('activeTab', 'in_review')
        ->assertCanSeeTableRecords([$this->reviewDraft])
        ->assertCountTableRecords(1);
});

it('shows the current user queue on the Needs my review tab, matching awaitingReviewBy', function () {
    expect(Trove::awaitingReviewBy($this->me->id)->count())->toBe(1);

    Livewire::test(ListTroves::class)
        ->set('activeTab', 'needs_my_review')
        ->assertCanSeeTableRecords([$this->reviewDraft])
        ->assertCountTableRecords(1);
});

it('excludes another users review queue from Needs my review', function () {
    // A review assigned to someone else should not appear in my queue.
    draftFor(publishedTrove(), ['review_requested_at' => now(), 'reviewer_id' => User::factory()->create()->id]);

    Livewire::test(ListTroves::class)
        ->set('activeTab', 'needs_my_review')
        ->assertCountTableRecords(1); // still just my own
});

it('shows live troves and their pending drafts on the Published tab', function () {
    Livewire::test(ListTroves::class)
        ->set('activeTab', 'published')
        ->assertCanSeeTableRecords([$this->published, $this->pendingDraft, $this->reviewDraft])
        ->assertCanNotSeeTableRecords([$this->neverPublished]);
});
