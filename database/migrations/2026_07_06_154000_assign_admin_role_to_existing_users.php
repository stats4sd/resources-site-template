<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Upgrade path: before this feature every authenticated user had unrestricted access.
 * Promote all pre-existing users to `admin` so their behaviour is preserved. On a fresh
 * install there are no users at this point, so this is a no-op; new users get their role
 * assigned explicitly at every creation path instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Roles must exist before we can assign them; RoleSeeder also creates these, but
        // migrations run before seeders, so ensure they're present here too.
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::query()->each(function (User $user): void {
            if ($user->roles()->count() === 0) {
                $user->assignRole(UserRole::Admin->value);
            }
        });
    }

    public function down(): void
    {
        // Irreversible data migration — role assignments are left in place.
    }
};
