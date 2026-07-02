<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Filament\Resources\TroveResource;
use App\Filament\Resources\TroveResource\Concerns\HasTroveFormActions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Model;

class CreateTrove extends CreateRecord
{
    use HasTroveFormActions;

    protected static string $resource = TroveResource::class;
    protected static bool $canCreateAnother = false;
    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        // A new Trove is a canonical row; it starts unpublished (published_at null).
        // Publishing / review requests are applied in afterCreate(), once relations and
        // media have been saved.
        return static::getModel()::create($data);
    }

    protected function afterCreate(): void
    {
        $this->finalizeTroveSave();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->troveSavedNotificationTitle();
    }
}
