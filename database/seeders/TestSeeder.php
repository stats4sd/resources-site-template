<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds. Assigns roles so local dev has a working admin and editor
     * immediately (RoleSeeder runs before this in DatabaseSeeder).
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->syncRoles([UserRole::Admin->value]);

        $editor = User::updateOrCreate(['email' => 'test2@example.com'], [
            'name' => 'Test Two',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
        ]);
        $editor->syncRoles([UserRole::Editor->value]);
    }
}
