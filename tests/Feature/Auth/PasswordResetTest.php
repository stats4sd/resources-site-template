<?php

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('registers the password reset request route', function () {
    $this->get('/admin/password-reset/request')->assertOk();
});

it('sends a reset notification through the request flow', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'forgetful@example.com']);

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'forgetful@example.com'])
        ->call('request');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('lets an admin trigger a reset link from the user edit page', function () {
    Notification::fake();
    actingAsAdmin();
    $target = User::factory()->editor()->create();

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->callAction('sendPasswordReset');

    Notification::assertSentTo($target, ResetPassword::class);
});
