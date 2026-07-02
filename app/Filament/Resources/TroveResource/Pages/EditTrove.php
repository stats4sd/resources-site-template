<?php

namespace App\Filament\Resources\TroveResource\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Resources\TroveResource;
use App\Filament\Resources\TroveResource\Concerns\HasTroveFormActions;
use App\Models\Trove;
use App\Services\TrovePublisher;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

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
        return 'Edit: ' . $this->record->title;
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
                ->visible(fn (Trove $record) => $record->review_status === ReviewStatus::InReview)
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
                ->visible(fn (Trove $record) => $record->review_status === ReviewStatus::PublishedWithPendingChanges)
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
                ->visible(fn (Trove $record) => $record->review_status === ReviewStatus::PublishedWithPendingChanges)
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // A live canonical only forks a shadow draft on the first save that actually
        // persists edits — opening the edit page no longer mutates state. Re-point
        // $this->record so Filament's relation/media persistence lands on the draft.
        if ($this->shouldForkOnSave($record)) {
            $record = app(TrovePublisher::class)->draftFor($record);
            $this->record = $record;
        }

        $record->update($data);

        return $record;
    }

    /**
     * Fork a shadow draft on save only when editing a live canonical without publishing:
     * Save draft and Request review must capture pending edits on the draft, never the
     * live copy. Publish (shouldPublish) folds straight back into the canonical, so
     * forking would be throwaway churn — publish in place instead.
     */
    protected function shouldForkOnSave(Model $record): bool
    {
        return ! $this->shouldPublish
            && $record->review_status === ReviewStatus::Published;
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
