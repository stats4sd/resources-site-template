<?php

use App\Models\Collection;
use Laravel\Scout\Searchable;

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

// Documented behaviour: Collection does NOT override shouldBeSearchable(), so every
// collection (public or not) is indexed — visibility filtering happens at query time.
// (Trait methods report the using class as their declaring class, so compare the source
// file instead to prove it is the Scout trait's implementation, not a local override.)
it('indexes every collection because it does not override shouldBeSearchable', function () {
    $sourceFile = (new ReflectionMethod(Collection::class, 'shouldBeSearchable'))->getFileName();
    $traitFile = (new ReflectionClass(Searchable::class))->getFileName();

    expect($sourceFile)->toBe($traitFile)
        ->and(Collection::factory()->private()->create()->shouldBeSearchable())->toBeTrue()
        ->and(Collection::factory()->create()->shouldBeSearchable())->toBeTrue();
});
