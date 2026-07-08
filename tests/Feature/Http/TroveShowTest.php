<?php

use App\Models\Trove;
use App\Services\TrovePublisher;

beforeEach(fn () => bootPublicSite());

it('shows a published trove', function () {
    $trove = publishedTrove(['title' => ['en' => 'A Findable Resource']]);

    $this->get('/resources/'.$trove->slug)
        ->assertOk()
        ->assertSee('A Findable Resource');
});

it('404s for an unknown slug', function () {
    $this->get('/resources/no-such-slug')->assertNotFound();
});

it('301-redirects a previous slug to the canonical slug', function () {
    $trove = publishedTrove();
    $trove->previous_slugs = ['legacy-slug'];
    $trove->saveQuietly();

    $this->get('/resources/legacy-slug')
        ->assertStatus(301)
        ->assertRedirect(route('resources.show', ['troveKey' => $trove->slug]));
});

it('301-redirects the old slug after unpublish, retitle, republish', function () {
    $publisher = new TrovePublisher;
    $trove = publishedTrove(['title' => ['en' => 'First Name']]);
    $oldSlug = $trove->slug;

    $publisher->unpublish($trove->fresh());

    $working = Trove::withDrafts()->find($trove->id);
    Trove::withoutSyncingToSearch(fn () => $working->update(['title' => ['en' => 'Second Name']]));

    $publisher->publish($working->fresh());
    $newSlug = $trove->fresh()->slug;

    expect($newSlug)->not->toBe($oldSlug);

    $this->get('/resources/'.$oldSlug)
        ->assertStatus(301)
        ->assertRedirect(route('resources.show', ['troveKey' => $newSlug]));
});

it('does not show an unpublished trove on the public route', function () {
    $trove = draftTrove();

    $this->get('/resources/'.$trove->slug)->assertNotFound();
});
