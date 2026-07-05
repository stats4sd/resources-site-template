**Draft PR description for `trove-review-update` branch**


This PR completely reworks the trove draft / publishing process, and the 'review' workflow.

The goals of this work was to:

1. Simplify: Both the code and the front end was over complicated, making it hard for both devs and users to fully understand the status of any individual trove.
2. Harmonise: The app was reliant on the LaravelDrafts and FilamentDrafts packages to manage the draft/publishing flow, while only using a small amount of those package's features and actually overwriting a bunch of things. This PR removes those external dependencies and brings the logic together within this app.

## Draft --> Publishing Workflow

In this new version, for a 'logical trove', there are now _at most_ 2 versions:

- A canonical version, which is either an unpublished draft, or the current published version
- A "shadow draft" copy, which is a clone of the published version so users can make draft edits without affecting the published version.

Shadow drafts are linked to the canonical version via the `published_id` field. There is a unique() constraint on this field to ensure there is only ever 1 shadow draft copy of a published trove.

Every single state change relating to drafts and publishing is brought together into the `app\Services\TrovePublisher` class. All the logic for publishing, cloning new shadow drafts, and reviewing troves lives here.

## Trove States

The Trove state is determined by 2 enum-backed attributes:

- `publicationState()`
  - Draft (new draft; never published / not visible on the front-end)
  - Published (trove is published / visible on the front-end. No working edits.)
  - PendingChanges (trove is published _and_ has a 'working' copy with draft edits. The published version is visible on the front-end; admins interact with the 'working' copy on the back end to draft changes without affecting the live version).

- `reviewState()`
  - None (the trove has not been reviwed)
  - InReview (a review has been requested and is currently pending.)
  - Reviewed (a review has been completed)


These 2 states are independant of each other. Marking a trove as reviewed _does not_ automatically publish it -> those are 2 separate decisions for users to make.

The 2 statuses are clearly visible on the main List Troves page in the admin panel

### User Workflow

| Step | Description | Records Created | Publication Status | Review Status |
| -- | -- | -- | -- | --
| 1 | New Trove created | 1 (Canonical) | Draft | None
| 2 | Trove is edited | 1 (Canonical) | Draft | None
| 3 | Reviwew is requested | 1 (Canonical) | Draft | InReview
| 4 | More edits occur (reviewer makes edits, or suggests changes etc) | 1 (Canonical) | Draft | InReview
| 5 | Review is approved | 1 (Canonical) | Draft | Reviewed
| 6 | Trove is published | 1 (Canonical) | Published | Reviewed
| 7 | Trove is edited again | 2 (Published Canonical + Shadow Draft) | PendingChanges | None
| 8 | Trove review is requested | 2 (Published Canonical + Shadow Draft) | PendingChanges | InReview
| 9 | Trove review is approved | 2 (Published Canonical + Shadow Draft) | PendingChanges | Reviewed
| 10 | Trove changes are published | 1 (Published Canonical) | Published | Reviewed


Notes:

- In step 7, editing the previously published trove creates a new "Shadow Draft" instance. This new instance has not yet been reviewed, which is why the review status changes.
- In step 10, the edits made to the shadow draft (properties, media files and other relations like tags) are merged into the 'canonical' version. It happens this way so that the 'canonical' version retains the same primary key / slug etc, to ensure long-term consistency.

## Other Considerations / Fixes

### Deletes

1. When reviewing how troves are deleted, it was decided to always hard-delete "shadow drafts" - mainly for simplicity. Soft-deleting would prevent a new shadow draft getting created if that trove was edited again (due to the unique() db constraint), and restoring the soft-deleted entry might be confusing for a user unless there's a clear UI explaining that they are restoring previous edits instead of starting fresh.
2. Canonical troves are always soft-deleted; regardless of Draft or Published status.

### Media handling

When creating a new 'shadow draft', all media linked to the canonical trove are fully copied on disk, so there are 2 copies of the files.

When publishing updates to a live trove (step 10 above), the process is written to prevent accidental file loss:

1. The Canonical media are moved to a temporary `*_superseded` collection. (this doesn't change any files on disk; it's only a database write)
2. The Shadow draft media are then copied over to the canonical trove, filling the regular collections. (this copies the media on disk)
3. Then the shadow drafte is deleted, along with deleting its media on disk.
4. Finally, after the database transaction has completed successfully, the `*_superseded` media are deleted from disk.

There is currently an issue where troves with multiple files get given the sasme file name on publish - see #7.


### Scopes

The 2 sets of enum-backed attributes only work to filter records when the Trove Collection is already loaded into PHP. To enable query-level filtering, we now also have 2 scopes:  `scopeWithPublishedState` and `scopeWithReviewState`. These are written next to the computed attributes and use the same filtering logic, but aren't directly referenced. If one changes, the other _is not automatically changed_ and must be manually updated. This is a structural downside to using php-level 'Attributes'.

When we write a proper test suite for this tool, we should add an explicit test to confirm that these computed attributes and query scopes are using identical logic, so we can immediately tell if they get out of sync.

As a separate note, the global "PublishedScope" added to the Trove is now fully disabled across the whole Filament admin panel (if statement in `PublishedScope`).