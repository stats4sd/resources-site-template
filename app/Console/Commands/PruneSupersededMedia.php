<?php

namespace App\Console\Commands;

use App\Models\Trove;
use App\Services\TrovePublisher;
use Illuminate\Console\Command;
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
    protected $description = 'Deletes leftover media stashed during a publish whose post-commit cleanup failed (rows whose collection_name still carries the publish "trash" suffix).';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Media::query()
            ->where('model_type', Trove::class)
            ->where('collection_name', 'like', '%'.TrovePublisher::TRASH_SUFFIX)
            ->whereIn('model_id', Trove::withTrashed()->select('id'))
            ->get()
            ->each(fn (Media $media) => $media->delete());
    }
}
