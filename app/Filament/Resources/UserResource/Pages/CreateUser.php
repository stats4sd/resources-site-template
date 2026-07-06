<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Role is a dehydrated(false) form field, so it lives in the raw form state and is
        // applied here rather than being mass-assigned as a (non-existent) users column.
        $this->record->syncRoles([$this->data['role']]);
    }
}
