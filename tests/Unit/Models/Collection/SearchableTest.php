<?php

use App\Models\Collection;

it('builds a searchable array including the public flag', function () {
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
        ->and($array['public'])->toBe(1);
});

it('reports public as 0 for a private collection', function () {
    $collection = Collection::factory()->private()->create();

    expect($collection->toSearchableArray()['public'])->toBe(0);
});

it('indexes public collections and skips private ones', function () {
    expect(Collection::factory()->create()->shouldBeSearchable())->toBeTrue()
        ->and(Collection::factory()->private()->create()->shouldBeSearchable())->toBeFalse();
});
