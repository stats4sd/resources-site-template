<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Enums\PublicationState;
use App\Enums\ReviewState;
use App\Filament\Resources\TroveResource;
use App\Filament\Resources\TroveResource\Concerns\HasTroveFormActions;
use App\Models\Trove;
use App\Services\TrovePublisher;
use Filament\Actions;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditTrove extends EditRecord
{
    use HasTroveFormActions;

    protected static string $resource = TroveResource::class;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getHeading(): string|Htmlable
    {
        return 'Edit: '.$this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Complete an outstanding review: records whoever ACTUALLY reviewed as the
            // approver (often not the assignee), then stamps the durable "✓ reviewed" fact.
            Actions\Action::make('mark_reviewed')
                ->label('Mark as reviewed')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Trove $record) => $record->review_state === ReviewState::InReview)
                ->requiresConfirmation()
                ->modalDescription('This records that you have reviewed and approved this trove. It does not publish it.')
                ->action(function (Trove $record) {
                    app(TrovePublisher::class)->completeReview($record, auth()->user());
                    Notification::make()->title('Review completed')->success()->send();

                    return redirect($this->getResource()::getUrl('edit', ['record' => $record->getKey()]));
                }),
            Actions\Action::make('discard_draft')
                ->label('Discard draft changes')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (Trove $record) => $record->publication_state === PublicationState::PendingChanges)
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
                ->visible(fn (Trove $record) => $record->publication_state === PublicationState::PendingChanges || PublicationState::Published)
                ->requiresConfirmation()
                ->modalDescription('This removes the trove from the public site. It is not deleted.')
                ->action(function (Trove $record) {
                    app(TrovePublisher::class)->unpublish($record->publishedVersion);
                    Notification::make()->title('Trove unpublished')->success()->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Snapshot of the form's meaningful state (scalars, translatable arrays, relation ID
     * sets, media UUID maps) taken right after the form is filled, used to decide whether
     * a plain Save on a live trove actually changed anything before forking a draft.
     */
    protected array $originalFormState = [];

    protected function afterFill(): void
    {
        $this->originalFormState = $this->troveFormStateSnapshot();
    }

    /**
     * Fork the shadow draft in save() — BEFORE parent::save() runs the form's
     * getState()/saveRelationships(), which otherwise persists the edited relations and
     * media onto the form's bound model (the live canonical). Only a live canonical needs
     * forking, and only when NOT publishing: Publish folds straight into the canonical, and
     * a draft / never-published row already targets the correct record.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $isLiveCanonical = $this->getRecord()->publication_state === PublicationState::Published;
        $isPlainSave = ! $this->shouldPublish && $this->reviewerIdToRequest === null;

        // A plain Save that changed nothing must not fork a throwaway draft. Request review
        // and Publish deliberately proceed even with no content change (the review handshake
        // belongs on a working row; publish still publishes in place).
        if ($isLiveCanonical && $isPlainSave && ! $this->troveFormIsDirty()) {
            Notification::make()->title(__('No changes to save'))->info()->send();

            return;
        }

        if ($isLiveCanonical && ! $this->shouldPublish) {
            $this->forkToDraftAndRebind();
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function troveFormIsDirty(): bool
    {
        return $this->troveFormStateSnapshot() !== $this->originalFormState;
    }

    /**
     * Normalized snapshot of the entire form data. Because TranslatableComboField stores
     * the full locale array in one place and this page does no active-locale buffering, the
     * whole meaningful state lives in $this->data — so this auto-covers tags, troveTypes,
     * media, cover images, translations and any field added later, with no list to maintain.
     * TemporaryUploadedFile instances are collapsed to a marker so a new upload always reads
     * as a difference (there are none at fill time) without depending on object identity.
     */
    protected function troveFormStateSnapshot(): array
    {
        return $this->normalizeFormState($this->data);
    }

    protected function normalizeFormState(mixed $state): mixed
    {
        if ($state instanceof TemporaryUploadedFile) {
            return '__new_upload__';
        }

        if (is_array($state)) {
            return array_map(fn ($value) => $this->normalizeFormState($value), $state);
        }

        return $state;
    }

    /**
     * Fork the canonical's shadow draft and re-point the page + form at it so all of
     * parent::save()'s persistence (record update, relation sync, media) lands on the draft.
     */
    protected function forkToDraftAndRebind(): void
    {
        $canonical = $this->getRecord();
        $mediaUuidMap = [];
        $draft = app(TrovePublisher::class)->draftFor($canonical, $mediaUuidMap);

        $this->record = $draft;      // handleRecordUpdate(getRecord(), …) now targets the draft
        $this->form->model($draft);  // relation Selects + media inherit via the container model
        $this->remapMediaState($mediaUuidMap);
    }

    /**
     * Rewrite each media component's state from the canonical's UUIDs to the draft-copy
     * UUIDs. Without this, saveRelationships() on the draft would see none of the draft's
     * (new-UUID) media in the state and delete all of it as "abandoned". New uploads pass
     * through untouched; files the user removed stay absent so their draft copy is deleted.
     *
     * @param  array<string, array<string, string>>  $map
     */
    protected function remapMediaState(array $map): void
    {
        foreach ($this->form->getFlatFields(withHidden: true) as $component) {
            if (! $component instanceof SpatieMediaLibraryFileUpload) {
                continue;
            }

            $collMap = $map[$component->getCollection()] ?? [];
            $new = [];
            $reload = false;

            foreach ($component->getState() ?? [] as $key => $value) {
                if ($value instanceof TemporaryUploadedFile) {
                    $new[$key] = $value;   // genuinely new upload; leave untouched

                    continue;
                }

                if (isset($collMap[$value])) {
                    $new[$collMap[$value]] = $collMap[$value];   // kept file -> draft copy UUID

                    continue;
                }

                // Canonical UUID with no draft counterpart (existing-draft divergence):
                // the map can't describe this collection, so reload it from the draft.
                $reload = true;
                break;
            }

            if ($reload) {
                $new = $this->getRecord()->getMedia($component->getCollection())
                    ->mapWithKeys(fn ($media) => [$media->uuid => $media->uuid])
                    ->all();

                // Preserve any brand-new uploads the user added this session.
                foreach ($component->getState() ?? [] as $key => $value) {
                    if ($value instanceof TemporaryUploadedFile) {
                        $new[$key] = $value;
                    }
                }
            }

            $component->state($new);
        }
    }

    protected function afterSave(): void
    {
        $this->finalizeTroveSave();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->troveSavedNotificationTitle();
    }
}
