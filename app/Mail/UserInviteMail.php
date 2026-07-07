<?php

namespace App\Mail;

use App\Models\Invite;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInviteMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Invite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been invited to :org', ['org' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.user-invite',
            with: [
                'appName' => config('app.name'),
                'orgName' => config('branding.org_name'),
                'registerUrl' => $this->registerUrl(),
                'expiresAt' => $this->invite->expires_at,
            ],
        );
    }

    protected function registerUrl(): string
    {
        return Filament::getPanel('admin')->getRegistrationUrl(['token' => $this->invite->token])
            ?? route('filament.admin.auth.register', ['token' => $this->invite->token]);
    }
}
