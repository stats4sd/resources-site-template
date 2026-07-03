<?php

use App\Models\Collection;
use App\Models\Tag;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;
use App\Services\TrovePublisher;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');
    $this->publisher = new TrovePublisher;
});

/** A published canonical trove with one tag, one troveType, one collection and one content file. */
function canonicalWithEverything(array $attributes = []): Trove
{
    $trove = publishedTrove($attributes);

    $trove->tags()->attach(Tag::factory()->create());
    $trove->troveTypes()->attach(TroveType::factory()->create());
    $trove->collections()->attach(Collection::factory()->create());
    $trove->addMediaFromString('original content')->usingFileName('doc.txt')->toMediaCollection('content_en');

    return $trove->fresh();
}

// -----------------------------------------------------------------------------
// draftFor()
// -----------------------------------------------------------------------------

describe('draftFor', function () {
    it('creates exactly one shadow draft copying content, relations and media', function () {
        $canonical = canonicalWithEverything(['title' => ['en' => 'Live Title']]);
        $map = [];

        $draft = $this->publisher->draftFor($canonical, $map);

        expect(Trove::withDrafts()->where('published_id', $canonical->id)->count())->toBe(1)
            ->and($draft->published_id)->toBe($canonical->id)
            ->and($draft->published_at)->toBeNull()
            ->and($draft->getTranslation('title', 'en'))->toBe('Live Title')
            ->and($draft->tags->pluck('id')->all())->toBe($canonical->tags->pluck('id')->all())
            ->and($draft->troveTypes->pluck('id')->all())->toBe($canonical->troveTypes->pluck('id')->all())
            ->and($draft->collections->pluck('id')->all())->toBe($canonical->collections->pluck('id')->all())
            ->and($draft->getMedia('content_en'))->toHaveCount(1);

        // Fresh path: map pairs canonical media uuid -> draft copy uuid.
        $canonicalUuid = $canonical->getMedia('content_en')->first()->uuid;
        $draftUuid = $draft->getMedia('content_en')->first()->uuid;
        expect($map['content_en'][$canonicalUuid])->toBe($draftUuid);
    });

    it('is idempotent: a second call returns the same draft and no new row', function () {
        $canonical = canonicalWithEverything();

        $first = $this->publisher->draftFor($canonical);
        $secondMap = [];
        $second = $this->publisher->draftFor($canonical->fresh(), $secondMap);

        expect($second->id)->toBe($first->id)
            ->and(Trove::withDrafts()->where('published_id', $canonical->id)->count())->toBe(1);

        // Existing-draft path still yields a canonical->draft uuid map (paired by file_name).
        $canonicalUuid = $canonical->getMedia('content_en')->first()->uuid;
        $draftUuid = $first->getMedia('content_en')->first()->uuid;
        expect($secondMap['content_en'][$canonicalUuid])->toBe($draftUuid);
    });

    it('starts the draft with a clean review slate', function () {
        $reviewer = User::factory()->create();
        $canonical = publishedTrove();
        // Canonical carries a completed review; the draft must not inherit it.
        $canonical->forceFill([
            'reviewed_at' => now(),
            'reviewer_id' => $reviewer->id,
            'review_requested_at' => now(),
        ])->saveQuietly();

        $draft = $this->publisher->draftFor($canonical->fresh());

        expect($draft->reviewed_at)->toBeNull()
            ->and($draft->reviewer_id)->toBeNull()
            ->and($draft->review_requested_at)->toBeNull();
    });
});

// -----------------------------------------------------------------------------
// publish() — never-published canonical
// -----------------------------------------------------------------------------

describe('publish (never-published canonical)', function () {
    it('publishes in place and returns the same instance', function () {
        $trove = draftTrove();
        expect($trove->published_at)->toBeNull();

        $result = $this->publisher->publish($trove);

        expect($result->is($trove))->toBeTrue()
            ->and($trove->fresh()->published_at)->not->toBeNull()
            ->and($trove->fresh()->published_id)->toBeNull();
    });

    it('preserves an existing published_at instead of overwriting it', function () {
        $original = now()->subDays(10)->startOfDay();
        $trove = publishedTrove(['published_at' => $original]);

        $this->publisher->publish($trove);

        expect($trove->fresh()->published_at->equalTo($original))->toBeTrue();
    });

    it('does not fabricate a review approval', function () {
        $trove = draftTrove();

        $this->publisher->publish($trove);

        expect($trove->fresh()->reviewed_at)->toBeNull()
            ->and($trove->fresh()->reviewer_id)->toBeNull();
    });
});

// -----------------------------------------------------------------------------
// publish() — shadow draft
// -----------------------------------------------------------------------------

