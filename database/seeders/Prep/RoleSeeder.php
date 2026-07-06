<?php

namespace Database\Seeders\Prep;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the three fixed roles on the `web` guard. Idempotent (firstOrCreate), so it's
 * safe to re-run on existing installs. No permissions are defined yet — policies check
 * roles directly; granular permissions are a future phase.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }

        // Roles are cached by spatie; clear so freshly-created roles resolve immediately.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
