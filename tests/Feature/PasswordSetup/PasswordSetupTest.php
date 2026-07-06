<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\SetPassword;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Mail\SetPasswordMail;
use App\Models\PasswordSetup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('generates a unique 64-char token and a 7-day expiry on creation', function () {
    $setup = PasswordSetup::factory()->create(['expires_at' => null]);

    expect($setup->token)->toHaveLength(64)
        ->and($setup->expires_at->diffInDays(now()->addDays(7), true))->toBeLessThan(1)
        ->and($setup->used_at)->toBeNull()
        ->and($setup->isUsable())->toBeTrue();
});

it('reports expired and used tokens as unusable', function () {
    expect(PasswordSetup::factory()->expired()->create()->isUsable())->toBeFalse()
        ->and(PasswordSetup::factory()->used()->create()->isUsable())->toBeFalse();
});

it('refreshes the token and expiry and clears used_at on refreshToken', function () {
    $setup = PasswordSetup::factory()->used()->expired()->create();
    $oldToken = $setup->token;

    $setup->refreshToken();

    expect($setup->token)->not->toBe($oldToken)
        ->and($setup->expires_at->isFuture())->toBeTrue()
        ->and($setup->used_at)->toBeNull()
        ->and($setup->isUsable())->toBeTrue();
});

it('creates a passwordless user and emails a setup link when the email option is chosen', function () {
    Mail::fake();
    actingAsAdmin();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Invited Person',
            'email' => 'invited@example.com',
            'role' => UserRole::Editor->value,
            'password_method' => 'email_link',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'invited@example.com')->firstOrFail();
    expect($user->password)->toBeNull()
        ->and($user->hasRole(UserRole::Editor->value))->toBeTrue()
        ->and(PasswordSetup::where('user_id', $user->id)->exists())->toBeTrue();

    Mail::assertQueued(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo('invited@example.com'));
});

it('creates a user with a hashed password and no setup link when a password is set manually', function () {
    Mail::fake();
    actingAsAdmin();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Manual Person',
            'email' => 'manual@example.com',
            'role' => UserRole::Editor->value,
            'password_method' => 'manual',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'manual@example.com')->firstOrFail();
    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and(PasswordSetup::where('user_id', $user->id)->exists())->toBeFalse();

    Mail::assertNothingQueued();
});

it('sets the password and consumes the token from a valid set-password link', function () {
    $user = User::factory()->editor()->create(['password' => null]);
    $setup = PasswordSetup::factory()->create(['user_id' => $user->id]);

    Livewire::withQueryParams(['token' => $setup->token])
        ->test(SetPassword::class)
        ->fillForm([
            'password' => 'brand-new-password',
            'passwordConfirmation' => 'brand-new-password',
        ])
        ->call('resetPassword')
        ->assertRedirect(Filament::getLoginUrl());

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue()
        ->and($setup->fresh()->used_at)->not->toBeNull()
        ->and($setup->fresh()->isUsable())->toBeFalse();
});

it('rejects an invalid, expired or used set-password token on mount', function (string $state) {
    $user = User::factory()->editor()->create(['password' => null]);

    $token = match ($state) {
        'invalid' => 'this-token-does-not-exist',
        'expired' => PasswordSetup::factory()->expired()->create(['user_id' => $user->id])->token,
        'used' => PasswordSetup::factory()->used()->create(['user_id' => $user->id])->token,
    };

    Livewire::withQueryParams(['token' => $token])
        ->test(SetPassword::class)
        ->assertRedirect(Filament::getLoginUrl());

    expect($user->fresh()->password)->toBeNull();
})->with(['invalid', 'expired', 'used']);

it('does not set a password when the token expires between load and submit', function () {
    $user = User::factory()->editor()->create(['password' => null]);
    $setup = PasswordSetup::factory()->create(['user_id' => $user->id]);

    $component = Livewire::withQueryParams(['token' => $setup->token])
        ->test(SetPassword::class);

    // The link is used elsewhere before this submit lands.
    $setup->markUsed();

    $component
        ->fillForm([
            'password' => 'brand-new-password',
            'passwordConfirmation' => 'brand-new-password',
        ])
        ->call('resetPassword')
        ->assertRedirect(Filament::getLoginUrl());

    expect($user->fresh()->password)->toBeNull();
});

it('redirects an already-authenticated visitor away from the set-password page', function () {
    $user = User::factory()->editor()->create(['password' => null]);
    $setup = PasswordSetup::factory()->create(['user_id' => $user->id]);
    actingAsAdmin();

    Livewire::withQueryParams(['token' => $setup->token])
        ->test(SetPassword::class)
        ->assertRedirect(Filament::getUrl());
});

it('resends a fresh set-password link from the edit page for a passwordless user', function () {
    Mail::fake();
    actingAsAdmin();
    $user = User::factory()->editor()->create(['password' => null]);
    $setup = PasswordSetup::factory()->create(['user_id' => $user->id]);
    $oldToken = $setup->token;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('resendSetPasswordLink');

    expect($setup->fresh()->token)->not->toBe($oldToken)
        ->and($setup->fresh()->isUsable())->toBeTrue();

    Mail::assertQueued(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo($user->email));
});

it('hides the resend action for a user who already has a password', function () {
    actingAsAdmin();
    $user = User::factory()->editor()->create(['password' => Hash::make('secret')]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->assertActionHidden('resendSetPasswordLink');
});
