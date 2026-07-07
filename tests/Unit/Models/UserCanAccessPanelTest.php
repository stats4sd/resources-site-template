<?php

use App\Models\User;
use Filament\Panel;

// Panel access is intentionally granted to ANY authenticated user (viewers get a
// read-only panel); what a user can actually do inside is governed by the policies.
it('grants panel access to any user unconditionally', function () {
    $user = User::factory()->create();
    $panel = Mockery::mock(Panel::class);

    expect($user->canAccessPanel($panel))->toBeTrue();
});
