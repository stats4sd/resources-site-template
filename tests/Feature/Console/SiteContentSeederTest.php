<?php

use App\Models\SiteContent;
use Database\Seeders\Prep\SiteContentSeeder;

it('warns when the org name is still the default placeholder', function () {
    config(['branding.org_name' => 'Your Organisation']);

    $this->artisan('db:seed', ['--class' => SiteContentSeeder::class])
        ->expectsOutputToContain('BRAND_ORG_NAME is not set')
        ->assertSuccessful();
});

it('warns when the org name is empty', function () {
    config(['branding.org_name' => '']);

    $this->artisan('db:seed', ['--class' => SiteContentSeeder::class])
        ->expectsOutputToContain('BRAND_ORG_NAME is not set')
        ->assertSuccessful();
});

it('does not warn when the org name is configured', function () {
    config(['branding.org_name' => 'Acme Research']);

    $this->artisan('db:seed', ['--class' => SiteContentSeeder::class])
        ->doesntExpectOutputToContain('BRAND_ORG_NAME is not set')
        ->assertSuccessful();

    expect(SiteContent::get('home_heading_line1'))->toContain('Acme Research');
});

it('runs without a command instance when seeded programmatically', function () {
    config(['branding.org_name' => 'Your Organisation']);

    (new SiteContentSeeder)->run();

    expect(SiteContent::query()->count())->toBeGreaterThan(0);
});
