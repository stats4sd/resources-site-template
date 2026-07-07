<?php

use App\Enums\InviteStatus;
use App\Enums\UserRole;
use App\Filament\Resources\InviteResource\Pages\CreateInvite;
use App\Filament\Resources\InviteResource\Pages\ListInvites;
use App\Mail\UserInviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('generates a unique 64-char token and a 7-day expiry on creation', function () {
    $invite = Invite::factory()->create(['expires_at' => null]);

    expect($invite->token)->toHaveLength(64)
        ->and($invite->expires_at->diffInDays(now()->addDays(7), true))->toBeLessThan(1)
        ->and($invite->status)->toBe(InviteStatus::Pending)
        ->and($invite->isUsable())->toBeTrue();
});

it('sends the invite email and stamps the inviter when created via the resource', function () {
    Mail::fake();
    $admin = actingAsAdmin();

    Livewire::test(CreateInvite::class)
        ->fillForm([
            'email' => 'newbie@example.com',
            'role' => UserRole::Editor->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invite = Invite::where('email', 'newbie@example.com')->firstOrFail();
    expect($invite->role)->toBe(UserRole::Editor)
        ->and($invite->invited_by)->toBe($admin->id);

    Mail::assertQueued(UserInviteMail::class, fn (UserInviteMail $mail) => $mail->hasTo('newbie@example.com'));
});

it('refreshes the token and expiry and re-sends mail on resend', function () {
    Mail::fake();
    actingAsAdmin();
    $invite = Invite::factory()->expired()->create();
    $oldToken = $invite->token;

    Livewire::test(ListInvites::class)
        ->callTableAction('resend', $invite);

    $invite->refresh();
    expect($invite->token)->not->toBe($oldToken)
        ->and($invite->expires_at->isFuture())->toBeTrue()
        ->and($invite->status)->toBe(InviteStatus::Pending);

    Mail::assertQueued(UserInviteMail::class);
});

it('hides the resend action for accepted invites', function () {
    actingAsAdmin();
    $accepted = Invite::factory()->accepted()->create();
    $pending = Invite::factory()->create();

    Livewire::test(ListInvites::class)
        ->assertTableActionHidden('resend', $accepted)
        ->assertTableActionVisible('resend', $pending);
});

it('rejects inviting an email that already belongs to a user', function () {
    actingAsAdmin();
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(CreateInvite::class)
        ->fillForm(['email' => 'taken@example.com', 'role' => UserRole::Editor->value])
        ->call('create')
        ->assertHasFormErrors(['email']);

    expect(Invite::where('email', 'taken@example.com')->exists())->toBeFalse();
});

it('rejects a duplicate pending invite for the same email', function () {
    actingAsAdmin();
    Invite::factory()->create(['email' => 'dupe@example.com']);

    Livewire::test(CreateInvite::class)
        ->fillForm(['email' => 'dupe@example.com', 'role' => UserRole::Editor->value])
        ->call('create')
        ->assertHasFormErrors(['email']);

    expect(Invite::where('email', 'dupe@example.com')->count())->toBe(1);
});

it('reports expired and accepted status correctly', function () {
    expect(Invite::factory()->expired()->create()->status)->toBe(InviteStatus::Expired)
        ->and(Invite::factory()->accepted()->create()->status)->toBe(InviteStatus::Accepted);
});
