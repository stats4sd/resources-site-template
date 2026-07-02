<?php

namespace App\Filament\Forms\Components\Actions;

use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;

/**
 * In-form "save as draft" button for the Check step. Saves the record without
 * publishing by leaving the page's $shouldPublish flag false, then triggering the
 * page's normal create()/save() flow.
 */
class SaveDraftFormAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'save_draft';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('Save Draft'))
            ->action(function ($livewire) {
                $livewire->shouldPublish = false;

                if ($livewire instanceof CreateRecord) {
                    $livewire->create();
                }

                if ($livewire instanceof EditRecord) {
                    $livewire->save();
                }
            });
    }
}
