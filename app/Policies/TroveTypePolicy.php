<?php

namespace App\Policies;

use App\Models\TroveType;
use App\Models\User;

class TroveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TroveType $troveType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    public function update(User $user, TroveType $troveType): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, TroveType $troveType): bool
    {
        return $user->canEdit();
    }

    public function restore(User $user, TroveType $troveType): bool
    {
        return $user->canEdit();
    }

    public function forceDelete(User $user, TroveType $troveType): bool
    {
        return $user->canEdit();
    }
}
