<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // List roles for the application
        $admin = -Role::updateOrCreate(['name' => 'admin']);

        // List permissions for the application
        Permission::updateOrCreate(['name' => 'manage users']);

        // Assign permissions to roles
        $admin->givePermissionTo('manage users');
    }
}
