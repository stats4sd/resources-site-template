<?php

namespace App\Services;

use App\Models\Trove;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

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
        'reviewer_id',
        'requester_id',
        'review_requested_at',
        'reviewed_at',
    ];

    /**
     * Suffix appended to a collection_name to move media out of its live collection
     * without touching disk (see stashMediaForReplacement()). Never a substring that
     * matches a registered collection name (cover_image_*, content_*).
     */
    public const TRASH_SUFFIX = '__superseded';

    /**
     * Return the shadow draft for a published canonical row, creating it (as a copy of
     * the canonical's content, relations and media) on first edit. Idempotent: there is
     * only ever one draft per canonical.
     *
     * $mediaUuidMap is an out-parameter populated with, per media collection, a
     * canonicalUuid => draftUuid map. Callers that filled a form from the canonical need
     * this to rewrite the media components' state onto the draft's (new) UUIDs — without
     * it, the draft's unchanged files would be treated as abandoned and deleted on save.
     */
    public function draftFor(Trove $canonical, array &$mediaUuidMap = []): Trove
    {
        if ($existing = $canonical->draft()->first()) {
            // Idempotent path: no fresh copy to read UUIDs from, so pair the canonical's
            // media to the existing draft's to build the same canonicalUuid => draftUuid map.
            $mediaUuidMap = $this->existingDraftMediaMap($canonical, $existing);

            return $existing;
        }

        return DB::transaction(function () use ($canonical, &$mediaUuidMap) {
            /** @var Trove $draft */
            // A fresh edit of a live Trove starts with a clean review slate: exclude all
            // four review fields so a re-edit can never inherit a stale reviewer/request.
            $draft = $canonical->replicate([
                'published_at',
                'reviewer_id',
                'requester_id',
                'review_requested_at',
                'reviewed_at',
            ]);
            $draft->published_id = $canonical->id;   // links draft -> canonical; published_at stays null
            $draft->save();

            $this->copyRelations($canonical, $draft);
            $mediaUuidMap = $this->copyMedia($canonical, $draft);

            return $draft;
        });
    }

    /**
     * Build the canonicalUuid => draftUuid map for a draft that already exists (no fresh
     * copy to read UUIDs from). Pairs each collection's media by file_name where those
     * names are unambiguous, otherwise positionally. Collections whose counts diverged
     * (the draft was edited independently) are left out of the map entirely — the caller
     * reloads those from the draft rather than remapping stale canonical UUIDs.
     *
     * @return array<string, array<string, string>>
     */
    private function existingDraftMediaMap(Trove $canonical, Trove $draft): array
    {
        $map = [];

        $canonical->getRegisteredMediaCollections()->each(function ($collection) use ($canonical, $draft, &$map) {
            $canonicalMedia = $canonical->getMedia($collection->name)->values();
            $draftMedia = $draft->getMedia($collection->name)->values();

            if ($canonicalMedia->isEmpty() || $canonicalMedia->count() !== $draftMedia->count()) {
                return; // nothing to pair, or divergent: caller reloads from the draft
            }

            $byName = $draftMedia->keyBy('file_name');
            $namesUnique = $byName->count() === $draftMedia->count();

            $canonicalMedia->each(function ($media, $i) use ($byName, $draftMedia, $namesUnique, $collection, &$map) {
                $counterpart = $namesUnique
                    ? ($byName[$media->file_name] ?? $draftMedia[$i])
                    : $draftMedia[$i];
                $map[$collection->name][$media->uuid] = $counterpart->uuid;
            });
        });

        return $map;
    }

    /**
     * Publish $trove and return the live canonical row.
     *
     * - Shadow draft: its content/relations/media are copied onto the canonical (PK
     *   unchanged), the canonical is marked published, the review request cleared (the
     *   approval stamp preserved), and the draft row is discarded.
     * - Never-published canonical: published in place.
     *
     * In both cases publishing always clears the outstanding *request* but preserves the
     * *approval* stamp (reviewed_at + reviewer_id) so "published with a review" stays
     * distinguishable from "published without one".
     */
    public function publish(Trove $draft): Trove
    {
        // Never-published canonical: publish in place.
        if ($draft->published_id === null) {

            return DB::transaction(function () use ($draft) {
                if ($draft->published_at === null) {
                    $draft->published_at = now();
                }
                $this->applyReviewStateOnPublish($draft, $draft);
                $draft->save();

                return $draft;
            });
        }

        // A shadow draft: fold it onto its canonical. Media replacement inside the
        // transaction only stashes the canonical's superseded media (a DB-only rename,
        // see copyMedia/stashMediaForReplacement) — no file is deleted from disk until
        // after commit, so a rollback here always leaves the canonical's original files
        // intact.
        $canonical = DB::transaction(function () use ($draft) {
            /** @var Trove $canonical */
            $canonical = $draft->publishedVersion()->firstOrFail();

            $previousSlug = $canonical->slug;

            // Copy the draft's content raw (as replicate() does).
            // Both rows share the same schema/casts, so the
            // raw representation transfers verbatim; merging over the canonical's own
            // attributes preserves its NON_CONTENT (identity/publishing/review) fields.
            $canonical->setRawAttributes(array_merge(
                $canonical->getAttributes(),
                Arr::except($draft->getAttributes(), self::NON_CONTENT)
            ));

            // Preserve the original publish date and track any slug change for redirects.
            if ($canonical->slug !== $previousSlug) {
                $canonical->previous_slugs = array_values(array_unique(
                    array_merge($canonical->previous_slugs ?? [], [$previousSlug])
                ));
            }
            if ($canonical->published_at === null) {
                $canonical->published_at = now();
            }
            // NON_CONTENT excludes the review fields, so forceFill did not carry the draft's
            // request onto the canonical; set the canonical's review state explicitly from
            // the draft's completion state.
            $this->applyReviewStateOnPublish($canonical, $draft);
            $canonical->save();

            $this->copyRelations($draft, $canonical);
            $this->copyMedia($draft, $canonical, replace: true);

            $this->deleteDraftRow($draft);

            return $canonical->refresh();
        });

        // Physically remove the stashed media only once the outermost transaction has
        // committed. Deferring via afterCommit keeps this correct when publish() runs inside
        // a caller's transaction (e.g. unpublish()): the files are never deleted while an
        // enclosing transaction could still roll back. Best effort — leftover trash rows are
        // swept up by PruneSupersededMedia if this ever fails.
        DB::afterCommit(fn () => $this->purgeSupersededMedia($canonical));

        return $canonical;
    }

    /** Throw away a shadow draft's pending edits, leaving the live canonical untouched. */
    public function discardDraft(Trove $draft): void
    {
        if ($draft->published_id === null) {
            return; // not a shadow draft; nothing to discard
        }

        DB::transaction(fn () => $this->deleteDraftRow($draft));
    }

    /**
     * Delete a logical Trove from any working row. The shadow draft (if any) is
     * hard-deleted — it must never linger soft-deleted, where it keeps occupying
     * unique(published_id) and blocks the next draftFor(). The canonical is
     * soft-deleted (recoverable).
     */
    public function delete(Trove $working): void
    {
        DB::transaction(function () use ($working) {
            $canonical = $working->published_id !== null
                ? $working->publishedVersion()->firstOrFail()
                : $working;

            if ($draft = $canonical->draft()->first()) {
                $this->deleteDraftRow($draft);
            }

            $canonical->delete();
        });
    }

    /**
     * Remove a canonical row from the public site without deleting it. If the canonical
     * has a shadow draft, its pending edits are folded onto the canonical first (and the
     * draft row discarded) so unpublish always leaves a single row, never an orphan draft
     * pointing at an unpublished parent.
     */
    public function unpublish(Trove $canonical): void
    {
        DB::transaction(function () use ($canonical) {
            if ($draft = $canonical->draft()->first()) {
                $this->publish($draft);   // copies content/relations/media onto canonical, deletes draft
                $canonical->refresh();    // publish() mutated a *different* instance (draft->publishedVersion)
            }

            $canonical->published_at = null;
            $canonical->save();
        });
    }

    /**
     * Request a review of $working: assign the reviewer and record who asked. A review is
     * "outstanding" from now until it is completed (reviewed_at) or consumed by publish.
     */
    public function requestReview(Trove $working, User $reviewer, ?User $requester = null): Trove
    {
        $working->review_requested_at = now();
        $working->reviewed_at = null;
        $working->reviewer_id = $reviewer->id;
        $working->requester_id = $requester?->id ?? auth()->id();
        $working->save();

        return $working;
    }

    /**
     * Complete the review of $working. reviewer_id is overwritten with whoever ACTUALLY
     * reviewed (often not the assignee — we keep only the real approver, per decision 2);
     * reviewed_at becomes the durable "✓ reviewed" fact that drives "done".
     */
    public function completeReview(Trove $working, User $reviewer): Trove
    {
        $working->reviewer_id = $reviewer->id;
        $working->reviewed_at = now();
        $working->save();

        return $working;
    }

    /**
     * Set the resulting canonical's review state on publish: always clear the outstanding
     * request; preserve the approval stamp only if the working row was actually reviewed
     * (no false "reviewed by" attribution on a trove published without a review).
     */
    private function applyReviewStateOnPublish(Trove $canonical, Trove $working): void
    {
        $canonical->review_requested_at = null;
        $canonical->requester_id = null;

        if ($working->reviewed_at !== null) {
            $canonical->reviewed_at = $working->reviewed_at;
            $canonical->reviewer_id = $working->reviewer_id;
        } else {
            $canonical->reviewed_at = null;
            $canonical->reviewer_id = null;
        }
    }

    /** Sync the draftable relations (tags, collections) from $from onto $to. */
    private function copyRelations(Trove $from, Trove $to): void
    {
        foreach ($from->getDraftableRelations() as $relation) {
            $to->{$relation}()->sync($from->{$relation}()->get()->pluck('id')->all());
        }
    }

    /**
     * Copy each registered media collection from $from onto $to (replacing $to's when
     * publishing). Returns a per-collection canonicalUuid => copyUuid map so callers that
     * need to follow the copies (e.g. rebinding a form to the draft) can remap UUIDs.
     *
     * @return array<string, array<string, string>>
     */
    private function copyMedia(Trove $from, Trove $to, bool $replace = false): array
    {
        $map = [];

        $from->getRegisteredMediaCollections()->each(function ($collection) use ($from, $to, $replace, &$map) {
            if ($replace) {
                // Stash rather than clearMediaCollection(): renaming collection_name is a
                // DB-only op (no disk delete) and also empties the live collection so
                // singleFile collections don't auto-evict a second file mid-transaction.
                $this->stashMediaForReplacement($to, $collection->name);
            }

            $from->getMedia($collection->name)->each(function ($media) use ($to, $collection, &$map) {
                $copy = $media->copy($to, $media->collection_name, $media->disk);
                $map[$collection->name][$media->uuid] = $copy->uuid;
            });
        });

        return $map;
    }

    /**
     * Move $to's existing media in $collectionName out of the live collection into a
     * per-publish "trash" collection, by renaming collection_name only. Spatie's
     * DefaultPathGenerator keys storage paths on media.id, not collection_name, so this
     * moves no files on disk. Uses a bulk query-builder update (not per-model saves) so
     * no Spatie model events fire and nothing is touched on disk.
     *
     * Safe inside a transaction: on rollback the rename reverts and the live media is
     * exactly as it was; the actual files are deleted later by purgeSupersededMedia().
     */
    private function stashMediaForReplacement(Trove $to, string $collectionName): void
    {
        $to->media()
            ->where('collection_name', $collectionName)
            ->update(['collection_name' => $collectionName.self::TRASH_SUFFIX]);
    }

    /**
     * Permanently delete media previously stashed by stashMediaForReplacement(). Must
     * only be called after the publish transaction has committed — $media->delete()
     * removes the original, conversions and responsive images from disk and cannot be
     * rolled back. Failure here is logged, not thrown: the publish has already
     * succeeded, and any leftover trash is reclaimed by the PruneSupersededMedia sweep.
     */
    private function purgeSupersededMedia(Trove $canonical): void
    {
        try {
            $canonical->media()
                ->where('collection_name', 'like', '%'.self::TRASH_SUFFIX)
                ->get()
                ->each(fn (Media $media) => $media->delete());
        } catch (Throwable $e) {
            Log::error('Failed to purge superseded media after publish', [
                'trove_id' => $canonical->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Detach pivots and hard-delete a shadow draft row. The draft's media files are removed
     * only after the enclosing transaction commits: the row is force-deleted while PRESERVING
     * its media (Spatie's deleting hook is skipped), and the files are deleted via afterCommit.
     * A rollback therefore leaves the files intact; a leftover orphan is reclaimed by
     * PruneSupersededMedia.
     */
    private function deleteDraftRow(Trove $draft): void
    {
        foreach ($draft->getDraftableRelations() as $relation) {
            $draft->{$relation}()->detach();
        }

        $mediaIds = $draft->media()->pluck('id')->all();

        $draft->forceDeletePreservingMedia();

        DB::afterCommit(function () use ($mediaIds) {
            Media::whereIn('id', $mediaIds)->get()->each->delete();
        });
    }
}
