<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $originalEmail = null;

    protected ?string $originalRole = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendPasswordReset')
                ->label('Send password reset link')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => "Email a password reset link to {$this->record->email}.")
                ->action(fn () => $this->sendPasswordResetLink()),
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->requiresConfirmation(fn (): bool => $this->isChangingOwnEmailOrRole())
                ->modalHeading('Confirm changes to your own account')
                ->modalDescription('You are changing your own email address or role. Continue?')
                ->modalSubmitActionLabel('Save changes'),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Hydrate the (non-column) role field from the user's assigned spatie role.
        $data['role'] = $this->record->roles->first()?->name;

        $this->originalEmail = $this->record->email;
        $this->originalRole = $data['role'];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles([$this->data['role']]);
    }

    /**
     * Guards against accidentally locking yourself out by silently changing your own
     * login email or demoting your own role while editing another field.
     */
    protected function isChangingOwnEmailOrRole(): bool
    {
        if ($this->record->id !== auth()->id()) {
            return false;
        }

        return $this->data['email'] !== $this->originalEmail
            || $this->data['role'] !== $this->originalRole;
    }

    /**
     * Mirror Filament's own password-reset request flow so the emailed link points at the
     * panel's reset route (Filament::getResetPasswordUrl) rather than Laravel's default.
     */
    protected function sendPasswordResetLink(): void
    {
        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $this->record->email],
            function (CanResetPassword $user, string $token): void {
                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = Filament::getResetPasswordUrl($token, $user);
                $user->notify($notification);
            },
        );

        if ($status === Password::RESET_LINK_SENT) {
            Notification::make()
                ->success()
                ->title('Password reset link sent')
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title('Could not send reset link')
            ->body(__($status))
            ->send();
    }
}
