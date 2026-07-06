<?php

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(fn () => actingAsAdmin());

it('creates a user with a role and a hashed password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Created Person',
            'email' => 'created@example.com',
            'role' => UserRole::Editor->value,
            'password' => 'password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'created@example.com')->firstOrFail();
    expect($user->hasRole(UserRole::Editor->value))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('changes a user role on edit', function () {
    $user = User::factory()->viewer()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['role' => UserRole::Editor->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->hasRole(UserRole::Editor->value))->toBeTrue()
        ->and($user->fresh()->hasRole(UserRole::Viewer->value))->toBeFalse();
});

it('keeps the current password when the password field is left blank on edit', function () {
    $user = User::factory()->editor()->create();
    $originalHash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'Renamed', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->password)->toBe($originalHash)
        ->and($user->fresh()->name)->toBe('Renamed');
});

it('blocks demoting the last admin from the edit form', function () {
    // The acting admin from beforeEach plus this one — but demoting the SOLE admin must fail.
    // Remove the beforeEach admin's adminship is not possible here, so create an isolated case:
    User::query()->whereKeyNot(auth()->id())->delete();
    auth()->user()->syncRoles([UserRole::Admin->value]);
    $soleAdmin = auth()->user();

    expect($soleAdmin->isLastAdmin())->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $soleAdmin->id])
        ->fillForm(['role' => UserRole::Viewer->value])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($soleAdmin->fresh()->hasRole(UserRole::Admin->value))->toBeTrue();
});
