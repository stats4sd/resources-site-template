<?php

use App\Contracts\ResolvesVideoLinks;
use App\Filament\Resources\TroveResource\Pages\CreateTrove;
use App\Models\Trove;
use App\Models\TroveType;
use App\Support\VideoLink\VideoLinkResult;
use Livewire\Livewire;

beforeEach(function () {
    $this->me = actingAsAdmin();

    app()->instance(ResolvesVideoLinks::class, new class implements ResolvesVideoLinks
    {
        public array $resolvedUrls = [];

        public function resolve(string $url): VideoLinkResult
        {
            $this->resolvedUrls[] = $url;

            return new VideoLinkResult(
                url: $url,
                provider: 'youtube',
                embedUrl: 'https://www.youtube.com/embed/q76bMs-NwRk',
                embeddable: true,
                title: 'Resolved title',
                resolvedUrl: $url,
            );
        }
    });
});

function videoTroveFormData(TroveType $type, array $videoLinks): array
{
    return [
        'title' => ['en' => 'Video Trove'],
        'description' => ['en' => 'Has a video'],
        'trove_type_id' => $type->id,
        'source' => 0,
        'creation_date' => now()->format('Y-m-d'),
        'video_links' => ['en' => $videoLinks],
    ];
}

it('resolves unresolved video links on create', function () {
    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [['url' => 'https://youtu.be/q76bMs-NwRk']]))
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = Trove::withDrafts()->firstWhere('slug', 'video-trove')->getTranslation('video_links', 'en');

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['embed_url'])->toBe('https://www.youtube.com/embed/q76bMs-NwRk')
        ->and($stored[0]['embeddable'])->toBeTrue()
        ->and($stored[0]['title'])->toBe('Resolved title');
});

it('does not re-resolve rows whose resolution already matches the url', function () {
    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [[
            'url' => 'https://youtu.be/q76bMs-NwRk',
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
            'embeddable' => true,
            'title' => 'Already resolved',
            'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
        ]]))
        ->call('create')
        ->assertHasNoFormErrors();

    // The mutate hook must not re-resolve when resolved_url === url;
    // if it did, 'Already resolved' would be overwritten with 'Resolved title'.
    $stored = Trove::withDrafts()->firstWhere('slug', 'video-trove')->getTranslation('video_links', 'en');

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['title'])->toBe('Already resolved');
});

it('re-resolves rows whose url changed after resolution and drops empty rows', function () {
    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [
            [
                'url' => 'https://youtu.be/DIFFERENT-ID',
                'provider' => 'youtube',
                'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
                'embeddable' => true,
                'title' => 'Stale',
                'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
            ],
            ['url' => '   '],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = Trove::withDrafts()->firstWhere('slug', 'video-trove')->getTranslation('video_links', 'en');

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['title'])->toBe('Resolved title');
});
