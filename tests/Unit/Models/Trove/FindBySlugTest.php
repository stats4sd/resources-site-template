<?php

use App\Models\Trove;

it('resolves a published canonical by slug', function () {
    $trove = publishedTrove(['title' => ['en' => 'Findable']]);

    expect(Trove::findBySlugOrRedirect($trove->slug)?->is($trove))->toBeTrue();
});

it('resolves a published canonical by numeric id', function () {
    $trove = publishedTrove();

    expect(Trove::findBySlugOrRedirect((string) $trove->id)?->is($trove))->toBeTrue();
});

it('resolves via a previous_slugs string entry', function () {
    $trove = publishedTrove();
    $trove->previous_slugs = ['an-old-slug'];
    $trove->saveQuietly();

    expect(Trove::findBySlugOrRedirect('an-old-slug')?->is($trove))->toBeTrue();
});

it('resolves via a previous_slugs numeric entry', function () {
    $trove = publishedTrove();
    // A prior route key that was numeric (e.g. a bare id). No live trove has id 987654.
    $trove->previous_slugs = [987654];
    $trove->saveQuietly();

    expect(Trove::findBySlugOrRedirect('987654')?->is($trove))->toBeTrue();
});

it('returns null when nothing matches', function () {
    publishedTrove();

    expect(Trove::findBySlugOrRedirect('does-not-exist'))->toBeNull();
});

it('never resolves an unpublished draft', function () {
    $canonical = publishedTrove();
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->draftOf($canonical)
        ->create(['title' => ['en' => 'Draft Only']]));

    // The draft is unpublished (published_at null) and carries published_id, so both the
    // PublishedScope and the explicit whereNull('published_id') guard exclude it.
    expect(Trove::findBySlugOrRedirect($draft->slug))->toBeNull();
});
