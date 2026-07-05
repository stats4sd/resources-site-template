# Change log: Explicit Review state + "Review" workflow on the Trove model

**Date**: 2026-07-02
**Branch**: `trove-review-update`
**Plan**: [docs/plans/trove-review-state-and-workflow.md](../plans/trove-review-state-and-workflow.md)

Made review a first-class, explicitly-recorded fact on the Trove working row, unified all vocabulary on **"Review"** (was a "Check"/"Review" split), and reworked the admin UI so the draft → review → publish → re-draft path is visible at a glance and surfaces each user's outstanding review tasks. Review remains **optional** — the user is guided toward it but Publish is always reachable. State lives on the Trove (no `Review` model, no review history), consistent with the single-shadow-draft model from the drafts-removal work.

## Data model

- Rewrote `database/migrations/2023_11_24_132900_create_troves_table.php` in place (fresh-migrate, per project convention):
  - Renamed `checker_id` → **`reviewer_id`** (assigned reviewer while a review is outstanding; overwritten with whoever *actually* reviewed + approved on completion — we keep only the real approver, no separate `approved_by`).
  - Kept `requester_id` (who asked).
  - Added **`review_requested_at`** (a review is *outstanding* iff this is set and `reviewed_at` is null; cleared on publish) and **`reviewed_at`** (the durable "✓ reviewed" fact; preserved on the canonical across publish).

## New code

- **`app/Enums/ReviewStatus.php`** — backed string enum (`Draft`, `InReview`, `Published`, `PublishedWithPendingChanges`) implementing Filament's `HasLabel`/`HasColor`/`HasIcon` so badge columns render automatically. `reviewed_at` is deliberately **not** an enum member — it is an orthogonal "✓ reviewed" marker rendered alongside the badge, keeping the status set to the four the request asked for.
- **`app/Filament/Resources/TroveResource/Concerns/HasTroveFormActions.php`** — shared trait giving CreateTrove/EditTrove the three explicit footer actions (Save draft / Request review / Publish) plus `finalizeTroveSave()` and the saved-notification title. The user's intent is now the button they press, not a radio value cross-referenced against visibility-toggled fieldsets.

## Model — `app/Models/Trove.php`

- Renamed `checker()` relation → **`reviewer()`** (`belongsTo(User, 'reviewer_id')`); kept `requester()`.
- Added **`reviewStatus(): Attribute`** — the single derivation of `ReviewStatus` from the working row's flags (precedence: outstanding review → shadow-draft-of-live → live → never-published). Every consumer reads this; nothing re-derives flag combinations.
- Added `reviewInProgress` convenience accessor and **`scopeAwaitingReviewBy($userId)`** (the personal review queue).
- Cast `review_requested_at`, `reviewed_at` as `datetime`.

## Domain — `app/Services/TrovePublisher.php`

- Added **`requestReview($working, $reviewer, ?$requester)`** and **`completeReview($working, $reviewer)`** (records the *actual* approver).
- `NON_CONTENT`: renamed `checker_id` → `reviewer_id`; added `review_requested_at`, `reviewed_at`.
- `draftFor()`: the `replicate()` exclusion list now excludes all four review fields, so a fresh edit of a live Trove starts with a clean review slate (this is what makes preserving `reviewed_at` on the canonical safe).
- `publish()`: replaced the blanket "clear checker/requester" with review-aware handling in a shared `applyReviewStateOnPublish()` helper — always clears the request; preserves the approval stamp (`reviewed_at` + `reviewer_id`) only if the working row was actually reviewed (no false "reviewed by" attribution on a trove published without review).

## Filament UI

- **`TroveResource.php`**: renamed the wizard step `Check` → **`Review`**; its body is now just the guidance `Shout` (no promise of notifications). Deleted the radio + three visibility-toggled fieldsets and the `should_publish`/`are_you_sure*` stale-`$record` guards. Added a **Status** badge column (reads `review_status`) with a "✓ reviewed by {name}" sub-description when `reviewed_at` is set.
- **`CreateTrove.php` / `EditTrove.php`**: use the shared trait for footer actions; dropped the `checker_id`→`requester_id` stamping hack (now handled inside `requestReview`). Publishing/review-request are applied in `afterCreate()`/`afterSave()` via `finalizeTroveSave()`.
- **`EditTrove.php`**: added a **Mark as reviewed** header action (visible only when the working row is `InReview`) calling `completeReview($record, auth()->user())`. Kept Discard draft changes / Unpublish (Review-language copy).
- **`ListTroves.php`**: rebuilt tabs — **All / Drafts / In review / Needs my review** (personal queue via `awaitingReviewBy`, with a count badge) **/ Published**.
- Deleted the now-unused `app/Filament/Forms/Components/Actions/SaveDraftFormAction.php`.

## Verification

- `php artisan migrate:fresh --seed` succeeds.
- Full lifecycle exercised via tinker: create → request review (assignee only appears in their queue) → complete review as a *different* user (reviewer_id overwritten with the actual approver, `reviewed_at` set) → publish (stamp preserved, request cleared) → edit (draft starts with a clean review slate — bug 1.3) → publish changes without review (no false attribution). All states derived correctly by `ReviewStatus`.
- Pre-existing Laravel scaffold test failures (auth/profile/example — bcrypt config + removed routes) are unrelated to this change; there is no automated Trove review suite yet.

## Deferred (unchanged from plan)

- Domain events (`ReviewRequested`, `TrovePublished`) and real notifications to the assigned reviewer/team. The service is structured to host these at a single call site later.
- Any review *history*.
