<?php

namespace App\Filament\Resources\HubResource\Pages;

use Filament\Actions;
use App\Filament\Resources\HubResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Translatable\TranslatableListView;

class ListHubs extends ListRecords
{
    use TranslatableListView;
    
    protected static string $resource = HubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\LocaleSwitcher::make(),
        ];
    }
}
