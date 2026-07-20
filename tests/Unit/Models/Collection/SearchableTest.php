<?php

use App\Models\Collection;
use App\Models\Tag;
use App\Models\Trove;
use App\Models\TroveType;

it('builds a searchable array of flattened titles and descriptions', function () {
    config(['app.locales' => ['en' => 'English', 'es' => 'Spanish']]);

    $collection = Collection::factory()->create([
        'title' => ['en' => 'Getting Started', 'es' => 'Comenzar'],
        'description' => ['en' => '<em>Curated</em> set'],
        'public' => true,
    ]);

    $array = $collection->toSearchableArray();

    expect($array['title'])->toBe('Getting Started Comenzar')
        ->and($array['description'])->toBe('Curated set') // HTML stripped
        ->and($array['id'])->toBe($collection->id)
        ->and($array)->not->toHaveKey('public');
});

it('indexes public collections and skips private ones', function () {
    expect(Collection::factory()->create()->shouldBeSearchable())->toBeTrue()
        ->and(Collection::factory()->private()->create()->shouldBeSearchable())->toBeFalse();
});

it('aggregates tag and trove type ids from published canonical members only', function () {
    $publishedType = TroveType::factory()->create();
    $draftType = TroveType::factory()->create();

    $publishedTag = Tag::factory()->create();
    $draftTag = Tag::factory()->create();

    $publishedMember = publishedTrove(['trove_type_id' => $publishedType->id]);
    $publishedMember->tags()->attach($publishedTag);

    $draftMember = draftTrove(['trove_type_id' => $draftType->id]);
    $draftMember->tags()->attach($draftTag);

    $shadowDraft = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->draftOf($publishedMember)
        ->create(['trove_type_id' => $draftType->id]));
    $shadowDraft->tags()->attach($draftTag);

    $collection = Collection::factory()->create();
    $collection->troves()->attach([$publishedMember->id, $draftMember->id, $shadowDraft->id]);

    $array = $collection->toSearchableArray();

    expect($array['tag_ids'])->toBe([$publishedTag->id])
        ->and($array['trove_type_ids'])->toBe([$publishedType->id]);
});

it('deduplicates tag and trove type ids across members', function () {
    $type = TroveType::factory()->create();
    $tag = Tag::factory()->create();

    $memberOne = publishedTrove(['trove_type_id' => $type->id]);
    $memberTwo = publishedTrove(['trove_type_id' => $type->id]);
    $memberOne->tags()->attach($tag);
    $memberTwo->tags()->attach($tag);

    $collection = Collection::factory()->create();
    $collection->troves()->attach([$memberOne->id, $memberTwo->id]);

    $array = $collection->toSearchableArray();

    expect($array['tag_ids'])->toBe([$tag->id])
        ->and($array['trove_type_ids'])->toBe([$type->id]);
});

it('lists only locales with a non-empty title translation', function () {
    config(['app.locales' => ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French']]);

    $collection = Collection::factory()->create([
        'title' => ['en' => 'Present', 'es' => '', 'fr' => 'Présent'],
    ]);

    expect($collection->toSearchableArray()['locales'])->toBe(['en', 'fr']);
});

it('exposes created_at as the sort_date timestamp', function () {
    $collection = Collection::factory()->create();

    expect($collection->toSearchableArray()['sort_date'])
        ->toBe($collection->created_at->getTimestamp());
});
