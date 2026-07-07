<?php

namespace App\Policies;

use App\Models\Invite;
use App\Models\User;

class InvitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Invite $invite): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Invite $invite): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Invite $invite): bool
    {
        return $user->isAdmin();
    }
}
