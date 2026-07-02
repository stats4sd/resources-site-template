<?php

namespace App\Filament\Resources\TroveResource\Concerns;

use App\Models\User;
use App\Services\TrovePublisher;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;

/**
 * The three explicit, self-describing footer actions shared by CreateTrove and EditTrove:
 * Save draft, Request review, Publish. The user's intent is the button they press — no
 * radio value cross-referenced against visibility-toggled fieldsets (that drift is what
 * we removed). Each action drives the page's normal save pipeline via a public flag, so
 * the persistence lives in one place (afterCreate()/afterSave() → finalizeTroveSave()).
 */
trait HasTroveFormActions
{
    /** Set by the Publish action; false means save-as-draft. */
    public bool $shouldPublish = false;

    /** Set by the Request review action to the assigned reviewer's id. */
    public ?int $reviewerIdToRequest = null;

    /** Remembered across the save so the saved-notification title matches what happened. */
    protected bool $justPublished = false;
    protected bool $justRequestedReview = false;

    protected function getFormActions(): array
    {
        return [
            $this->publishAction(),
            $this->requestReviewAction(),
            $this->saveDraftAction(),
        ];
    }

    /** Route the action through the page's own create()/save() so hooks fire normally. */
    protected function triggerTroveSave(): void
    {
        $this instanceof CreateRecord ? $this->create() : $this->save();
    }

    protected function saveDraftAction(): Action
    {
        return Action::make('save_draft')
            ->label(__('Save draft'))
            ->icon('heroicon-m-inbox-arrow-down')
            ->color('gray')
            ->action(function () {
                $this->shouldPublish = false;
                $this->reviewerIdToRequest = null;
                $this->triggerTroveSave();
            });
    }

    protected function requestReviewAction(): Action
    {
        return Action::make('request_review')
            ->label(__('Request review'))
            ->icon('heroicon-m-clipboard-document-check')
            ->color('info')
            ->modalHeading(__('Request a review'))
            ->modalDescription(__('Two pairs of eyes are better than one. Pick someone to review this trove — it appears in their "Needs my review" queue.'))
            ->form([
                Select::make('reviewer_id')
                    ->label(__('Who should review this?'))
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('Request review'))
            ->action(function (array $data) {
                $this->shouldPublish = false;
                $this->reviewerIdToRequest = (int) $data['reviewer_id'];
                $this->triggerTroveSave();
            });
    }

    protected function publishAction(): Action
    {
        return Action::make('publish')
            ->label(fn () => $this->publishLabel())
            ->icon('heroicon-m-globe-alt')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn () => $this->publishLabel())
            ->modalDescription(fn () => $this->reviewedAlready()
                ? __('This trove has been reviewed. Publish it to the live site?')
                : __('No one has reviewed this trove yet. We recommend a review — but you can publish anyway if you are confident.'))
            // When unreviewed, require an explicit "publish without a review" confirmation;
            // when reviewed, a plain confirm. Publish is never blocked (optionality).
            ->form(fn () => $this->reviewedAlready() ? [] : [
                Checkbox::make('confirm_publish')
                    ->label(__('Publish without a review'))
                    ->accepted()
                    ->required(),
            ])
            ->modalSubmitActionLabel(fn () => $this->publishLabel())
            ->action(function () {
                $this->reviewerIdToRequest = null;
                $this->shouldPublish = true;
                $this->triggerTroveSave();
            });
    }

    protected function publishLabel(): string
    {
        return $this->record?->has_published_version ? __('Publish changes') : __('Publish');
    }

    protected function reviewedAlready(): bool
    {
        return $this->record?->reviewed_at !== null;
    }

    /**
     * Shared tail of afterCreate()/afterSave(): persist per-locale file names, then publish
     * or record the requested review as chosen by the pressed footer action.
     */
    protected function finalizeTroveSave(): void
    {
        $formData = $this->form->getRawState();
        foreach (array_keys(config('branding.locales', ['en' => 'English'])) as $locale) {
            $name = $formData["file_name_{$locale}"] ?? null;
            if ($name) {
                $this->record->getMedia("content_{$locale}")->each(fn ($m) => $m->update(['name' => $name]));
            }
        }

        $this->justPublished = $this->shouldPublish;
        if ($this->shouldPublish) {
            app(TrovePublisher::class)->publish($this->record);
        }
        $this->shouldPublish = false;

        $this->justRequestedReview = $this->reviewerIdToRequest !== null;
        if ($this->reviewerIdToRequest !== null) {
            if ($reviewer = User::find($this->reviewerIdToRequest)) {
                app(TrovePublisher::class)->requestReview($this->record, $reviewer);
            }
            $this->reviewerIdToRequest = null;
        }
    }

    protected function troveSavedNotificationTitle(): string
    {
        return match (true) {
            $this->justRequestedReview => __('Review requested'),
            $this->justPublished => __('Published'),
            default => __('Draft saved'),
        };
    }
}
