<?php

use App\Models\SiteSetting;

it('is a singleton: instance() creates one row and always returns it', function () {
    $first = SiteSetting::instance();
    $second = SiteSetting::instance();

    expect($first->id)->toBe($second->id)
        ->and($first->id)->toBe(1)
        ->and(SiteSetting::count())->toBe(1);
});

it('defaults to English on first creation', function () {
    expect(SiteSetting::instance()->localesAsConfig())->toBe(['en' => 'English']);
});

it('filters malformed locale entries in localesAsConfig', function () {
    $setting = SiteSetting::factory()->create([
        'locales' => [
            ['code' => 'en', 'label' => 'English'],
            ['code' => 'es', 'label' => 'Spanish'],
            ['code' => '', 'label' => 'No code'],       // dropped
            ['code' => 'fr', 'label' => ''],            // dropped
            ['label' => 'Missing code key'],            // dropped
            ['code' => 'de'],                           // dropped
        ],
    ]);

    expect($setting->localesAsConfig())->toBe([
        'en' => 'English',
        'es' => 'Spanish',
    ]);
});
