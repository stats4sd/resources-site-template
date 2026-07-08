<?php

use App\Models\Trove;

it('generates a slug from the first title locale on create', function () {
    $trove = draftTrove(['title' => ['en' => 'Hello World']]);

    expect($trove->slug)->toBe('hello-world');
});

it('updates the slug when the title of a never-published trove changes', function () {
    $trove = draftTrove(['title' => ['en' => 'Original Title']]);

    Trove::withoutSyncingToSearch(fn () => $trove->update(['title' => ['en' => 'Renamed Title']]));

    expect($trove->slug)->toBe('renamed-title');
});

it('never changes the slug of a published canonical when the title changes', function () {
    $trove = publishedTrove(['title' => ['en' => 'Published Title']]);

    Trove::withoutSyncingToSearch(fn () => $trove->update(['title' => ['en' => 'Renamed Published Title']]));

    expect($trove->slug)->toBe('published-title');
});

it('never changes the slug of a shadow draft when the title changes', function () {
    $canonical = publishedTrove(['title' => ['en' => 'Canonical Title']]);
    // TrovePublisher::draftFor() copies the canonical's slug onto the draft; mirror that here.
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->draftOf($canonical)
        ->withSlug($canonical->slug)
        ->create(['title' => ['en' => 'Canonical Title']]));

    Trove::withoutSyncingToSearch(fn () => $draft->update(['title' => ['en' => 'Edited On The Draft']]));

    expect($draft->slug)->toBe('canonical-title');
});

it('keeps a pre-set slug on a row that has a published version', function () {
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->published()
        ->withSlug('my-custom-slug')
        ->create(['title' => ['en' => 'Completely Different Title']]));

    expect($trove->slug)->toBe('my-custom-slug');
});

it('appends the first free numeric suffix when the base slug is taken', function () {
    $title = ['en' => 'Repeated Title'];

    $first = draftTrove(['title' => $title]);
    $second = draftTrove(['title' => $title]);

    expect($first->slug)->toBe('repeated-title')
        ->and($second->slug)->toBe('repeated-title-2');
});

it('does not generate a slug that collides with an existing numbered slug', function () {
    $numbered = draftTrove(['title' => ['en' => 'Foo 1']]);
    $base = draftTrove(['title' => ['en' => 'Foo']]);
    $another = draftTrove(['title' => ['en' => 'Foo']]);

    expect($numbered->slug)->toBe('foo-1')
        ->and($base->slug)->toBe('foo')
        ->and($another->slug)->toBe('foo-2')
        ->and(collect([$numbered, $base, $another])->pluck('slug')->unique())->toHaveCount(3);
});

it('does not collide with its own row when regenerating on a title change', function () {
    $trove = draftTrove(['title' => ['en' => 'Solo Title']]);

    Trove::withoutSyncingToSearch(fn () => $trove->update(['title' => ['en' => 'Solo Title Renamed']]));
    Trove::withoutSyncingToSearch(fn () => $trove->update(['title' => ['en' => 'Solo Title']]));

    // Regenerating back to the original slug must exclude the trove itself from the
    // uniqueness count, so no -1 suffix appears.
    expect($trove->slug)->toBe('solo-title');
});

it('counts trashed rows when checking slug uniqueness', function () {
    $title = ['en' => 'Trashed Collision'];

    $first = draftTrove(['title' => $title]);
    $first->delete(); // soft delete

    $second = draftTrove(['title' => $title]);

    expect($second->slug)->toBe('trashed-collision-2');
});

it('counts shadow-draft rows when checking slug uniqueness', function () {
    $title = ['en' => 'Draft Collision'];

    $canonical = publishedTrove(['title' => $title]);
    // A draft created without a pinned slug generates its own; it must see the canonical
    // through withDrafts() and gain a suffix rather than duplicating the base.
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->draftOf($canonical)
        ->create(['title' => $title]));

    expect($canonical->slug)->toBe('draft-collision')
        ->and($draft->slug)->toBe('draft-collision-2');
});
