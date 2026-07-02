<?php

namespace App\Services;

use App\Models\Trove;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Owns every lifecycle transition for the published + single-shadow-draft model.
 * This is the ONLY place that mutates published_at / the shadow-draft row, so the
 * rules (stable canonical PK, at most one draft, review fields cleared on publish)
 * live in exactly one testable place.
 */
class TrovePublisher
{
    /**
     * Attributes that must never be copied between the canonical row and its draft:
     * identity, publishing state and the review handshake are per-row, not content.
     */
    private const NON_CONTENT = [
        'id',
        'published_id',
        'published_at',
        'created_at',
        'updated_at',
        'deleted_at',
        'checker_id',
        'requester_id',
    ];

    /**
     * Return the shadow draft for a published canonical row, creating it (as a copy of
     * the canonical's content, relations and media) on first edit. Idempotent: there is
     * only ever one draft per canonical.
     */
    public function draftFor(Trove $canonical): Trove
    {
        if ($existing = $canonical->draft()->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($canonical) {
            /** @var Trove $draft */
            $draft = $canonical->replicate(['published_at', 'checker_id', 'requester_id']);
            $draft->published_id = $canonical->id;   // links draft -> canonical; published_at stays null
            $draft->save();

            $this->copyRelations($canonical, $draft);
            $this->copyMedia($canonical, $draft);

            return $draft;
        });
    }

    /**
     * Publish $trove and return the live canonical row.
     *
     * - Shadow draft: its content/relations/media are copied onto the canonical (PK
     *   unchanged), the canonical is marked published, review fields cleared, and the
     *   draft row is discarded.
     * - Never-published canonical: published in place.
     */
    public function publish(Trove $draft): Trove
    {
        // Never-published canonical: publish in place and clear review fields.
        if ($draft->published_id === null) {

            return DB::transaction(function () use ($draft) {
                if ($draft->published_at === null) {
                    $draft->published_at = now();
                }
                $draft->checker_id = null;
                $draft->requester_id = null;
                $draft->save();

                return $draft;
            });
        }

        // A shadow draft: fold it onto its canonical.
        return DB::transaction(function () use ($draft) {
            /** @var Trove $canonical */
            $canonical = $draft->publishedVersion()->firstOrFail();

            $previousSlug = $canonical->slug;

            $canonical->forceFill(Arr::except($draft->getAttributes(), self::NON_CONTENT));

            // Preserve the original publish date and track any slug change for redirects.
            if ($canonical->slug !== $previousSlug) {
                $canonical->previous_slugs = array_values(array_unique(
                    array_merge($canonical->previous_slugs ?? [], [$previousSlug])
                ));
            }
            if ($canonical->published_at === null) {
                $canonical->published_at = now();
            }
            $canonical->checker_id = null;
            $canonical->requester_id = null;
            $canonical->save();

            $this->copyRelations($draft, $canonical);
            $this->copyMedia($draft, $canonical, replace: true);

            $this->deleteDraftRow($draft);

            return $canonical->refresh();
        });
    }

    /** Throw away a shadow draft's pending edits, leaving the live canonical untouched. */
    public function discardDraft(Trove $draft): void
    {
        if ($draft->published_id === null) {
            return; // not a shadow draft; nothing to discard
        }

        DB::transaction(fn () => $this->deleteDraftRow($draft));
    }

    /** Remove a canonical row from the public site without deleting it (documented Unpublish). */
    public function unpublish(Trove $canonical): void
    {
        $canonical->published_at = null;
        $canonical->save();
    }

    /** Sync the draftable relations (tags, troveTypes, collections) from $from onto $to. */
    private function copyRelations(Trove $from, Trove $to): void
    {
        foreach ($from->getDraftableRelations() as $relation) {
            $to->{$relation}()->sync($from->{$relation}()->get()->pluck('id')->all());
        }
    }

    /** Copy each registered media collection from $from onto $to (replacing $to's when publishing). */
    private function copyMedia(Trove $from, Trove $to, bool $replace = false): void
    {
        $from->getRegisteredMediaCollections()->each(function ($collection) use ($from, $to, $replace) {
            if ($replace) {
                $to->clearMediaCollection($collection->name);
            }

            $from->getMedia($collection->name)->each(
                fn ($media) => $media->copy($to, $media->collection_name, $media->disk)
            );
        });
    }

    /** Detach pivots and hard-delete a shadow draft row (its media go with the model delete). */
    private function deleteDraftRow(Trove $draft): void
    {
        foreach ($draft->getDraftableRelations() as $relation) {
            $draft->{$relation}()->detach();
        }

        $draft->forceDelete();
    }
}
