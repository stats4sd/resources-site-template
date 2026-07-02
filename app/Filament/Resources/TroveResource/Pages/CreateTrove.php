<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Filament\Resources\TroveResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Guava\FilamentDrafts\Admin\Actions\SaveDraftAction;
use Guava\FilamentDrafts\Admin\Resources\Pages\Create\Draftable;

class CreateTrove extends CreateRecord
{
    use Draftable;

    protected static string $resource = TroveResource::class;
    protected static bool $canCreateAnother = false;
    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // override the default draftable form actions
    protected function getFormActions(): array
    {
        return [
            SaveDraftAction::make(),
            $this->getCancelFormAction(),
        ];
    }

}
