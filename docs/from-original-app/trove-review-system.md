**Date**: 2026-06-25
**Branch**: `temp-merge-1`
**Reviewer**: Claude (automated multi-angle review)
**Scope**: The full Trove draft / review / publish workflow — `app/Models/Trove.php`, `app/Filament/Resources/TroveResource.php` (+ Pages), `app/Filament/Draftable/`, the two forked submodules (`packages/laravel-drafts`, `packages/filament-drafts`), and the public/preview routes. Assessed against the four reference docs in `docs/`.

# Review: the Trove review & publishing system

This review answers three questions in order: (1) what is functionally broken, (2) how the codebase could be re-compartmentalised behind clean APIs, and (3) whether `laravel-drafts` / `filament-drafts` are pulling their weight. It closes on the guiding question — keeping the _draft → review → publish → re-draft_ intent while simplifying both the implementation and the front-end.

The reference docs (`trove-review-process.md`, `trove-review-technical.md`, `draft-package-forks-vs-upstream.md`, `trove-saving-notes.md`) are accurate; this review builds on them rather than restating them.

---

## Part 1 — Functional bugs

Ordered roughly by severity / user impact.

### 1.1 The UI promises notifications that are never sent (confirmed)

Three pieces of Check-step copy tell the user a notification will go out:

*   `TroveResource.php:267` — _"A notification will be sent to the resources team to let them know…"_ (intro callout)
*   `TroveResource.php:266` — _"Please ask for a review from one of the team using the form below."_
*   `TroveResource.php:328` — _"A notification will be sent to the resources team…"_ (publish callout)

A repo-wide search for `Notification::`, `->notify(`, `Mail::` and mailables finds **nothing** tied to Trove publish/review events. The only `Notifiable` is the stock auth trait on `User`; the only Filament `Notification`s are the in-page save toasts and the collection-builder toasts in `AllTrovesTable.php`. No listener fires on `published`, `drafted`, or when `checker_id`/`requester_id` are set.

**Impact**: the entire "ask someone to check" loop depends on out-of-band communication (Slack/email/word-of-mouth). The assigned checker is never told. This is the headline bug and the one the brief specifically calls out.

**Fix shape**: this should _not_ be patched by sprinkling `Notification::send()` into the form action. See Part 2 — emit domain events (`ReviewRequested`, `TrovePublished`) and attach listeners. Until then, the copy is actively misleading and should be corrected to match reality (as the process guide already documents the truth).

### 1.2 `requester_id` is probably not recorded on a brand-new review request (needs runtime confirmation)

`TroveResource.php:308-309`:

```php
Forms\Components\Hidden::make('requester_id')
    ->formatStateUsing(fn(?Trove $record, Forms\Get $get) =>
        $get('checker_id') ? auth()->id() : $record?->requester_id),
```

`formatStateUsing` runs once, at form **hydration**, not on every live update. For a brand-new Trove, at mount `$record` is `null` and `checker_id` is empty, so the field resolves to `null`. The user then picks a checker via the `->live()` select — but that does **not** re-run `formatStateUsing` on the hidden field. So on the first "Request a Review", `requester_id` is saved as `null`.

This contradicts the process guide ("two things are recorded: who you asked, and that you were the one who asked") and trace 9.1 step 4 in the technical doc, which both assume the requester is captured.

**Confidence**: high on the mechanism, but worth a 2-minute runtime check (create a trove, request a review, inspect `requester_id`). Either way the pattern is fragile — deriving a persisted field from live state via `formatStateUsing` is the wrong tool. The requester should be stamped in the action handler at save time (`auth()->id()` when a checker is being assigned), not computed in a hidden form field.

### 1.3 Re-editing a published Trove silently re-enters the review queue (confirmed by design trace)

`checker_id` / `requester_id` are never cleared after a publish, and they are part of the row attributes the engine carries forward:

1.  Publish swaps attributes between the draft row and the canonical published row (`setLive()`), so the published row keeps `checker_id`.
2.  Next time someone edits that published Trove, `updateAsDraft()` replicates the published row into a new current draft — which inherits the old `checker_id`.
3.  The **Check Requested** tab (`ListTroves.php:44-47`) is `onlyDrafts()->where('is_current', true)->where('checker_id','!=',null)`.

Net result: **every edit of a previously-reviewed Trove reappears in the review queue, attributed to the original (possibly long-irrelevant) checker**, with no new review actually requested. The queue accumulates false positives and the warnings in 1.4 misfire.

**Fix shape**: clear `checker_id` / `requester_id` on publish (or model review state explicitly so "a review is outstanding" is a first-class fact, not an inferred one — Part 2).

