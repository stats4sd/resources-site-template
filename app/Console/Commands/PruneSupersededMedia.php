<?php

namespace App\Console\Commands;

use App\Models\Scopes\PublishedScope;
use App\Models\Trove;
use App\Services\TrovePublisher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PruneSupersededMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-superseded-media';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes leftover Trove media whose post-commit cleanup failed: superseded rows still carrying the publish "trash" suffix, and orphans whose Trove row no longer exists.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Every Trove row that still exists (published or not, drafts and trashed included).
        // PublishedScope must be dropped explicitly: this runs in a console context where it
        // otherwise restricts the subquery to published canonicals, so leftover media on a
        // later-unpublished canonical would never match.
        $existingTroveIds = fn () => Trove::withoutGlobalScope(PublishedScope::class)
            ->withTrashed()
            ->select('id');

        Media::query()
            ->where('model_type', Trove::class)
            ->where(function (Builder $query) use ($existingTroveIds) {
                $query
                    ->where(fn (Builder $superseded) => $superseded
                        ->where('collection_name', 'like', '%'.TrovePublisher::TRASH_SUFFIX)
                        ->whereIn('model_id', $existingTroveIds()))
                    ->orWhereNotIn('model_id', $existingTroveIds());
            })
            ->get()
            ->each(fn (Media $media) => $media->delete());
    }
}
