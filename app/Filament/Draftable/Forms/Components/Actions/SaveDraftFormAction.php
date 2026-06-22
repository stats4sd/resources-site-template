<?php

namespace App\Filament\Draftable\Forms\Components\Actions;

use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;

class SaveDraftFormAction extends Action
{

    public static function getDefaultName(): ?string
    {
        return 'draft';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->action($this->draft(...))
            ->label(__('filament-drafts::actions.save-draft'));
    }

    protected function draft(): void
    {
        $livewire = $this->getLivewire();
        $livewire->shouldSaveAsDraft = true;

        if ($livewire instanceof CreateRecord) {
            $livewire->create();
        }

        if ($livewire instanceof EditRecord) {
            $livewire->save();
        }
    }

}
