<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Prep\SiteContentSeeder;
use Database\Seeders\Prep\SiteSettingSeeder;
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
        $this->call(TroveTypeSeeder::class);
        $this->call(SiteContentSeeder::class);
        $this->call(SiteSettingSeeder::class);

        // run test seeders locally
        if (app()->environment('local')) {
            $this->call(TestSeeder::class);
        }
    }
}
