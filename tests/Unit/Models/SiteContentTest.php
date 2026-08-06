<?php

use App\Models\SiteContent;

it('returns null for a missing key', function () {
    expect(SiteContent::get('nonexistent_key'))->toBeNull();
});

it('returns null when the stored value is empty', function () {
    SiteContent::create(['key' => 'empty_one', 'value' => ['en' => '']]);

    expect(SiteContent::get('empty_one'))->toBeNull();
});

it('returns the value for the current app locale by default', function () {
    app()->setLocale('en');
    SiteContent::create(['key' => 'greeting', 'value' => ['en' => 'Hello', 'es' => 'Hola']]);

    expect(SiteContent::get('greeting'))->toBe('Hello');
});

it('respects an explicit locale without falling back', function () {
    SiteContent::create(['key' => 'only_en', 'value' => ['en' => 'Hello']]);

    expect(SiteContent::get('only_en', 'en'))->toBe('Hello')
        // no fallback: a locale with no translation returns null, not the English value
        ->and(SiteContent::get('only_en', 'es'))->toBeNull();
});
