<?php

namespace App\Observers;

use App\Models\Collection;
use App\Models\Tag;
use App\Models\Trove;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Tags are denormalised into the search index (tag_ids/tag_names on troves,
 * aggregated tag_ids on collections), so tag changes outside the trove-save path
 * must reindex the affected documents.
 */
class TagObserver
{
    /**
     * taggables.tag_id cascades on delete, so the member troves must be captured at
     * `deleting` (pivot rows still present) and reindexed at `deleted` (documents
     * rebuilt without the tag). Static because the event dispatcher resolves a fresh
     * observer instance per event — instance state would not survive from `deleting`
     * to `deleted`.
     *
     * @var array<int, array<int, int>>
     */
    private static array $troveIdsPendingDelete = [];

    public function saved(Tag $tag): void
    {
        // False after an insert (Eloquent only syncs changes on updates), so a freshly
        // created tag — which cannot have troves yet — never triggers a reindex.
        if (! $tag->wasChanged('name')) {
            return;
        }

        $this->reindexTrovesAndTheirCollections($this->publishedTroveIds($tag));
    }

    public function deleting(Tag $tag): void
    {
        self::$troveIdsPendingDelete[$tag->id] = $this->publishedTroveIds($tag);
    }

    public function deleted(Tag $tag): void
    {
        $troveIds = self::$troveIdsPendingDelete[$tag->id] ?? [];
        unset(self::$troveIdsPendingDelete[$tag->id]);

        $this->reindexTrovesAndTheirCollections($troveIds);
    }

    /**
     * Only published canonical rows are indexed — constrained explicitly rather than
     * via the ambient PublishedScope, which self-disables in Filament panel contexts.
     *
     * @return array<int, int>
     */
    private function publishedTroveIds(Tag $tag): array
    {
        return $tag->troves()
            ->whereNotNull('troves.published_at')
            ->whereNull('troves.published_id')
            ->pluck('troves.id')
            ->all();
    }

    /**
     * @param  array<int, int>  $troveIds
     */
    private function reindexTrovesAndTheirCollections(array $troveIds): void
    {
        if ($troveIds === []) {
            return;
        }

        Trove::query()
            ->whereNotNull('published_at')
            ->whereNull('published_id')
            ->whereIn('id', $troveIds)
            ->chunkById(500, fn (EloquentCollection $troves) => $troves->searchable());

        Collection::query()
            ->whereHas('troves', fn (Builder $query) => $query->whereIn('troves.id', $troveIds))
            ->chunkById(500, fn (EloquentCollection $collections) => $collections->searchable());
    }
}
