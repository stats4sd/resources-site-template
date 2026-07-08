<?php

use App\Models\Trove;

beforeEach(function () {
    bootPublicSite();
});

function publishedTroveWithVideoLinks(array $links): Trove
{
    return Trove::factory()->create([
        'published_at' => now(),
        'video_links' => ['en' => $links],
    ]);
}

it('renders an iframe for an embeddable video link', function () {
    $trove = publishedTroveWithVideoLinks([[
        'url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
        'provider' => 'ecoagtube',
        'embed_url' => 'https://www.ecoagtube.org/embed/32021',
        'embeddable' => true,
        'title' => 'Biofertilizer formulation',
        'resolved_url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
    ]]);

    $this->get("/resources/{$trove->slug}")
        ->assertOk()
        ->assertSee('https://www.ecoagtube.org/embed/32021', false);
});

it('renders a link card for a non-embeddable video link', function () {
    $trove = publishedTroveWithVideoLinks([[
        'url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
        'provider' => null,
        'embed_url' => null,
        'embeddable' => false,
        'title' => 'Crop rotation with legumes',
        'resolved_url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
    ]]);

    $response = $this->get("/resources/{$trove->slug}");

    $response->assertOk()
        ->assertSee('Crop rotation with legumes')
        ->assertSee('accessagriculture.org')
        ->assertSee('https://www.accessagriculture.org/crop-rotation-legumes', false)
        ->assertDontSee('<iframe', false);
});

it('renders a mixed list with both treatments and skips urlless entries', function () {
    $trove = publishedTroveWithVideoLinks([
        [
            'url' => 'https://youtu.be/q76bMs-NwRk',
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
            'embeddable' => true,
            'title' => null,
            'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
        ],
        ['url' => '', 'embeddable' => false],
        [
            'url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
            'provider' => null,
            'embed_url' => null,
            'embeddable' => false,
            'title' => null,
            'resolved_url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
        ],
    ]);

    $response = $this->get("/resources/{$trove->slug}");

    $response->assertOk()
        ->assertSee('https://www.youtube.com/embed/q76bMs-NwRk', false)
        ->assertSee('accessagriculture.org');

    expect(substr_count($response->content(), '<iframe'))->toBe(1)
        ->and(substr_count($response->content(), 'Watch on'))->toBe(1);
});