describe('publish (shadow draft)', function () {
    it('folds the draft onto the canonical with a stable PK and deletes the draft', function () {
        $canonical = canonicalWithEverything(['title' => ['en' => 'Original']]);
        $canonicalId = $canonical->id;

        $draft = $this->publisher->draftFor($canonical->fresh());
        $draft->title = ['en' => 'Edited Title'];
        $draft->saveQuietly();
        // Change relations on the draft.
        $newTag = Tag::factory()->create();
        $draft->tags()->sync([$newTag->id]);

        $result = $this->publisher->publish($draft->fresh());

        expect($result->id)->toBe($canonicalId)
            ->and($result->getTranslation('title', 'en'))->toBe('Edited Title')
            ->and($result->tags->pluck('id')->all())->toBe([$newTag->id])
            ->and(Trove::withDrafts()->find($draft->id))->toBeNull(); // draft row gone
    });

    it('preserves the original published_at on re-publish', function () {
        $original = now()->subDays(30)->startOfDay();
        $canonical = publishedTrove(['published_at' => $original]);
        $draft = $this->publisher->draftFor($canonical->fresh());
        $draft->title = ['en' => 'Changed'];
        $draft->saveQuietly();

        $result = $this->publisher->publish($draft->fresh());

        expect($result->published_at->equalTo($original))->toBeTrue();
    });

    it('records slug changes into previous_slugs, accumulating and deduping', function () {
        $canonical = publishedTrove(['title' => ['en' => 'First Name']]);
        $originalSlug = $canonical->slug;

        // First edit: new title -> new slug. save() (not saveQuietly) so the slug hook fires.
        $draft = $this->publisher->draftFor($canonical->fresh());
        $draft->title = ['en' => 'Second Name'];
        $draft->slug = null; // force regeneration
        $draft->save();
        $canonical = $this->publisher->publish($draft->fresh());
        $secondSlug = $canonical->slug;

        expect($canonical->previous_slugs)->toContain($originalSlug);

        // Second edit: another new slug -> accumulates.
        $draft2 = $this->publisher->draftFor($canonical->fresh());
        $draft2->title = ['en' => 'Third Name'];
        $draft2->slug = null;
        $draft2->save();
        $canonical = $this->publisher->publish($draft2->fresh());

        expect($canonical->previous_slugs)->toContain($originalSlug)
            ->and($canonical->previous_slugs)->toContain($secondSlug)
            // dedup: each old slug appears once
            ->and(array_count_values($canonical->previous_slugs)[$originalSlug])->toBe(1);
    });

    it('preserves the review approval when the draft was reviewed', function () {
        $reviewer = User::factory()->create();
        $canonical = publishedTrove();
        $draft = $this->publisher->draftFor($canonical->fresh());
        $this->publisher->completeReview($draft, $reviewer);

        $result = $this->publisher->publish($draft->fresh());

        expect($result->reviewed_at)->not->toBeNull()
            ->and($result->reviewer_id)->toBe($reviewer->id)
            ->and($result->review_requested_at)->toBeNull(); // request always cleared
    });

    it('does not attribute a review when the draft was only requested, not completed', function () {
        $reviewer = User::factory()->create();
        $canonical = publishedTrove();
        $draft = $this->publisher->draftFor($canonical->fresh());
        $this->publisher->requestReview($draft, $reviewer);

        $result = $this->publisher->publish($draft->fresh());

        expect($result->reviewed_at)->toBeNull()
            ->and($result->reviewer_id)->toBeNull()
            ->and($result->review_requested_at)->toBeNull();
    });

    it('replaces canonical media with the draft copy and purges superseded media after commit', function () {
        $canonical = canonicalWithEverything();
        $originalMedia = $canonical->getMedia('content_en')->first();
        $originalPath = $originalMedia->id.'/'.$originalMedia->file_name;
        expect(Storage::disk('public')->exists($originalPath))->toBeTrue();

        $draft = $this->publisher->draftFor($canonical->fresh());

        $result = $this->publisher->publish($draft->fresh());

        expect($result->getMedia('content_en'))->toHaveCount(1)
            // no leftover superseded rows
            ->and(Media::where('collection_name', 'like', '%'.TrovePublisher::TRASH_SUFFIX)->count())->toBe(0)
            // the original (superseded) file has been purged from disk
            ->and(Storage::disk('public')->exists($originalPath))->toBeFalse();
    });
});

// -----------------------------------------------------------------------------
// publish() — media rollback safety
// -----------------------------------------------------------------------------

it('leaves the canonical media on disk when the publish transaction rolls back', function () {
    $canonical = canonicalWithEverything();
    $originalMedia = $canonical->getMedia('content_en')->first();
    $originalPath = $originalMedia->id.'/'.$originalMedia->file_name;

    $draft = $this->publisher->draftFor($canonical->fresh());

    // Force a failure AFTER the canonical's media has been stashed (a DB-only rename) but
    // before commit: throw when the draft's media copy is created during copyMedia().
    Media::created(function () {
        throw new RuntimeException('boom during media copy');
    });

    try {
        $this->publisher->publish($draft->fresh());
    } catch (RuntimeException $e) {
        // expected
    }

    // Rollback reverted the stash rename; the purge (post-commit) never ran.
    $canonical = $canonical->fresh();
    expect($canonical->getMedia('content_en'))->toHaveCount(1)
        ->and($canonical->getMedia('content_en')->first()->id)->toBe($originalMedia->id)
        ->and(Storage::disk('public')->exists($originalPath))->toBeTrue();
});

