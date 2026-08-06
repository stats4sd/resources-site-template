<?php

use Database\Seeders\Prep\SiteContentSeeder;

beforeEach(function () {
    bootPublicSite();
    $this->seed(SiteContentSeeder::class);
});

it('redirects the root to /home', function () {
    $this->get('/')->assertRedirect('/home');
});

it('renders the home page with site content', function () {
    $response = $this->get('/home');

    $response->assertOk()
        ->assertSee('Resources Library'); // seeded home_heading_line2
});
