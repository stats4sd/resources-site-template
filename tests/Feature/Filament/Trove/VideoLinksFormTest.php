<?php

use App\Contracts\ResolvesVideoLinks;
use App\Filament\Resources\TroveResource\Pages\CreateTrove;
use App\Filament\Resources\TroveResource\Pages\EditTrove;
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

    // fillForm fires url's afterStateUpdated mid-fill (overwriting title with the fake
    // resolver's value); the later keys in this row then restore title/resolved_url.
    // The final assertion therefore depends on fillForm processing keys in insertion
    // order — if this ever breaks, suspect that order, not the mutate hook.
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

it('saves the trove even when the resolver throws', function () {
    app()->instance(ResolvesVideoLinks::class, new class implements ResolvesVideoLinks
    {
        public function resolve(string $url): VideoLinkResult
        {
            throw new RuntimeException('resolver exploded');
        }
    });

    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [['url' => 'https://youtu.be/q76bMs-NwRk']]))
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = Trove::withDrafts()->firstWhere('slug', 'video-trove')->getTranslation('video_links', 'en');

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['url'])->toBe('https://youtu.be/q76bMs-NwRk')
        ->and($stored[0]['embeddable'])->toBeFalse();
});

it('neutralises a non-https embed_url injected through a pre-resolved row', function () {
    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [[
            'url' => 'https://youtu.be/q76bMs-NwRk',
            'provider' => 'youtube',
            'embed_url' => 'javascript:alert(1)',
            'embeddable' => true,
            'title' => 'Evil',
            'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
        ]]))
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = Trove::withDrafts()->firstWhere('slug', 'video-trove')->getTranslation('video_links', 'en');

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['embeddable'])->toBeFalse()
        ->and($stored[0]['embed_url'])->toBeNull();
});

it('editing a live trove video_links lands changes on the shadow draft not the canonical', function () {
    $type = TroveType::factory()->create();
    $canonical = publishedTrove([
        'trove_type_id' => $type->id,
        'source' => true,
        'creation_date' => now()->subYear()->toDateString(),
        'video_links' => ['en' => []],
    ]);

    Livewire::test(EditTrove::class, ['record' => $canonical->getKey()])
        ->set('data.video_links', ['en' => [['url' => 'https://youtu.be/q76bMs-NwRk']]])
        ->set('data.source', 1)
        ->call('save')
        ->assertHasNoFormErrors();

    $canonical->refresh();

    $draft = Trove::withDrafts()->where('published_id', $canonical->id)->first();

    expect($canonical->getTranslation('video_links', 'en'))->toBe([])
        ->and($draft)->not->toBeNull()
        ->and($draft->getTranslation('video_links', 'en'))->toHaveCount(1)
        ->and($draft->getTranslation('video_links', 'en')[0]['embed_url'])->toBe('https://www.youtube.com/embed/q76bMs-NwRk')
        ->and($draft->getTranslation('video_links', 'en')[0]['title'])->toBe('Resolved title');
});
