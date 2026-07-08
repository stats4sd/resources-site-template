<?php

use App\Models\Trove;
use App\Services\TrovePublisher;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');
    // Simulate the real console/queue context (no Filament panel), where PublishedScope is on.
    usePublicContext();
});

/** A trove carrying a single content file, with its collection renamed to the publish "trash" collection. */
function troveWithSupersededMedia(array $attributes = []): Trove
{
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()->published()->create($attributes));
    $trove->addMediaFromString('stashed content')->usingFileName('old.txt')->toMediaCollection('content_en');
    $trove->media()->update(['collection_name' => 'content_en'.TrovePublisher::TRASH_SUFFIX]);

    return $trove->fresh();
}

it('prunes superseded media left on a later-unpublished canonical', function () {
    $trove = troveWithSupersededMedia();
    $media = $trove->media()->first();
    $path = $media->id.'/'.$media->file_name;

    // Unpublish it: PublishedScope would hide this canonical from a naive withTrashed() subquery.
    $trove->forceFill(['published_at' => null])->saveQuietly();
    expect(Storage::disk('public')->exists($path))->toBeTrue();

    $this->artisan('app:prune-superseded-media')->assertOk();

    expect(Media::find($media->id))->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

it('prunes orphaned trove media whose row has been hard-deleted', function () {
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()->published()->create());
    $trove->addMediaFromString('orphan content')->usingFileName('orphan.txt')->toMediaCollection('content_en');
    $media = $trove->media()->first();
    $path = $media->id.'/'.$media->file_name;

    // Hard-delete the row but preserve its media (the state item 8's deleteDraftRow leaves
    // behind if the post-commit media purge never runs).
    $trove->forceDeletePreservingMedia();
    expect(Storage::disk('public')->exists($path))->toBeTrue();

    $this->artisan('app:prune-superseded-media')->assertOk();

    expect(Media::find($media->id))->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

it('leaves the live media of an existing trove untouched', function () {
    $trove = Trove::withoutSyncingToSearch(fn () => Trove::factory()->published()->create());
    $trove->addMediaFromString('live content')->usingFileName('live.txt')->toMediaCollection('content_en');
    $media = $trove->media()->first();
    $path = $media->id.'/'.$media->file_name;

    $this->artisan('app:prune-superseded-media')->assertOk();

    expect(Media::find($media->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});
