<?php

use App\Models\Trove;

beforeEach(fn () => bootPublicSite());

it('returns an empty response to a guest', function () {
    $trove = publishedTrove();

    $response = $this->get('/resources/preview/'.$trove->slug);

    // The route returns nothing (null) for guests -> empty 200 body.
    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('shows the working (draft) version to an authenticated user', function () {
    actingAsAdmin();

    $canonical = publishedTrove(['title' => ['en' => 'Live Version']]);
    $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()
        ->draftOf($canonical)
        ->create(['title' => ['en' => 'Draft Version In Progress']]));

    $this->get('/resources/preview/'.$draft->slug)
        ->assertOk()
        ->assertSee('Draft Version In Progress');
});
