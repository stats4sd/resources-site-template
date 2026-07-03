<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Filament\Resources\TroveResource;
use App\Models\Trove;
use App\Services\TrovePublisher;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTrove extends ViewRecord
{
    protected static string $resource = TroveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->using(fn (Trove $record) => app(TrovePublisher::class)->delete($record)),
        ];

    }
}
