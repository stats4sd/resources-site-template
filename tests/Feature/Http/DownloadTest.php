<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    bootPublicSite();
});

// NB: downloadAllFilesAsZip() always calls getDownloadableLinks(), which iterates the
// youtube_links/external_links translations. A genuinely null links column makes
// getTranslation() return '' (not null), which the method's `?? []` guard misses and a
// foreach then chokes on — a latent fragility flagged in the change log. Here we give the
// troves explicit (empty) link translations so we exercise the intended download paths.
$emptyLinks = ['external_links' => ['en' => []], 'youtube_links' => ['en' => []]];

it('streams a zip when the trove has downloadable content media', function () use ($emptyLinks) {
    $trove = publishedTrove($emptyLinks);
    $trove->addMediaFromString('file body')->usingFileName('report.pdf')->toMediaCollection('content_en');

    $this->get('/download-all-zip/'.$trove->slug)->assertOk();
});

it('redirects back with an error when nothing is downloadable', function () use ($emptyLinks) {
    $trove = publishedTrove($emptyLinks); // no media, empty links

    $this->get('/download-all-zip/'.$trove->slug)
        ->assertRedirect()
        ->assertSessionHas('error');
});
