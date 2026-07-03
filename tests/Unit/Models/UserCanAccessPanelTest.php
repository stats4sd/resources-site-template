<?php

use App\Models\User;
use Filament\Panel;

// Change-detector for a security-relevant surface: canAccessPanel() currently returns
// true for ANY authenticated user (spatie/laravel-permission is not installed). If this
// ever gains real gating, this test should fail and be updated deliberately.
it('grants panel access to any user unconditionally', function () {
    $user = User::factory()->create();
    $panel = Mockery::mock(Panel::class);

    expect($user->canAccessPanel($panel))->toBeTrue();
});
