<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admins may delete users, but never themselves and never the last remaining admin.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        return ! $model->isLastAdmin();
    }

    /**
     * Bulk deletion is disabled outright: bulk actions skip the per-record delete()
     * guards (self-deletion, last admin), so there is no safe way to allow it.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
