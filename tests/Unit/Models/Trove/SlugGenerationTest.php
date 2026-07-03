<?php

use App\Models\Trove;

it('generates a slug of the first title locale plus the date', function () {
    $trove = draftTrove(['title' => ['en' => 'Hello World']]);

    expect($trove->slug)->toBe('hello-world-'.now()->format('Y-m-d'));
});

it('does not regenerate a slug that is already set', function () {
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->withSlug('my-custom-slug')
        ->create(['title' => ['en' => 'Completely Different Title']]));

    expect($trove->slug)->toBe('my-custom-slug');
});

it('appends a suffix equal to the number of rows sharing the base slug', function () {
    $title = ['en' => 'Repeated Title'];
    $base = 'repeated-title-'.now()->format('Y-m-d');

    $first = draftTrove(['title' => $title]);
    $second = draftTrove(['title' => $title]);

    // The suffix is the count of rows matching the *base* slug; only $first holds the exact
    // base, so $second becomes base-1. (The generator counts the base, not prior suffixes.)
    expect($first->slug)->toBe($base)
        ->and($second->slug)->toBe($base.'-1');
});

it('counts trashed rows when checking slug uniqueness', function () {
    $title = ['en' => 'Trashed Collision'];
    $base = 'trashed-collision-'.now()->format('Y-m-d');

    $first = draftTrove(['title' => $title]);
    $first->delete(); // soft delete

    $second = draftTrove(['title' => $title]);

    expect($second->slug)->toBe($base.'-1');
});

it('counts shadow-draft rows when checking slug uniqueness', function () {
    $title = ['en' => 'Draft Collision'];
    $base = 'draft-collision-'.now()->format('Y-m-d');

    $canonical = publishedTrove(['title' => $title]);
    // A shadow draft shares the same generated slug base; it must still count.
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->draftOf($canonical)
        ->create(['title' => $title]));

    // The canonical got the base; the draft collided and gained a suffix.
    expect($canonical->slug)->toBe($base)
        ->and($draft->slug)->toBe($base.'-1');
});
