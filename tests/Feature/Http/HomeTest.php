<?php

use Database\Seeders\Prep\SiteContentIfaSeeder;

beforeEach(function () {
    bootPublicSite();
    $this->seed(SiteContentIfaSeeder::class);
});

it('redirects the root to /home', function () {
    $this->get('/')->assertRedirect('/home');
});

it('renders the home page with site content', function () {
    $response = $this->get('/home');

    $response->assertOk()
        ->assertSee('Resource Library: Education for Agroecological Transformations');
});