// -----------------------------------------------------------------------------
// discardDraft()
// -----------------------------------------------------------------------------

describe('discardDraft', function () {
    it('hard-deletes the draft and leaves the canonical untouched', function () {
        $canonical = canonicalWithEverything(['title' => ['en' => 'Untouched']]);
        $draft = $this->publisher->draftFor($canonical->fresh());
        $draftId = $draft->id;

        $this->publisher->discardDraft($draft);

        expect(Trove::withTrashed()->withDrafts()->find($draftId))->toBeNull()
            ->and($canonical->fresh()->getTranslation('title', 'en'))->toBe('Untouched')
            ->and($canonical->fresh()->trashed())->toBeFalse();
    });

    it('is a no-op when passed a canonical (non-draft) row', function () {
        $canonical = publishedTrove();

        $this->publisher->discardDraft($canonical);

        expect($canonical->fresh())->not->toBeNull()
            ->and($canonical->fresh()->trashed())->toBeFalse();
    });
});

// -----------------------------------------------------------------------------
// delete()
// -----------------------------------------------------------------------------

describe('delete', function () {
    it('soft-deletes the canonical and hard-deletes its draft when called on the canonical', function () {
        $canonical = canonicalWithEverything();
        $draft = $this->publisher->draftFor($canonical->fresh());

        $this->publisher->delete($canonical->fresh());

        expect(Trove::find($canonical->id))->toBeNull()                            // hidden from normal queries
            ->and(Trove::withTrashed()->withDrafts()->find($canonical->id)->trashed())->toBeTrue() // soft-deleted
            ->and(Trove::withTrashed()->withDrafts()->find($draft->id))->toBeNull(); // hard-deleted
    });

    it('resolves the canonical and cleans up when called on the draft', function () {
        $canonical = canonicalWithEverything();
        $draft = $this->publisher->draftFor($canonical->fresh());

        $this->publisher->delete($draft->fresh());

        expect(Trove::withTrashed()->withDrafts()->find($canonical->id)->trashed())->toBeTrue()
            ->and(Trove::withTrashed()->withDrafts()->find($draft->id))->toBeNull();
    });
});

// -----------------------------------------------------------------------------
// unpublish()
// -----------------------------------------------------------------------------

describe('unpublish', function () {
    it('nulls published_at when there is no draft', function () {
        $canonical = publishedTrove();

        $this->publisher->unpublish($canonical);

        // (PublishedScope is off in this admin-panel test context; assert the column directly.)
        expect(Trove::withDrafts()->find($canonical->id)->published_at)->toBeNull();
    });

    it('folds a pending draft in first, then unpublishes, leaving a single row', function () {
        $canonical = canonicalWithEverything(['title' => ['en' => 'Before']]);
        $draft = $this->publisher->draftFor($canonical->fresh());
        $draft->title = ['en' => 'After Edit'];
        $draft->saveQuietly();

        $this->publisher->unpublish($canonical->fresh());

        $rows = Trove::withDrafts()->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->id)->toBe($canonical->id)
            ->and($rows->first()->published_at)->toBeNull()
            ->and($rows->first()->getTranslation('title', 'en'))->toBe('After Edit'); // draft edits folded in
    });
});

// -----------------------------------------------------------------------------
// requestReview() / completeReview()
// -----------------------------------------------------------------------------

describe('requestReview / completeReview', function () {
    it('requestReview assigns the reviewer and records the requester', function () {
        $reviewer = User::factory()->create();
        $requester = User::factory()->create();
        $trove = publishedTrove();

        $this->publisher->requestReview($trove, $reviewer, $requester);

        expect($trove->fresh()->reviewer_id)->toBe($reviewer->id)
            ->and($trove->fresh()->requester_id)->toBe($requester->id)
            ->and($trove->fresh()->review_requested_at)->not->toBeNull()
            ->and($trove->fresh()->reviewed_at)->toBeNull();
    });

    it('requestReview falls back to the authenticated user as requester', function () {
        $reviewer = User::factory()->create();
        $me = actingAsAdmin();
        $trove = publishedTrove();

        $this->publisher->requestReview($trove, $reviewer);

        expect($trove->fresh()->requester_id)->toBe($me->id);
    });

    it('completeReview overwrites the reviewer with the actual approver and stamps reviewed_at', function () {
        $assignee = User::factory()->create();
        $actualReviewer = User::factory()->create();
        $trove = publishedTrove();
        $this->publisher->requestReview($trove, $assignee);

        $this->publisher->completeReview($trove->fresh(), $actualReviewer);

        expect($trove->fresh()->reviewer_id)->toBe($actualReviewer->id)
            ->and($trove->fresh()->reviewed_at)->not->toBeNull();
    });
});
