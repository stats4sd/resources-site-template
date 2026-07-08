<?php

use App\Support\VideoLink\LegacyYoutubeLinksConverter;

function expectedRecord(string $videoId): array
{
    return [
        'url' => "https://www.youtube.com/watch?v={$videoId}",
        'provider' => 'youtube',
        'embed_url' => "https://www.youtube.com/embed/{$videoId}",
        'embeddable' => true,
        'title' => null,
        'resolved_url' => "https://www.youtube.com/watch?v={$videoId}",
    ];
}

it('converts a list of legacy youtube_id entries', function () {
    $converted = LegacyYoutubeLinksConverter::convertLocaleEntries([
        ['youtube_id' => 'q76bMs-NwRk'],
        ['youtube_id' => 'xNN7iTA57jM'],
    ]);

    expect($converted)->toBe([expectedRecord('q76bMs-NwRk'), expectedRecord('xNN7iTA57jM')]);
});

it('converts the legacy single-assoc shape', function () {
    expect(LegacyYoutubeLinksConverter::convertLocaleEntries(['youtube_id' => 'q76bMs-NwRk']))
        ->toBe([expectedRecord('q76bMs-NwRk')]);
});

it('passes through entries already in the new shape and drops empty ones', function () {
    $newShape = expectedRecord('q76bMs-NwRk');

    expect(LegacyYoutubeLinksConverter::convertLocaleEntries([$newShape, ['youtube_id' => ''], 'junk']))
        ->toBe([$newShape]);
});

it('converts a whole translations dictionary and drops empty locales', function () {
    $converted = LegacyYoutubeLinksConverter::convertTranslations([
        'en' => [['youtube_id' => 'q76bMs-NwRk']],
        'fr' => [],
    ]);

    expect($converted)->toBe(['en' => [expectedRecord('q76bMs-NwRk')]]);
});

it('returns null for non-array or fully-empty input', function () {
    expect(LegacyYoutubeLinksConverter::convertTranslations(null))->toBeNull()
        ->and(LegacyYoutubeLinksConverter::convertTranslations(['en' => []]))->toBeNull();
});
