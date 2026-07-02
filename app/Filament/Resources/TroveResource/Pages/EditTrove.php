<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Services\TrovePublisher;
use App\Filament\Forms\Components\Actions\SaveDraftFormAction;
use App\Filament\Resources\TroveResource;
use App\Models\Trove;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class EditTrove extends EditRecord
{
    protected static string $resource = TroveResource::class;
    public static string|Alignment $formActionsAlignment = Alignment::End;

    /** Set by the Check-step "Save and Publish" action; false means save-as-draft. */
    public bool $shouldPublish = false;

    /** Remembers, for the saved-notification title, whether this save published. */
    protected bool $justPublished = false;

    /**
     * Editing a live Trove must not disturb the public copy, so the moment a published
     * canonical row is opened we fork (or reuse) its single shadow draft and edit that
     * instead. From then on every field, relation and media edit targets the draft row.
     */
    public function mount($record): void
    {
        parent::mount($record);

        $trove = $this->getRecord();

        if ($trove->published_id === null && $trove->published_at !== null) {
            $draft = app(TrovePublisher::class)->draftFor($trove);
            $this->redirect(TroveResource::getUrl('edit', ['record' => $draft->getKey()]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getHeading(): string|Htmlable
    {
        return 'Edit: ' . $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('discard_draft')
                ->label('Discard draft changes')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (Trove $record) => $record->published_id !== null)
                ->requiresConfirmation()
                ->modalDescription('This discards the unpublished changes and keeps the live version as it is.')
                ->action(function (Trove $record) {
                    app(TrovePublisher::class)->discardDraft($record);
                    Notification::make()->title('Draft changes discarded')->success()->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),
            Actions\Action::make('unpublish')
                ->label('Unpublish')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn (Trove $record) => $record->published_id !== null)
                ->requiresConfirmation()
                ->modalDescription('This removes the trove from the public site (and discards any draft changes). It is not deleted.')
                ->action(function (Trove $record) {
                    app(TrovePublisher::class)->unpublish($record->publishedVersion);
                    Notification::make()->title('Trove unpublished')->success()->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /** Stamp the requester when a checker is being assigned (review 1.2). */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['checker_id']) && empty($data['requester_id'])) {
            $data['requester_id'] = auth()->id();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    protected function afterSave(): void
    {
        // Persist per-locale content file names onto the (draft) record's media.
        $formData = $this->form->getRawState();
        foreach (array_keys(config('branding.locales', ['en' => 'English'])) as $locale) {
            $name = $formData["file_name_{$locale}"] ?? null;
            if ($name) {
                $this->record->getMedia("content_{$locale}")->each(fn ($m) => $m->update(['name' => $name]));
            }
        }

        // Fold the now fully-saved draft (fields + relations + media) onto its canonical.
        $this->justPublished = $this->shouldPublish;
        if ($this->shouldPublish) {
            app(TrovePublisher::class)->publish($this->record);
        }

        $this->shouldPublish = false;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->justPublished ? 'Published' : 'Draft saved';
    }

}