### 1.4 Publish-confirmation warnings read stale persisted state

All three guards in the Publish fieldset key off `$record` (the persisted record), not live form state:

*   `are_you_sure` visible when `!$record?->checker_id` (`:332`)
*   `are_you_sure_again` visible when `$record?->requester_id === auth()->id()` (`:337`)
*   `should_publish` checkbox required when `!$record?->checker_id || $record?->requester_id === auth()->id()` (`:343`)
*   publish button disabled by the same `$record`\-based condition (`:351`)

Consequences:

*   On **create**, `$record` is always `null`, so `!$record?->checker_id` is always truthy → the "no-one has checked this" warning and the mandatory tick-box always appear, even if the user just assigned a checker in the Review fieldset moments earlier (that assignment isn't persisted yet).
*   On **edit**, if the user changes the checker in this same session and switches to Publish, the guards reflect the _saved_ value, not what's on screen.

Combined with 1.3, the `requester_id === auth()->id()` warning will fire on routine re-edits of old content because of the stale carried-forward requester.

### 1.5 `check_requested` cast points at a non-existent column (dead code)

`Trove.php:43` casts `'check_requested' => 'boolean'`, but no migration defines the column and nothing reads or writes it (confirmed by grep — the only hit is the cast itself). It is a fossil of an earlier design where review state was a boolean flag, since superseded by `checker_id`/`requester_id`. Remove it; it misleads anyone reading the model into thinking there's a status flag.

### 1.6 The documented "Unpublish" button is unreachable

The process guide (§"Editing a Trove that is already live") states: _"There is also an Unpublish option available when editing."_ The app trait `App\Filament\Draftable\Pages\Edit\Draftable::getFormActions()` does add `UnpublishAction` (`Draftable.php:79`) — but `EditTrove::getFormActions()` (`EditTrove.php:41-47`) **overrides** it and returns only `SaveDraftAction` + Cancel. `EditTrove`'s header actions are `DeleteAction` only. So `UnpublishAction` is never rendered anywhere for Troves.

Either the doc is wrong, or unpublish is a lost requirement. Decide which and align them. (The dead `UnpublishAction` import in the trait is a smell that the trait and the page disagree about who owns the footer — see Part 2.)

### 1.7 The "Check Requested" tab is not personal, and there's no "checked / approved" state

Documented as intentional, but worth flagging together as a workflow gap:

*   The tab shows the _whole team's_ queue (no `checker_id = auth()->id()` filter). With no notifications (1.1), a checker has no way to find "what's assigned to me" except scanning a shared list.
*   There is no record that a check was _completed_. Approval is implicit in the act of publishing; nothing distinguishes "reviewed and approved" from "published without review". After the fact you cannot tell whether the safety net was actually used.

This is the strongest argument for an explicit review state (Part 2 / Part 3).

### 1.8 Upgrade liability: forked `laravel-drafts` is 32 commits behind upstream

Per `draft-package-forks-vs-upstream.md`: the fork is 1 ahead / **32 behind**, missing Laravel 12/13 and PHP 8.4/8.5 support. Not a runtime bug today, but it pins the app's ability to upgrade the framework to a single-purpose fork that exists only to add `clone_relations`. See Part 3.

### Lower-severity / hygiene

*   **No automated tests** cover draft/review/publish at all (`tests/` has no matching file). For a workflow this stateful — attribute-swapping, row-cloning, media-copying, global scopes — that is the highest-leverage gap to close _before_ any refactor.
*   `TroveResource.php:115` repeats `->relationship('troveTypes','label')` twice on the same `Select` (harmless duplicate call).
*   `getRecordTitleAttribute()` (`:56-61`) computes `$locale` and never uses it.
*   `findBySlugOrRedirect()` is named "OrRedirect" but returns `?self` and never redirects; the redirect happens at the call site. Misleading name.

---

## Part 2 — Refactoring: compartmentalise the workflow behind a clean API

The brief asks specifically about _structural_ refactoring — pulling complicated pieces into modules with clean APIs — not line-level tidying. The central structural problem is:

> **There is no "review" concept in the domain.** The state machine lives nowhere and everywhere.

The workflow's logic is currently smeared across at least seven locations:

| Concern | Lives in |
| --- | --- |
| State transitions (draft / request review / publish) | `TroveResource` Check-step form closures **and** the Edit `Draftable` trait's 4-branch `handleRecordUpdate()` |
| Who is requester | a hidden field's `formatStateUsing` (1.2) |
| "Save as draft" trigger | **two** near-identical actions: `SaveDraftFormAction` (form-level) + Guava's `SaveDraftAction` (page-level) |
| "Publish" trigger | an inline `->action()` closure inside the form schema (`:352-362`) |
| Which footer buttons show | `getFormActions()` overridden in _both_ page classes, partly duplicating the trait |
| Media duplication on draft | a `drafted` model listener (`Trove::booted()`) |
| Queue definition | `ListTroves::getTabs()` flag combinations |

The state itself is **derived** from combinations of `is_published`, `is_current`, `checker_id`, `requester_id` — there is no single source of truth, which is why bugs 1.3 / 1.4 / 1.7 exist (state is inferred from flags that aren't kept consistent).

### 2.1 Introduce an explicit review/publishing state

Add a first-class notion of where a Trove is in the lifecycle. Two options, in increasing order of ambition:

*   **Minimum**: a derived `ReviewStatus` enum accessor on the model (`Draft`, `InReview`, `Published`, `PublishedWithPendingChanges`) computed _once_, in one place, from the flags. Every consumer (tabs, badges, warnings) reads the enum instead of re-deriving flag combinations. This alone kills the duplicated, drift-prone conditions in the form and the tabs.
*   **Better**: persist review intent explicitly — e.g. a nullable `review_requested_at` and `reviewed_at`/`approved_by`, so "a review is outstanding" and "this was approved" are facts, not inferences. This directly fixes 1.3 (no inheritance of stale checker), 1.7 (approval is recorded), and makes a "checked ✓" badge trivial.

### 2.2 Move transitions into domain actions / a service

Create a small, framework-agnostic surface that owns the transitions and is the _only_ thing that mutates lifecycle state:

```
App\Domain\Troves\TrovePublisher (or single-purpose action classes)
  ->saveDraft(Trove $t, array $data): Trove
  ->requestReview(Trove $t, User $checker, User $requester): Trove
  ->publish(Trove $t, User $publisher): Trove
  ->unpublish(Trove $t): Trove
```

Each method performs the transition, clears/sets the review fields correctly (fixing 1.3), and **fires a domain event** (`ReviewRequested`, `TrovePublished`, `TroveUnpublished`). The Filament actions become three-line wrappers that call these methods. This:

*   collapses the 4-branch `handleRecordUpdate()` into named, testable methods;
*   gives Part 1's notification fix a clean home — `ReviewRequested` → listener → notify the checker; `TrovePublished` → listener → notify the team (1.1 solved properly and once);
*   lets you write unit tests against the service without booting Filament (closing the test gap).

### 2.3 Collapse the duplicated action surface

*   Replace the **radio + three visibility-toggled fieldsets + inline publish closure** in the Check step with **explicit Filament actions** ("Save Draft", "Request Review", "Publish"), each opening a small modal where relevant (pick a checker; confirm publish). This removes the `->live()` form-state juggling, the stale-`$record` reads (1.4), and the `formatStateUsing` requester hack (1.2). The user's intent becomes the button they press, not a radio value that has to be cross-referenced against three fieldsets.
*   Reconcile the **two** SaveDraft actions and the **two** `getFormActions()` overrides. Right now `EditTrove` and the app trait disagree about the footer (1.6). Pick one owner.

### 2.4 Make media a proper part of the draft mechanism, not a side-effect

Media duplication is bolted on via the `drafted` listener and copies _every_ registered collection onto _every_ new draft. Combined with "every save inserts a row" (`trove-saving-notes.md`), this means routine editing **duplicates S3 files repeatedly**. If the draft model is simplified (Part 3), media should be copied only when a draft is genuinely forked from a published version, not on every keystroke-save.

---

## Part 3 — Do `laravel-drafts` / `filament-drafts` help or hinder?

### What they actually buy us

The app has two genuine requirements:

*   **(R1)** the public sees only published Troves;
*   **(R2)** you can edit a _live_ Trove without changing what the public sees until you explicitly publish.

`laravel-drafts` delivers both: the `PublishingScope` global scope gives R1 essentially for free, and the `is_published`/`is_current` split plus attribute-swap-on-publish gives R2 with a **stable public URL / primary key** across re-publishes. That URL stability is real value and non-trivial to reimplement.

`filament-drafts` is much thinner: a revisions paginator render-hook, the `withDrafts()` query override, and the Save-Draft/Unpublish actions. The app already overrides or bypasses most of it.

### What they cost us

1.  **Two forked submodules**, one 32 commits behind upstream (1.8) — a framework-upgrade blocker. The `filament-drafts` fork exists _only_ to re-point its dependency at the `laravel-drafts` fork.
2.  **The fork was necessary at all** because the package _doesn't clone draftable relations onto drafts_ — i.e. its draft model doesn't fit the app's "preview a draft with its tags/types/collections" need out of the box. That's a fit problem, not a one-off tweak.
3.  **The publish mechanism (attribute-swapping between two rows)** is the cleverest and least legible part of the system. It is the root cause of 1.3 (stale checker carried across the swap) and the "every save inserts a row" surprise.
4.  **Full N-version history (keep=10) is barely used.** The app exposes the revisions paginator but the product requirement is "edit live safely + roll back if needed", not a 10-deep audit trail. We pay the complexity of pruning, swap, and per-draft media duplication for a feature surface the workflow doesn't lean on.
5.  **The custom Edit** `**Draftable**` **trait exists to work around upstream issue #15** — another sign the integration is fighting the package.

### Assessment

The packages **over-deliver on versioning and under-deliver on review**. They give a rich revision engine the app mostly ignores, while the thing the brief cares about — review state and notifications — is entirely outside the package and hand-rolled with loose columns. The maintenance surface (two forks, a workaround trait, a relations-cloning patch, a media listener) is large relative to the two requirements that genuinely justify the package.

---

## Part 4 — The guiding question: keep the intent, simplify implementation _and_ front-end

> _Troves are drafted, reviewed, published, then maybe re-drafted, re-reviewed, re-published — how do we keep that while simplifying?_

The intent decomposes into exactly: **R1** (public sees only published), **R2** (edit-live-without-disturbing-public), and a **review handshake** (assign a checker → checker looks → publish). Everything else is incidental complexity.

### Recommended direction

**Separate the two axes that are currently tangled:** _**versioning**_ **and** _**review**_**.**

**Review** should be modelled explicitly in the app (Part 2): a status enum + `requested`/`approved` timestamps + domain events + notifications. This is small, testable, and owned by us. It fixes 1.1, 1.2, 1.3, 1.7 at the source and makes the front-end honest.

**Versioning** should be reduced to what R1+R2 require, and the decision is _how much of the package to keep_:

**Option A — keep** `**laravel-drafts**`**, stop fighting it.** Configure `keep` low (1–2) so you hold _the published row + at most one working draft_ rather than a 10-deep history. Eliminate the need for the `clone_relations` fork by either (a) not using `draftableRelations` and instead snapshotting tags/types/collections explicitly at fork time, or (b) upstreaming the patch. Goal: run the _released_ package, delete the forks, unblock framework upgrades. Lowest-risk path; keeps the valuable stable-URL swap.

**Option B — replace the package with an explicit "published + optional shadow draft" model.** One published row per logical Trove plus, when someone edits a live Trove, a single linked `draft` (a self-referential `parent_id`, or a JSON `pending_changes` column for simple fields + a fork only when media/relations change). R1 becomes a `where('is_published', true)` default scope; R2 becomes "render the shadow draft on the preview route". No attribute-swapping, no pruning, no per-save row growth, no per-draft media duplication, no forks. Highest-clarity end state; more upfront work and you reimplement stable-URL handling yourself.

Given the app uses almost none of the deep-history surface and the forks are a standing liability, **Option A is the pragmatic near-term move** (delete forks, shrink history, model review explicitly), with **Option B as the target** if the team wants to own the model outright.

### Front-end simplification (independent of A vs B)

*   Replace the radio + three fieldsets + stale warnings with three explicit buttons/modals (Part 2.3). The user picks an action, not a mode.
*   Drive the list off the **status enum**: a single status badge column (`Draft` / `In review` / `Published` / `Unpublished changes` / `Checked ✓`) instead of four overlapping tabs (`All` = current; `Drafts` = current+unpublished; `Check Requested` = a subset of Drafts). One or two filters on the enum replace the tab soup.
*   Make **"Check Requested" personal** (default to "assigned to me") now that notifications exist, with a toggle to see the whole queue.
*   Keep **Preview-on-Frontend** and the **Comments thread** — these are the two parts of the current UX that are clear and correct; they need no change.
*   Correct or implement the **Unpublish** affordance (1.6) so docs and UI agree.

### Suggested sequencing

1.  **Write characterization tests** for the current draft/review/publish behaviour (close the zero-coverage gap) — a safety net before touching anything.
2.  **Truth-in-UI**: fix the notification copy (1.1) and remove dead `check_requested` (1.5) / reconcile Unpublish (1.6) — cheap, immediate.
3.  **Model review explicitly** + domain events + notifications (Part 2.1/2.2). Fixes 1.1/1.2/1.3/1.7.
4.  **Collapse the action surface** and the front-end (Part 2.3, Part 4 front-end).
5.  **Decide A vs B** for versioning and, at minimum, delete the forks (Option A).