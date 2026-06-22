<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Prep\MediaTableSeeder;
use Database\Seeders\Prep\OldTroveSeeder;
use Database\Seeders\Prep\TagSeeder;
use Database\Seeders\Prep\TagTypeSeeder;
use Database\Seeders\Prep\TroveTypeSeeder;
use Database\Seeders\Prep\UserTableSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // run prep seeders from Prep folder
        $this->call(UserTableSeeder::class);
        $this->call(TagTypeSeeder::class);
        $this->call(TagSeeder::class);
        $this->call(TroveTypeSeeder::class);
        $this->call(MediaTableSeeder::class);

        // run test seeders locally
        if (app()->environment('local')) {
            $this->call(TestSeeder::class);
        }
    }
}
