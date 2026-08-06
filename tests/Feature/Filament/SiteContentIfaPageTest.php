<?php

use App\Filament\Pages\SiteContentIfaPage;
use App\Models\SiteContent;
use Livewire\Livewire;

beforeEach(fn () => actingAsAdmin());

it('persists translatable content keys to SiteContent', function () {
    Livewire::test(SiteContentIfaPage::class)
        ->fillForm([
            'shared_hero_heading' => ['en' => 'Welcome'],
            'home_ifa_intro' => ['en' => "Explore our resources.\n\nMore about us."],
            'footer_admin_login_label' => ['en' => 'Staff Login'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteContent::get('shared_hero_heading'))->toBe('Welcome')
        ->and(SiteContent::get('home_ifa_intro'))->toBe("Explore our resources.\n\nMore about us.")
        ->and(SiteContent::get('footer_admin_login_label'))->toBe('Staff Login');
});
