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
        return [
            // One editable row per logical Trove: the draft if one exists, else the canonical.
            'all' => Tab::make()
                ->label(__('All'))
                ->modifyQueryUsing(fn (Builder $query) => $query->workingVersions()),
            // The live public rows.
            'published' => Tab::make()
                ->label(__('Published'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->withDrafts()
                    ->whereNull('published_id')
                    ->whereNotNull('published_at')),
            // Unpublished working versions (never-published canonicals + shadow drafts).
            'drafts' => Tab::make()
                ->label(__('Drafts'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->workingVersions()
                    ->whereNull('published_at')),
            // Drafts that have had a checker assigned.
            'review' => Tab::make()
                ->label(__('Check Requested'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->workingVersions()
                    ->whereNull('published_at')
                    ->whereNotNull('checker_id')),
        ];
    }
}
