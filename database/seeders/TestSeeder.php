<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        User::updateOrCreate(['email' => 'test2@example.com'], [
            'name' => 'Test Two',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
