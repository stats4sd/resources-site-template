<?php

namespace App\Filament\Resources\TroveTypeResource\Pages;

use App\Filament\Resources\TroveTypeResource;
use App\Filament\Translatable\TranslatableListView;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;

class ListTroveTypes extends ManageRecords
{

    use TranslatableListView;

    protected static string $resource = TroveTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalHeading(''),
            Actions\LocaleSwitcher::make(),
        ];
    }

}
