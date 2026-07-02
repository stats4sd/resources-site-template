<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Services\TrovePublisher;
use App\Filament\Forms\Components\Actions\SaveDraftFormAction;
use App\Filament\Resources\TroveResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Model;

class CreateTrove extends CreateRecord
{
    protected static string $resource = TroveResource::class;
    protected static bool $canCreateAnother = false;
    public static string|Alignment $formActionsAlignment = Alignment::End;

    /** Set by the Check-step "Save and Publish" action; false means save-as-draft. */
    public bool $shouldPublish = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** Stamp the requester when a checker is being assigned (review 1.2). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['checker_id']) && empty($data['requester_id'])) {
            $data['requester_id'] = auth()->id();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // A new Trove is a canonical row; it starts unpublished (published_at null).
        $record = static::getModel()::create($data);

        if ($this->shouldPublish) {
            app(TrovePublisher::class)->publish($record);
        }

        return $record;
    }

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();
        foreach (array_keys(config('branding.locales', ['en' => 'English'])) as $locale) {
            $name = $data["file_name_{$locale}"] ?? null;
            if ($name) {
                $this->record->getMedia("content_{$locale}")->each(fn ($m) => $m->update(['name' => $name]));
            }
        }
    }
}
