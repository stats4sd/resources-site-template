<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\InviteResource;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('inviteUser')
                ->label('Invite user')
                ->icon('heroicon-o-envelope')
                ->url(InviteResource::getUrl('create')),
            Actions\CreateAction::make(),
        ];
    }
}
