<?php

use App\Models\Collection;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;

/**
 * The content policies (Trove/Collection/Tag/TagType/TroveType) share one rule: everyone
 * may view, only editors and admins may mutate. Viewers are read-only.
 */
dataset('content models', [
    'trove' => [fn () => Trove::withoutSyncingToSearch(fn () => Trove::factory()->create())],
    'collection' => [fn () => Collection::withoutSyncingToSearch(fn () => Collection::factory()->create())],
    'tag' => [fn () => Tag::factory()->create()],
    'tagType' => [fn () => TagType::factory()->create()],
    'troveType' => [fn () => TroveType::factory()->create()],
]);

it('lets everyone view content', function (Closure $make) {
    $record = $make();

    foreach (['viewer', 'editor', 'admin'] as $role) {
        $user = User::factory()->{$role}()->create();
        expect($user->can('viewAny', $record::class))->toBeTrue()
            ->and($user->can('view', $record))->toBeTrue();
    }
})->with('content models');

it('forbids viewers from mutating content', function (Closure $make) {
    $record = $make();
    $viewer = User::factory()->viewer()->create();

    expect($viewer->can('create', $record::class))->toBeFalse()
        ->and($viewer->can('update', $record))->toBeFalse()
        ->and($viewer->can('delete', $record))->toBeFalse();
})->with('content models');

it('allows editors and admins to mutate content', function (Closure $make) {
    $record = $make();

    foreach (['editor', 'admin'] as $role) {
        $user = User::factory()->{$role}()->create();
        expect($user->can('create', $record::class))->toBeTrue()
            ->and($user->can('update', $record))->toBeTrue()
            ->and($user->can('delete', $record))->toBeTrue();
    }
})->with('content models');

it('treats a role-less user as having no edit rights', function () {
    $user = User::factory()->create(); // no role assigned

    expect($user->canEdit())->toBeFalse()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->can('create', Trove::class))->toBeFalse();
});
