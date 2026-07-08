<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use App\Models\Invite;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Attributes\Url;
use SensitiveParameter;

/**
 * Registration is either invite-driven (a token in the URL) or open (the open_registration
 * site setting). Invite tokens carry the role to assign; open registrants become viewers.
 */
class Register extends BaseRegister
{
    #[Url]
    public ?string $token = null;

    public ?Invite $invite = null;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        if (filled($this->token)) {
            $this->invite = Invite::where('token', $this->token)->first();

            if (! $this->invite || ! $this->invite->isUsable()) {
                $this->redirectToLogin(__('This invitation is invalid or has expired.'), 'danger');

                return;
            }

            // The email may have been claimed after the invite was sent (e.g. created
            // manually). Reject gracefully rather than hitting the unique constraint.
            if (User::where('email', $this->invite->email)->exists()) {
                $this->redirectToLogin(__('An account for this email address already exists. Please log in.'), 'danger');

                return;
            }
        } elseif (! SiteSetting::instance()->open_registration) {
            $this->redirectToLogin(__('Registration is by invitation only.'), 'warning');

            return;
        }

        $this->callHook('beforeFill');

        $this->form->fill($this->invite ? ['email' => $this->invite->email] : []);

        $this->callHook('afterFill');
    }

    protected function getEmailFormComponent(): Component
    {
        $component = parent::getEmailFormComponent();

        // For invites the address is fixed to the one that was invited.
        if ($this->invite) {
            $component->readOnly();
        }

        return $component;
    }

    public function register(): ?RegistrationResponse
    {
        // Everything checked at mount() can lapse before submit — the invite may expire
        // or be used, the email claimed, or open registration switched off. Re-check,
        // mirroring SetPassword::resetPassword().
        if ($this->invite) {
            if (! $this->invite->isUsable()) {
                $this->redirectToLogin(__('This invitation is invalid or has expired.'), 'danger');

                return null;
            }

            if (User::where('email', $this->invite->email)->exists()) {
                $this->redirectToLogin(__('An account for this email address already exists. Please log in.'), 'danger');

                return null;
            }
        } elseif (! SiteSetting::instance()->open_registration) {
            $this->redirectToLogin(__('Registration is by invitation only.'), 'warning');

            return null;
        }

        try {
            return parent::register();
        } catch (UniqueConstraintViolationException) {
            $this->redirectToLogin(__('An account for this email address already exists. Please log in.'), 'danger');

            return null;
        }
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        // Never trust a tampered email field on an invite — pin it to the invite.
        if ($this->invite) {
            $data['email'] = $this->invite->email;
        }

        /** @var User $user */
        $user = $this->getUserModel()::create($data);

        $role = $this->invite?->role?->value ?? UserRole::Viewer->value;
        $user->assignRole($role);

        $this->invite?->markAccepted();

        return $user;
    }

    protected function redirectToLogin(string $message, string $status): void
    {
        $notification = Notification::make()->title($message);
        $status === 'warning' ? $notification->warning() : $notification->danger();
        $notification->persistent()->send();

        $this->redirect(Filament::getLoginUrl());
    }
}
