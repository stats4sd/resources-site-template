# Plan: Explicit Review state + "Review" workflow on the Trove model

**Status:** Completed — see [docs/change-logs/trove-review-state-and-workflow.md](../change-logs/trove-review-state-and-workflow.md).

## Context

This is the deferred follow-up promised by [docs/plans/remove-laravel-drafts-single-shadow-draft.md](remove-laravel-drafts-single-shadow-draft.md) (Decision 3: "defer the review-state enum + notifications") and directly addresses points **2.1, 2.2, 2.3** and bug **1.7** of [docs/from-original-app/trove-review-system.md](../from-original-app/trove-review-system.md). The drafts-removal work left the lifecycle running on an app-owned published + single-shadow-draft model, but "review" is still an *inferred* state: it is read off `checker_id`/`requester_id` combinations with no single source of truth, the language is split between "Check" and "Review", there is no record that a review was actually *completed* (1.7), and the Check step is a radio + three visibility-toggled fieldsets that juggle stale `$record` state (1.4) rather than explicit actions.

This plan makes review a first-class, explicitly-recorded fact on the Trove, unifies all language on **"Review"**, and reworks the management UI so the draft → review → publish → re-draft path is visible at a glance and surfaces the current user's outstanding tasks.

We chose **state-on-the-Trove** over a separate `Review` model. Review state belongs to *one working version's journey to publication*, not to the logical Trove across all time; the single-shadow-draft model already guarantees exactly one working row at a time, so review fields on that row have their lifecycle bounded by the draft's (created on fork, cleared/consumed on publish, discarded with the draft). A `Review` history table would reintroduce the accreting history the drafts-removal plan deliberately removed, and would add a second status enum (`Review.status` vs `Trove.ReviewStatus`) that works against the "minimal user-facing statuses" principle.

## Principles (from the request)

1. **One vocabulary — "Review".** Rename "Check"/"Checker" everywhere: UI copy, code, columns.
2. **Conceptually simple, explainable in one breath.** Minimal user-facing triggers/flags/statuses. A clear, *visible* step-by-step path: create draft → request review → publish → edit without touching the live version → review edits → publish changes. The management list must show each trove's status and highlight the current user's pending tasks.
3. **Review is optional.** Users are *guided* toward it ("two pairs of eyes are better than one") but never *blocked* — Publish is always reachable without a review.

## Decisions (confirmed)

