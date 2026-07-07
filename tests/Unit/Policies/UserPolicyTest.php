<?php

use App\Models\Invite;
use App\Models\User;

it('lets an admin delete another user', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create(); // a second admin, so neither is "last"
    $target = User::factory()->editor()->create();

    expect($admin->can('delete', $target))->toBeTrue();
});

it('forbids a user from deleting themselves', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse();
});

it('forbids deleting the last admin', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    // With two admins, neither is "last" — each can delete the other.
    expect($adminA->isLastAdmin())->toBeFalse()
        ->and($adminB->can('delete', $adminA))->toBeTrue();

    // Demote B, leaving A as the sole admin.
    $adminB->syncRoles(['editor']);

    expect($adminA->fresh()->isLastAdmin())->toBeTrue()
        // A can't delete itself, and no other admin exists to delete it either.
        ->and($adminA->can('delete', $adminA))->toBeFalse()
        ->and($adminB->fresh()->can('delete', $adminA))->toBeFalse();
});

it('forbids non-admins from deleting users', function () {
    $editor = User::factory()->editor()->create();
    $target = User::factory()->editor()->create();

    expect($editor->can('delete', $target))->toBeFalse()
        ->and($editor->can('viewAny', User::class))->toBeFalse();
});

it('restricts invite management to admins', function () {
    $admin = User::factory()->admin()->create();
    $editor = User::factory()->editor()->create();
    $viewer = User::factory()->viewer()->create();
    $invite = Invite::factory()->create();

    expect($admin->can('viewAny', Invite::class))->toBeTrue()
        ->and($admin->can('create', Invite::class))->toBeTrue()
        ->and($admin->can('delete', $invite))->toBeTrue()
        ->and($editor->can('viewAny', Invite::class))->toBeFalse()
        ->and($viewer->can('create', Invite::class))->toBeFalse();
});
