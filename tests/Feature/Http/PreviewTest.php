<?php

use App\Models\Trove;

beforeEach(fn () => bootPublicSite());

it('redirects a guest to the login page', function () {
    $trove = publishedTrove();

    $this->get('/resources/preview/'.$trove->slug)
        ->assertRedirect(route('filament.admin.auth.login'));
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
