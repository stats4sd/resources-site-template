<?php

namespace App\Policies;

use App\Models\TagType;
use App\Models\User;

class TagTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TagType $tagType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    public function update(User $user, TagType $tagType): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, TagType $tagType): bool
    {
        return $user->canEdit();
    }

    public function restore(User $user, TagType $tagType): bool
    {
        return $user->canEdit();
    }

    public function forceDelete(User $user, TagType $tagType): bool
    {
        return $user->canEdit();
    }
}
