<?php

use App\Models\Collection;

beforeEach(fn () => bootPublicSite());

it('shows an existing collection', function () {
    $collection = Collection::factory()->create(['title' => ['en' => 'My Curated Set']]);

    $this->get('/collections/'.$collection->id)
        ->assertOk()
        ->assertSee('My Curated Set');
});

it('404s for a missing collection', function () {
    $this->get('/collections/999999')->assertNotFound();
});

it('404s for a private collection', function () {
    $collection = Collection::factory()->private()->create();

    $this->get('/collections/'.$collection->id)->assertNotFound();
});
