<?php

namespace App\Services\Search;

use App\Contracts\SearchesLibrary;
use App\Models\Collection;
use App\Models\Trove;
use Illuminate\Database\Eloquent\Builder;

/**
 * Degraded fallback for installs without Meilisearch (SCOUT_DRIVER=null) and for
 * engine outages: no text ranking and no facet counts, just DB-side filters over a
 * date-ordered listing. Portable SQL only — this path must run on SQLite.
 */
class DatabaseLibrarySearch implements SearchesLibrary
{
    public function search(LibrarySearchRequest $request): LibrarySearchResult
    {
        $troves = $this->troveQuery($request)
            ->get(['id', 'published_at'])
            ->map(fn (Trove $trove) => [
                'hit' => new LibraryHit(type: 'trove', id: $trove->id, score: 0.0),
                'date' => $trove->published_at?->getTimestamp() ?? 0,
            ]);

        $collections = $this->collectionQuery($request)
            ->get(['id', 'created_at'])
            ->map(fn (Collection $collection) => [
                'hit' => new LibraryHit(type: 'collection', id: $collection->id, score: 0.0),
                'date' => $collection->created_at?->getTimestamp() ?? 0,
            ]);

        $merged = $troves->concat($collections)->sortByDesc('date')->values();

        $totalHits = $merged->count();

        $hits = $merged
            ->slice(($request->page - 1) * $request->perPage, $request->perPage)
            ->pluck('hit')
            ->values()
            ->all();

        return new LibrarySearchResult(
            hits: $hits,
            totalHits: $totalHits,
            totalPages: (int) ceil($totalHits / max(1, $request->perPage)),
            facets: null,
        );
    }

    /**
     * Published canonical troves only — constrained explicitly rather than via the
     * ambient PublishedScope, which self-disables in Filament panel contexts.
     */
    protected function troveQuery(LibrarySearchRequest $request): Builder
    {
        $query = Trove::query()
            ->whereNotNull('published_at')
            ->whereNull('published_id');

        foreach ($request->tagIdsByType as $tagIds) {
            if ($tagIds === []) {
                continue;
            }

            $query->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('tags.id', $tagIds));
        }

        if ($request->troveTypeIds !== []) {
            $query->whereIn('trove_type_id', $request->troveTypeIds);
        }

        if ($request->locales !== []) {
            $query->whereLocales('title', $request->locales);
        }

        return $query;
    }

    /**
     * Public collections; tag and type filters apply through published canonical
     * member troves, mirroring the aggregated attributes in the Meilisearch index.
     */
    protected function collectionQuery(LibrarySearchRequest $request): Builder
    {
        $query = Collection::query()->where('public', true);

        foreach ($request->tagIdsByType as $tagIds) {
            if ($tagIds === []) {
                continue;
            }

            $query->whereHas('troves', fn (Builder $troveQuery) => $troveQuery
                ->whereNotNull('published_at')
                ->whereNull('published_id')
                ->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('tags.id', $tagIds)));
        }

        if ($request->troveTypeIds !== []) {
            $query->whereHas('troves', fn (Builder $troveQuery) => $troveQuery
                ->whereNotNull('published_at')
                ->whereNull('published_id')
                ->whereIn('trove_type_id', $request->troveTypeIds));
        }

        if ($request->locales !== []) {
            $query->whereLocales('title', $request->locales);
        }

        return $query;
    }
}
