<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Enums\ReviewState;
use App\Enums\PublicationState;
use App\Filament\Resources\TroveResource;
use App\Models\Trove;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Kainiklas\FilamentScout\Traits\InteractsWithScout;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListTroves extends ListRecords
{
    use Translatable;
    use InteractsWithScout;

    protected static string $resource = TroveResource::class;

    protected \Filament\Support\Enums\Width|string|null $maxContentWidth = \Filament\Support\Enums\Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            LocaleSwitcher::make(),
        ];
    }

    public function getTabs(): array
    {
        $needsMyReview = Trove::query()->awaitingReviewBy(auth()->id())->count();

        return [
            // One editable row per logical Trove: the draft if one exists, else the canonical.
            'all' => Tab::make()
                ->label(__('All'))
                ->modifyQueryUsing(fn (Builder $query) => $query->workingVersions()),
            // Working versions still being drafted: never-published drafts and unpublished
            // edits to a live row (pending changes), that are not currently in review.
            // Composed from the two explicit axes — publication (Draft or PendingChanges)
            // and review (anything except an outstanding review).
            'drafts' => Tab::make()
                ->label(__('Drafts'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->workingVersions()
                    ->withPublicationState(PublicationState::Draft, PublicationState::PendingChanges)
                    ->withReviewState(ReviewState::None, ReviewState::Reviewed)),
            // Working versions with a review currently outstanding (pure review axis).
            'in_review' => Tab::make()
                ->label(__('In review'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->workingVersions()
                    ->withReviewState(ReviewState::InReview)),
            // The current user's personal queue of reviews to action.
            'needs_my_review' => Tab::make()
                ->label(__('Needs my review'))
                ->badge($needsMyReview ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingReviewBy(auth()->id())),
            // Working versions of live troves: the live row itself, and any pending-changes
            // draft of one (pure publication axis — a draft under review still counts).
            'published' => Tab::make()
                ->label(__('Published'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->workingVersions()
                    ->withPublicationState(PublicationState::Published, PublicationState::PendingChanges)),
        ];
    }
}
