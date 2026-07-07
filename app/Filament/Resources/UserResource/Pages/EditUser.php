<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\SetPasswordMail;
use App\Models\PasswordSetup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

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
            // Only relevant while the user has never set a password (created via the "email a
            // link" option and not yet completed it). Once they have one, use the reset link above.
            Action::make('resendSetPasswordLink')
                ->label('Resend set-password link')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->visible(fn (): bool => $this->record->password === null)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => "Issue a fresh set-password link and email it to {$this->record->email}.")
                ->action(fn () => $this->resendSetPasswordLink()),
            DeleteAction::make(),
        ];
    }

    /**
     * (Re)issue a single-use set-password link for a user who has not set a password yet,
     * refreshing the token on any existing PasswordSetup so older links stop working.
     */
    protected function resendSetPasswordLink(): void
    {
        $setup = PasswordSetup::firstOrCreate(['user_id' => $this->record->id]);

        if (! $setup->wasRecentlyCreated) {
            $setup->refreshToken();
        }

        Mail::to($this->record->email)->send(new SetPasswordMail($setup));

        Notification::make()
            ->success()
            ->title('Set-password link sent')
            ->send();
    }

    /**
     * Use a non-submit action to add a confirmation modal if the user is changing their own email / role.
     * This potentially prevents an admin unsetting their own role and being unable to reset it.
     */
    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->action(fn () => $this->save())
            ->keyBindings(['mod+s'])
            ->modalHidden(fn (): bool => ! $this->isChangingOwnEmailOrRole())
            ->requiresConfirmation()
            ->modalHeading('Confirm changes to your own account')
            ->modalDescription('You are changing your own email address or role. Continue?')
            ->modalSubmitActionLabel('Save changes');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Hydrate the (non-column) role field from the user's assigned spatie role.
        $data['role'] = $this->record->roles->first()?->name;

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles([$this->data['role']]);
    }

    /**
     * Guards against accidentally locking yourself out by silently changing your own
     * login email or demoting your own role while editing another field. Compares the
     * current form state against the record's persisted (pre-save) values, which are
     * still intact when this runs at action-mount time.
     */
    protected function isChangingOwnEmailOrRole(): bool
    {
        if ($this->record->getKey() !== auth()->id()) {
            return false;
        }

        $currentRole = $this->record->roles->first()?->name;

        return ($this->data['email'] ?? null) !== $this->record->getOriginal('email')
            || ($this->data['role'] ?? null) !== $currentRole;
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
