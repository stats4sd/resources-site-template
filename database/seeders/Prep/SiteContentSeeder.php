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

        if ($orgName === '' || $orgName === 'Your Organisation') {
            $this->command?->warn('BRAND_ORG_NAME is not set (or still the "Your Organisation" default). Set it in .env before seeding, otherwise the placeholder name is baked into SiteContent and changing .env later will not update it.');
        }

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
