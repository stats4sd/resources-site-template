<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\SetPasswordMail;
use App\Models\PasswordSetup;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // "Email a link" users have no password until they set one via the emailed link.
        // The password column is nullable, so leave it null — no usable credential exists.
        if (($this->data['password_method'] ?? null) === 'email_link') {
            $data['password'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Role is a dehydrated(false) form field, so it lives in the raw form state and is
        // applied here rather than being mass-assigned as a (non-existent) users column.
        $this->record->syncRoles([$this->data['role']]);

        if (($this->data['password_method'] ?? null) === 'email_link') {
            $setup = PasswordSetup::create(['user_id' => $this->record->id]);
            Mail::to($this->record->email)->send(new SetPasswordMail($setup));
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return ($this->data['password_method'] ?? null) === 'email_link'
            ? 'User created — password setup link sent'
            : 'User created';
    }
}
