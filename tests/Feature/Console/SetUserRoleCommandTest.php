<?php

use App\Enums\UserRole;
use App\Models\User;

it('assigns a role to a user by email', function () {
    $user = User::factory()->create(['email' => 'target@example.com']);

    $this->artisan('user:set-role', ['email' => 'target@example.com', 'role' => 'admin'])
        ->assertSuccessful();

    expect($user->fresh()->hasRole(UserRole::Admin->value))->toBeTrue();
});

it('fails on an invalid role', function () {
    User::factory()->create(['email' => 'target@example.com']);

    $this->artisan('user:set-role', ['email' => 'target@example.com', 'role' => 'superuser'])
        ->assertFailed();
});

it('fails when the user does not exist', function () {
    $this->artisan('user:set-role', ['email' => 'ghost@example.com', 'role' => 'admin'])
        ->assertFailed();
});

it('refuses to demote the last admin without --force', function () {
    $admin = User::factory()->admin()->create(['email' => 'solo@example.com']);

    $this->artisan('user:set-role', ['email' => 'solo@example.com', 'role' => 'viewer'])
        ->assertFailed();

    expect($admin->fresh()->hasRole(UserRole::Admin->value))->toBeTrue();
});

it('demotes the last admin with --force', function () {
    $admin = User::factory()->admin()->create(['email' => 'solo@example.com']);

    $this->artisan('user:set-role', ['email' => 'solo@example.com', 'role' => 'viewer', '--force' => true])
        ->assertSuccessful();

    expect($admin->fresh()->hasRole(UserRole::Viewer->value))->toBeTrue();
});
