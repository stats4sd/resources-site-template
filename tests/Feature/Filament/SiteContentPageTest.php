<?php

use App\Filament\Pages\SiteContentPage;
use App\Models\SiteContent;
use Livewire\Livewire;

beforeEach(fn () => actingAsAdmin());

it('persists translatable content keys to SiteContent', function () {
    Livewire::test(SiteContentPage::class)
        ->fillForm([
            'home_heading_line1' => ['en' => 'Welcome'],
            'home_intro' => ['en' => 'Explore our resources.'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteContent::get('home_heading_line1'))->toBe('Welcome')
        ->and(SiteContent::get('home_intro'))->toBe('Explore our resources.');
});
