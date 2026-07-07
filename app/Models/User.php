<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Every authenticated user reaches the panel; roles govern what they can do
        // once inside (viewers get a read-only panel). Gating lives in the policies.
        return true;
    }

    /**
     * Role helpers wrap spatie's HasRoles so policies never call hasRole() with a magic
     * string. A future move to granular permissions (can('manage troves')) only touches
     * these helpers, not every caller. A role-less user is treated as viewer-equivalent.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin->value);
    }

    public function canEdit(): bool
    {
        return $this->hasAnyRole([UserRole::Editor->value, UserRole::Admin->value]);
    }

    /**
     * True when this user is an admin and no other admin exists. Guards the deletion and
     * demotion of the sole administrator so a site can never lock itself out of user
     * management. Used by UserPolicy and the UserResource role field validation.
     */
    public function isLastAdmin(): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        return static::role(UserRole::Admin->value)->where('id', '!=', $this->getKey())->doesntExist();
    }
}
