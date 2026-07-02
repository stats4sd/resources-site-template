<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Filament\Resources\TroveResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Kainiklas\FilamentScout\Traits\InteractsWithScout;

class ListTroves extends ListRecords
{
    use ListRecords\Concerns\Translatable;
    use InteractsWithScout;

    protected static string $resource = TroveResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\LocaleSwitcher::make(),
        ];
    }

    public function getTabs(): array
    {
        $needsMyReview = \App\Models\Trove::query()->awaitingReviewBy(auth()->id())->count();

        return [
            // One editable row per logical Trove: the draft if one exists, else the canonical.
            'all' => Tab::make()
                ->label(__('All'))
                ->modifyQueryUsing(fn (Builder $query) => $query->workingVersions()),
            // Unpublished working versions with no review outstanding.
            'drafts' => Tab::make()
                ->label(__('Drafts'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->workingVersions()
                    ->whereNull('published_at')
                    ->whereNull('review_requested_at')),
            // Working versions with a review currently outstanding.
            'in_review' => Tab::make()
                ->label(__('In review'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->workingVersions()
                    ->whereNotNull('review_requested_at')
                    ->whereNull('reviewed_at')),
            // The current user's personal queue of reviews to action.
            'needs_my_review' => Tab::make()
                ->label(__('Needs my review'))
                ->badge($needsMyReview ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingReviewBy(auth()->id())),
            // The live public rows.
            'published' => Tab::make()
                ->label(__('Published'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->withDrafts()
                    ->whereNull('published_id')
                    ->whereNotNull('published_at')),
        ];
    }
}
