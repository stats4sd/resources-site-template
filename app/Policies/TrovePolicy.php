<?php

namespace App\Policies;

use App\Models\Trove;
use App\Models\User;

class TrovePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trove $trove): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    public function update(User $user, Trove $trove): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, Trove $trove): bool
    {
        return $user->canEdit();
    }

    public function restore(User $user, Trove $trove): bool
    {
        return $user->canEdit();
    }

    public function forceDelete(User $user, Trove $trove): bool
    {
        return $user->canEdit();
    }
}
