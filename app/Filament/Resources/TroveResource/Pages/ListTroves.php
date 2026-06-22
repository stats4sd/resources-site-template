<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Filament\Resources\TroveResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Guava\FilamentDrafts\Admin\Resources\Pages\List\Draftable;
use Illuminate\Database\Eloquent\Builder;
use Kainiklas\FilamentScout\Traits\InteractsWithScout;

class ListTroves extends ListRecords
{
    use ListRecords\Concerns\Translatable;
    use Draftable;
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
            'all' => Tab::make()
                ->label(__('filament-drafts::tables.all-table.title'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_current', true)),
            'published' => Tab::make()
                ->label(__('Published'))
                ->modifyQueryUsing(fn(Builder $query) => $query->withoutDrafts()),
            'drafts' => Tab::make()
                ->label(__('filament-drafts::tables.draftable-table.title'))
                ->modifyQueryUsing(fn(Builder $query) => $query->onlyDrafts()->where('is_current', true)),
            'review' => Tab::make()
                ->label(__('Check Requested'))
                ->modifyQueryUsing(fn(Builder $query) => $query->onlyDrafts()->where('is_current', true)
                    ->where('checker_id', '!=', null)),
        ];
    }
}
