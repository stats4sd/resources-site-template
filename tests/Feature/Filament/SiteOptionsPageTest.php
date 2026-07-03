<?php

use App\Filament\Pages\SiteOptionsPage;
use App\Models\SiteSetting;
use Livewire\Livewire;

beforeEach(fn () => actingAsAdmin());

it('persists the language filter toggle and locales repeater to SiteSetting', function () {
    Livewire::test(SiteOptionsPage::class)
        ->fillForm([
            'show_language_filter' => false,
            'locales' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'es', 'label' => 'Español'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = SiteSetting::instance();
    expect($settings->show_language_filter)->toBeFalse()
        ->and($settings->localesAsConfig())->toBe(['en' => 'English', 'es' => 'Español']);
});
