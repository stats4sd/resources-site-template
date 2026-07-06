<?php

namespace App\Filament\Resources\InviteResource\Pages;

use App\Filament\Resources\InviteResource;
use App\Mail\UserInviteMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class CreateInvite extends CreateRecord
{
    protected static string $resource = InviteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Stamp the inviting admin; token + expiry are set by Invite::creating().
        $data['invited_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        Mail::to($this->record->email)->send(new UserInviteMail($this->record));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Invitation sent';
    }
}
