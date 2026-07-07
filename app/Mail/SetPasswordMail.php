<?php

namespace App\Mail;

use App\Models\PasswordSetup;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public PasswordSetup $setup) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Set your password for :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.set-password',
            with: [
                'appName' => config('app.name'),
                'orgName' => config('branding.org_name'),
                'name' => $this->setup->user->name,
                'setPasswordUrl' => $this->setPasswordUrl(),
                'expiresAt' => $this->setup->expires_at,
            ],
        );
    }

    protected function setPasswordUrl(): string
    {
        return Filament::getPanel('admin')->route('auth.set-password', ['token' => $this->setup->token]);
    }
}
