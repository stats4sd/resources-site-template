<?php

namespace App\Filament\Pages\Auth;

use App\Models\PasswordSetup;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * The public "set your password" screen a newly-created user lands on from the SetPasswordMail
 * link. It reuses Filament's ResetPassword layout/form but replaces the Laravel password-reset
 * broker with our own single-use, expiring PasswordSetup token — no registration, no role, and
 * the account is identified by the token rather than a typed-in email.
 */
class SetPassword extends ResetPassword
{
    #[Url]
    public ?string $token = null;

    public ?PasswordSetup $setup = null;

    // Signature mirrors the parent; the token arrives via the #[Url] property, not these args.
    public function mount(?string $email = null, ?string $token = null): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->setup = filled($this->token)
            ? PasswordSetup::with('user')->where('token', $this->token)->first()
            : null;

        if (! $this->setup?->isUsable() || ! $this->setup->user) {
            $this->redirectInvalid();

            return;
        }

        // Identity comes from the token; the email is shown read-only for reassurance only.
        $this->email = $this->setup->user->email;

        $this->form->fill([
            'email' => $this->email,
        ]);
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        // Re-check between page load and submit — the link may have expired or been used since.
        if (! $this->setup?->isUsable() || ! $this->setup->user) {
            $this->redirectInvalid();

            return null;
        }

        $data = $this->form->getState();

        $user = $this->setup->user;
        $user->forceFill([
            $user->getAuthPasswordName() => Hash::make($data['password']),
            $user->getRememberTokenName() => Str::random(60),
        ])->save();

        $this->setup->markUsed();

        Notification::make()
            ->title(__('Your password has been set. You can now log in.'))
            ->success()
            ->send();

        return app(PasswordResetResponse::class);
    }

    protected function redirectInvalid(): void
    {
        Notification::make()
            ->title(__('This link is invalid or has expired.'))
            ->danger()
            ->persistent()
            ->send();

        $this->redirect(Filament::getLoginUrl());
    }

    public function getTitle(): string|Htmlable
    {
        return __('Set your password');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Set your password');
    }
}
