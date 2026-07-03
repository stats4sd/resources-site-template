<?php

namespace Database\Seeders\Prep;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $locales = array_keys(config('branding.locales', ['en' => 'English']));
        $defaultLocale = $locales[0];

        $orgName = config('branding.org_name', '');

        $defaults = [
            'home_heading_line1' => [
                $defaultLocale => $orgName,
            ],
            'home_heading_line2' => [
                $defaultLocale => 'Resources Library',
            ],
            'library_heading_line1' => [
                $defaultLocale => $orgName,
            ],
            'library_heading_line2' => [
                $defaultLocale => 'Resources Library',
            ],
            'home_intro' => [
                $defaultLocale => 'Welcome to the ' . $orgName . ' Resources Library - a carefully selected set of resources for you to explore.',
            ],
            'library_hero_description' => [
                $defaultLocale => 'Browse the full library of resources and collections on a variety of topics.',
            ],
            'footer_admin_login_label' => [
                $defaultLocale => 'Staff Login',
            ],
        ];

        foreach ($defaults as $key => $value) {
            SiteContent::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
