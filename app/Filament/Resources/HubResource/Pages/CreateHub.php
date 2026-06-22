<?php

namespace App\Filament\Resources\HubResource\Pages;

use Filament\Actions;
use App\Filament\Resources\HubResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateHub extends CreateRecord
{
    protected static string $resource = HubResource::class;
    protected static bool $canCreateAnother = false;

    public function getSubheading(): string|Htmlable
    {
        return 'TODO add instructions';
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
