<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Filament\Resources\CollectionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateCollection extends CreateRecord
{

    protected static string $resource = CollectionResource::class;

    protected static bool $canCreateAnother = false;

    public function getSubheading(): string|Htmlable
    {
        return 'Give the collection a title and brief description. You can select the troves to include in the collection after it is created.';
    }

    protected function getRedirectUrl(): string
    {
        return CollectionResource::getUrl('view', ['record' => $this->record]);
    }

}