1. **State on the Trove**, no `Review` model, no review history.
2. **`reviewer_id` holds who *actually* reviewed + approved, not who was asked.** When a review is requested, `reviewer_id` is the assigned person; when the review is completed, `reviewer_id` is overwritten with whoever actually did it (which is often a *different* person than assigned — and that's fine, we keep only the real approver). We do **not** add a separate `approved_by` column.
3. **The "reviewed ✓ by X" stamp survives publish** on the live canonical (so you can see the safety net was used), but the *review request* is consumed on publish and a fresh edit starts with a clean review slate.

## Data model

### Columns (rewrite the create-troves migration in place — fresh-migrate, per project convention)

Rename and add on `troves`:

```php
// rename: checker_id  ->  reviewer_id  (assigned reviewer; overwritten with the ACTUAL approver on completion)
$table->foreignId('reviewer_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
// keep: requester_id (who asked for the review)
$table->foreignId('requester_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
// new:
$table->timestamp('review_requested_at')->nullable();   // set when a review is requested; cleared on publish/complete-into-publish
$table->timestamp('reviewed_at')->nullable();            // set when the review is completed/approved; the durable "✓ reviewed" fact
```

Field semantics:

| Field | Meaning |
|---|---|
| `requester_id` | who requested the review (the "you asked" fact). Cleared on publish. |
| `reviewer_id` | during review: the assigned reviewer. After completion: the person who **actually** reviewed + approved. |
| `review_requested_at` | a review is *outstanding* iff this is set and `reviewed_at` is null. Cleared on publish. |
| `reviewed_at` | the review was completed. Preserved on the canonical across publish as the "✓ reviewed by X on <date>" stamp. |

### `ReviewStatus` derived enum

Create `app/Enums/ReviewStatus.php` (backed string enum, `Draft`, `InReview`, `Published`, `PublishedWithPendingChanges`) with `label()` and `color()`/`icon()` helpers for Filament badges. It is computed **once**, in a single model accessor (`reviewStatus(): Attribute`), from the working row's flags. Every consumer (list badge, tabs/filters, publish warnings) reads the enum — nothing re-derives flag combinations (this is what kills the drift behind bugs 1.3/1.4/1.7).

Derivation on the working row (the shadow draft if one exists, else the canonical), by precedence:

1. `review_requested_at != null && reviewed_at == null` → **InReview** (the actionable state wins, whether it is a first publish or pending changes).
2. `published_id != null` (this row is a shadow draft of a live canonical) → **PublishedWithPendingChanges**.
3. `published_at != null` (live canonical, no draft) → **Published**.
4. otherwise (never-published canonical) → **Draft**.

`reviewed_at != null` is an **orthogonal "✓ reviewed" marker** rendered alongside the badge (e.g. a tick + reviewer name), *not* a separate enum member — this keeps the status set to the four the request asked for while still distinguishing "published with a review" from "published without one" (fixes 1.7). Precedence note: a shadow draft that is currently `InReview` shows *In review* rather than *Pending changes*, because the outstanding review is the pending task we want surfaced.

## Domain layer — extend `App\Services\TrovePublisher`

Add the review transitions to the existing service (still the only place lifecycle state mutates; unit-testable without Filament):

```php
requestReview(Trove $working, User $reviewer, ?User $requester = null): Trove
    // review_requested_at = now(); reviewed_at = null; reviewer_id = $reviewer->id;
    // requester_id = ($requester ?? auth user)->id;  save.  (fires ReviewRequested — see "Notifications" below)

completeReview(Trove $working, User $reviewer): Trove
    // reviewer_id = $reviewer->id (the ACTUAL approver, overwriting the assignee);
    // reviewed_at = now(); review_requested_at stays set-or-null (reviewed_at drives "done").  save.
```

Update the existing methods:

- **`draftFor()`** — the `replicate([...])` exclusion list must now exclude **all four** review fields (`reviewer_id`, `requester_id`, `review_requested_at`, `reviewed_at`) so a fresh edit of a live Trove starts with a clean review slate. This is what makes keeping `reviewed_at` on the canonical safe (fixes 1.3 permanently: a re-edit can never inherit a stale reviewer/request).
- **`publish()`** — replace the blanket "clear `checker_id`/`requester_id`" with review-aware handling applied to the resulting **canonical**:
  - always clear the *request*: `review_requested_at = null`, `requester_id = null`.
  - preserve the *approval* stamp: if the working row's `reviewed_at != null`, copy `reviewed_at` + `reviewer_id` onto the canonical; if it was never reviewed (`reviewed_at == null`), set both to null (published without review → no false "reviewed by" attribution).
  - Because `NON_CONTENT` excludes the review fields (so `forceFill` won't carry the draft's request onto the canonical), `publish()` sets these explicitly from the working row's completion state.
- **`NON_CONTENT`** — rename `checker_id` → `reviewer_id`; add `review_requested_at`, `reviewed_at`.

(Domain events + real notifications remain **out of scope** here — see "Deferred". The service methods are structured so a `ReviewRequested` event can be added at the one obvious call site later without touching the UI.)

## Model changes — `app/Models/Trove.php`

- Rename `checker()` relation → `reviewer()` (`belongsTo(User::class, 'reviewer_id')`); keep `requester()`.
- Add the `reviewStatus(): Attribute` accessor (the single derivation above) and a `reviewInProgress` convenience (`review_requested_at !== null && reviewed_at === null`).
- Cast `review_requested_at`, `reviewed_at` as `datetime`.
- Add a query scope for the personal queue: `scopeAwaitingReviewBy(Builder $q, int $userId)` → `workingVersions()->whereNotNull('review_requested_at')->whereNull('reviewed_at')->where('reviewer_id', $userId)`.
- `hasPublishedVersion()` accessor: unchanged (drives the Publish button label).

## Filament — replace the Check step with explicit Review actions

### `TroveResource.php`

- **Rename the wizard step** `Check` → **`Review`** (icon `heroicon-m-clipboard-document-check` is fine); rewrite its `Shout` copy to describe the real flow ("we recommend a review — pick someone and it appears in the **In Review** queue; or publish now if you're confident"). No promise of notifications (none exist yet).
- **Delete the radio + three visibility-toggled fieldsets + the `should_publish`/`are_you_sure*` stale-`$record` guards** (fixes 1.4). The user's intent becomes the button they press, not a radio value cross-referenced against three fieldsets. The Review step body becomes just the guidance `Shout` (the actions live in the form footer, below).
- Add a `ReviewStatus` **badge column** to `getTableColumns()` (`TextColumn::badge()` reading `review_status`, coloured via the enum), plus a "✓ reviewed by {reviewer.name}" indicator when `reviewed_at` is set. This is the at-a-glance status the principle requires.

### Form footer actions (shared by Create/Edit via `getFormActions()` / the Review step)

Replace `SaveDraftFormAction` + the inline publish closure with explicit, self-describing actions:

- **Save draft** — save without publishing (`shouldPublish = false`).
- **Request review** — opens a modal with a single `Select` (the reviewer); on submit, saves then calls `TrovePublisher::requestReview($record, $reviewer)`. Stamps `requester_id = auth()->id()` in the handler (fixes 1.2 — no more `formatStateUsing`).
- **Publish** — label `Publish` / `Publish changes` via `hasPublishedVersion()`. Confirmation modal whose body is driven by the **live** `ReviewStatus`/`reviewed_at`, not `$record`: if not reviewed, show the "no one has reviewed this — publish anyway?" guidance with a confirm checkbox; if reviewed, a plain confirm. Always enabled (optionality principle — never block publish).

### `EditTrove.php`

- **Mark as reviewed** action (header or footer), visible only when the working row is `InReview`: opens a small confirm modal, calls `TrovePublisher::completeReview($record, auth()->user())` (records the *actual* approver per Decision 2), notifies "Review completed". Optionally offer **Approve & publish** as a one-click combination (completeReview then publish).
- Keep the existing **Discard draft changes** and **Unpublish** header actions (rename any "check" copy).
- `mutateFormDataBeforeSave()`: drop the `checker_id`→`requester_id` stamping hack (now handled inside `requestReview`). Rename remaining references.

### `CreateTrove.php`

- Same footer actions. A brand-new Trove is a never-published canonical; **Request review** and **Publish** both operate on it after the initial create/save. Drop the `checker_id`→`requester_id` stamping hack.

### `ListTroves.php` — tabs driven by review state

Rebuild `getTabs()` in Review language, keyed off the new columns/scopes:

- **All** → `workingVersions()`
- **Drafts** → `workingVersions()->whereNull('published_at')->whereNull('review_requested_at')` (unpublished, no review outstanding)
- **In review** → `workingVersions()->whereNotNull('review_requested_at')->whereNull('reviewed_at')` (was "Check Requested")
- **Needs my review** → `awaitingReviewBy(auth()->id())` — the current user's pending tasks, with a count badge on the tab (`->badge(...)`). This is the personal-queue requirement (addresses the "not personal" half of 1.7).
- **Published** → `withDrafts()->whereNull('published_id')->whereNotNull('published_at')`

## Language sweep (verify zero stragglers)

Grep after the rename for: `checker`, `check_requested`, `Check Requested`, `'Check'` (wizard step), and any user-facing "check"/"checking" copy in `TroveResource.php` and the page classes. Confirmed current surface: `Trove.php` (relations), `TroveResource.php` (step + fieldsets + copy), `CreateTrove`/`EditTrove`/`ListTroves`, the migration, and `TrovePublisher`. No public blade view uses review/check language (the `checkIcon` matches in `trove.blade.php`/`collection.blade.php` are unrelated copy-link JS — leave them).

## Verification (end-to-end; no automated suite yet)

- `php artisan migrate:fresh --seed` then the Example seeder; boot `/admin`.
- **Create → request review → complete → publish**: create a draft; Request review (pick reviewer A); confirm it shows **In review** and appears under **Needs my review** for A (and disappears for others). As A, **Mark as reviewed** (as a *different* user than assigned, to confirm `reviewer_id` is overwritten with the actual approver); confirm badge shows ✓ reviewed by A. Publish; confirm the live trove keeps the ✓ reviewed-by-A stamp and no longer shows a review request.
- **Publish without review**: create a draft, Publish directly (confirm the "no review — publish anyway?" guidance appears but does **not** block); confirm the published trove shows **Published** with *no* ✓ marker.
- **Edit live → review edits → publish changes**: edit a published trove; confirm public copy unchanged and status is **Published with pending changes**; confirm the draft starts with a **clean** review slate (no inherited reviewer/request — bug 1.3); request review on the draft; publish changes; confirm same URL, updated content, new review stamp.
- **Discard draft** and **Unpublish** still work and use Review language.
- Meilisearch: only published canonicals indexed; drafts/in-review rows never appear on the public site.

## Deferred (explicitly out of scope)

- Domain events (`ReviewRequested`, `TrovePublished`) and **real notifications** to the assigned reviewer / team (bug 1.1). The service is structured to host these at a single call site later.
- Any review *history* (multiple past reviews per Trove) — excluded by Decision 1 and by the drafts-removal no-history decision.
