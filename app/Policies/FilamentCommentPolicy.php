<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Parallax\FilamentComments\Models\FilamentComment;

/**
 * Overrides the package default (which lets any authenticated user comment). Viewers get
 * a read-only panel, so they may read comments but not create them; deletion stays limited
 * to a comment's own author (who must also be an editor/admin to have created it).
 *
 * Wired up via config('filament-comments.model_policy').
 */
class FilamentCommentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, FilamentComment $comment): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return $user instanceof User && $user->canEdit();
    }

    public function update(Authenticatable $user, FilamentComment $comment): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, FilamentComment $comment): bool
    {
        return $user instanceof User
            && $user->canEdit()
            && $user->getKey() === $comment->user_id;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, FilamentComment $comment): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, FilamentComment $comment): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
