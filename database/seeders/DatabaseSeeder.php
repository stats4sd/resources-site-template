<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Prep\TagSeeder;
use Database\Seeders\Prep\TagTypeSeeder;
use Database\Seeders\Prep\TroveTypeSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // run prep seeders from Prep folder
        $this->call(TagTypeSeeder::class);
        $this->call(TagSeeder::class);
        $this->call(TroveTypeSeeder::class);

        // run test seeders locally
        if (app()->environment('local')) {
            $this->call(TestSeeder::class);
        }
    }
}
