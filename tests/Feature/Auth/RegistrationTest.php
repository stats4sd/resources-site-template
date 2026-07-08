<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Register;
use App\Models\Invite;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function fillRegistration($component, string $email)
{
    return $component->fillForm([
        'name' => 'New Person',
        'email' => $email,
        'password' => 'password',
        'passwordConfirmation' => 'password',
    ]);
}

it('registers a user with the invite role and stamps the invite as accepted', function () {
    $invite = Invite::factory()->role(UserRole::Editor)->create(['email' => 'invitee@example.com']);

    $component = Livewire::test(Register::class, ['token' => $invite->token]);
    fillRegistration($component, 'invitee@example.com')
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'invitee@example.com')->firstOrFail();
    expect($user->hasRole(UserRole::Editor->value))->toBeTrue()
        ->and($invite->fresh()->accepted_at)->not->toBeNull();
});

it('assigns the exact role carried by the invite', function () {
    $invite = Invite::factory()->role(UserRole::Admin)->create(['email' => 'boss@example.com']);

    $component = Livewire::test(Register::class, ['token' => $invite->token]);
    fillRegistration($component, 'boss@example.com')->call('register')->assertHasNoFormErrors();

    expect(User::where('email', 'boss@example.com')->firstOrFail()->hasRole(UserRole::Admin->value))->toBeTrue();
});

it('redirects an expired token to login without registering', function () {
    $invite = Invite::factory()->expired()->create();

    Livewire::test(Register::class, ['token' => $invite->token])
        ->assertRedirect(Filament::getLoginUrl());

    expect(User::where('email', $invite->email)->exists())->toBeFalse();
});

it('redirects an already-accepted (reused) token to login', function () {
    $invite = Invite::factory()->accepted()->create();

    Livewire::test(Register::class, ['token' => $invite->token])
        ->assertRedirect(Filament::getLoginUrl());
});

it('redirects a garbage token to login', function () {
    Livewire::test(Register::class, ['token' => 'not-a-real-token'])
        ->assertRedirect(Filament::getLoginUrl());
});

it('redirects to login when there is no token and open registration is off', function () {
    SiteSetting::instance()->update(['open_registration' => false]);

    Livewire::test(Register::class)
        ->assertRedirect(Filament::getLoginUrl());
});

it('registers a viewer in open-registration mode with no token', function () {
    SiteSetting::instance()->update(['open_registration' => true]);

    $component = Livewire::test(Register::class);
    fillRegistration($component, 'walkup@example.com')
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'walkup@example.com')->firstOrFail();
    expect($user->hasRole(UserRole::Viewer->value))->toBeTrue()
        ->and($user->canEdit())->toBeFalse();
});

it('rejects an invite whose email was registered after the invite was sent', function () {
    $invite = Invite::factory()->create(['email' => 'racy@example.com']);
    User::factory()->create(['email' => 'racy@example.com']);

    Livewire::test(Register::class, ['token' => $invite->token])
        ->assertRedirect(Filament::getLoginUrl());
});

it('rejects an invite that expires between page load and submit', function () {
    $invite = Invite::factory()->create(['email' => 'slow@example.com']);

    $component = Livewire::test(Register::class, ['token' => $invite->token]);
    fillRegistration($component, 'slow@example.com');

    $invite->forceFill(['expires_at' => now()->subMinute()])->save();

    $component->call('register')->assertRedirect(Filament::getLoginUrl());

    expect(User::where('email', 'slow@example.com')->exists())->toBeFalse();
});

it('rejects an invite that is accepted elsewhere between page load and submit', function () {
    $invite = Invite::factory()->create(['email' => 'racer@example.com']);

    $component = Livewire::test(Register::class, ['token' => $invite->token]);
    fillRegistration($component, 'racer@example.com');

    $invite->markAccepted();

    $component->call('register')->assertRedirect(Filament::getLoginUrl());

    expect(User::where('email', 'racer@example.com')->exists())->toBeFalse();
});

it('rejects a submit when the invite email was claimed after page load', function () {
    $invite = Invite::factory()->create(['email' => 'claimed@example.com']);

    $component = Livewire::test(Register::class, ['token' => $invite->token]);
    fillRegistration($component, 'claimed@example.com');

    User::factory()->create(['email' => 'claimed@example.com']);

    $component->call('register')->assertRedirect(Filament::getLoginUrl());

    expect(User::where('email', 'claimed@example.com')->count())->toBe(1);
});

it('rejects an open registration submitted after open registration is switched off', function () {
    SiteSetting::instance()->update(['open_registration' => true]);

    $component = Livewire::test(Register::class);
    fillRegistration($component, 'toolate@example.com');

    SiteSetting::instance()->update(['open_registration' => false]);

    $component->call('register')->assertRedirect(Filament::getLoginUrl());

    expect(User::where('email', 'toolate@example.com')->exists())->toBeFalse();
});
