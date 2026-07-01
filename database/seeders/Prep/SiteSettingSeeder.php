<?php

namespace Database\Seeders\Prep;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::firstOrCreate(['id' => 1], [
            'show_language_filter' => true,
            'locales' => [
                ['code' => 'en', 'label' => 'English'],
            ],
        ]);
    }
}
