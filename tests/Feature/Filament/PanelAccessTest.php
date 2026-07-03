<?php

use App\Filament\Pages\SiteContentPage;
use App\Filament\Pages\SiteOptionsPage;
use App\Filament\Resources\CollectionResource;
use App\Filament\Resources\TagResource;
use App\Filament\Resources\TagTypeResource;
use App\Filament\Resources\TroveResource;
use App\Filament\Resources\TroveTypeResource;
use App\Models\Collection;
use App\Models\TagType;

it('renders the custom login page for a guest', function () {
    $this->get('/admin/login')->assertOk();
});

it('renders resource list and create pages for an authenticated user', function () {
    actingAsAdmin();

    $this->get(TroveResource::getUrl('index'))->assertOk();
    $this->get(TroveResource::getUrl('create'))->assertOk();
    $this->get(CollectionResource::getUrl('index'))->assertOk();
    $this->get(CollectionResource::getUrl('create'))->assertOk();
    $this->get(TroveTypeResource::getUrl('index'))->assertOk();
    $this->get(TagResource::getUrl('index'))->assertOk();
    $this->get(TagTypeResource::getUrl('index'))->assertOk();
});

it('renders record edit and view pages', function () {
    actingAsAdmin();

    $trove = publishedTrove();
    $collection = Collection::factory()->create();
    $tagType = TagType::factory()->create();

    $this->get(TroveResource::getUrl('edit', ['record' => $trove]))->assertOk();
    $this->get(CollectionResource::getUrl('edit', ['record' => $collection]))->assertOk();
    $this->get(CollectionResource::getUrl('view', ['record' => $collection]))->assertOk();
    $this->get(TagTypeResource::getUrl('edit', ['record' => $tagType]))->assertOk();
});

it('renders the custom Site Options and Site Content pages', function () {
    actingAsAdmin();

    $this->get(SiteOptionsPage::getUrl())->assertOk();
    $this->get(SiteContentPage::getUrl())->assertOk();
});
