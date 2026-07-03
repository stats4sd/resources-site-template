# Plan: Split the Trove lifecycle into two orthogonal axes (Publication × Review)

**Status:** Completed — see [docs/change-logs/trove-split-publication-and-review-axes.md](../change-logs/trove-split-publication-and-review-axes.md).

## Context

This refines the design delivered by [docs/plans/trove-review-state-and-workflow.md](trove-review-state-and-workflow.md) (Completed). That work moved every lifecycle transition into [app/Services/TrovePublisher.php](../../app/Services/TrovePublisher.php), removed the `laravel-drafts` / `filament-drafts` dependencies, and introduced a single derived `ReviewStatus` enum as "the single source of truth for the working row's lifecycle state". It fixed the bugs the [original review](../from-original-app/trove-review-system.md) called out.

But it only *half*-delivered that review's headline recommendation — **"Separate the two axes that are currently tangled: versioning and review."** The two axes are separated in the *data* (the review columns are distinct from the publication columns; `TrovePublisher` treats review and publication as independent transitions) but were **re-fused in the derivation**: `ReviewStatus` flattens both axes into one enum. This plan finishes the split at the derivation/presentation layer.

### Evidence the single enum tangles the two axes

- Three of `ReviewStatus`'s four members (`Draft`, `Published`, `PublishedWithPendingChanges`) are **pure publication facts** — derived only from `published_at` / `published_id`, with zero review content. Only `InReview` is about review. And the actual review-*outcome* fact (`reviewed_at`) is deliberately kept **out** of the enum as an "orthogonal ✓ marker" (see the enum docblock and [Trove.php:116](../../app/Models/Trove.php#L116)). So a type named `ReviewStatus` is ~75% publication axis, one review member, plus an admitted orthogonal marker bolted on the side. The axes were already half-split, asymmetrically: `reviewed_at` got to be orthogonal; `InReview` did not.
- The asymmetry is forced by **precedence**: in `reviewStatus()` an outstanding review wins over everything ([Trove.php:120-134](../../app/Models/Trove.php#L120-L134)). `InReview` is a *hat* worn over the publication state, so while a trove is in review its publication fact (fresh draft vs. pending edits to a live row) is masked. When the review completes, the hat comes off and the row "falls back" to the publication state it always had — which is why "Mark as reviewed sends it back to Draft" is hard to explain: it never left Draft; `InReview` was covering it.
- This is why "Mark as reviewed" not publishing reads as surprising. In a single-status model, `InReview` looks like a peer of `Published`, so completing it feels like it should advance *toward* `Published`. In truth review and publication are orthogonal: completing a review advances the review axis and leaves the publication axis untouched by design.

### Incidental complexity and live bugs caused by the fusion

- **Parity guards:** `scopeWithReviewStatus` hangs a `->whereNot($outstandingReview)` guard on all three publication members ([Trove.php:159-169](../../app/Models/Trove.php#L159-L169)) purely to reproduce the precedence in SQL. This is the entire reason the accessor/scope parity is fragile enough to need a dedicated parity test ([docs/plans/trove-review-status-parity-test.md](trove-review-status-parity-test.md)). Splitting the axes deletes every one of these guards — each scope then filters one or two columns with no cross-axis interaction.
- **Bug — Discard / Unpublish vanish under review:** in [EditTrove.php:52-77](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L52-L77), *Discard draft changes* and *Unpublish* are gated on `review_status === PublishedWithPendingChanges`. If a live trove is being edited **and** has a review outstanding on those edits, `review_status` is `InReview` (precedence wins), so both controls silently disappear from a pending-changes trove while it is under review. Splitting means these gate on the *publication* axis (`PendingChanges`) and are unaffected by review state.
- **Special-case in the tab list:** the "Published" tab includes `PublishedWithPendingChanges` and carries the comment "A draft currently InReview is intentionally excluded (for now)" ([ListTroves.php:58-64](../../app/Filament/Resources/TroveResource/Pages/ListTroves.php#L58-L64)) — a live-with-pending-edits trove drops out of "Published" the moment it goes under review, again because the axes are fused. With a publication axis the tab filters on publication alone and the special case disappears.

## Principles (carried from the prior plan, still binding)

1. **One vocabulary — "Review".**
2. **Conceptually simple, explainable in one breath.** The refinement here *serves* this principle: every user question maps to exactly one axis.
3. **Review is optional.** Publish is always reachable without a review.

## Decision

**Split the fused `ReviewStatus` into two orthogonal, single-axis computed states. Do *not* introduce a combined flat `TroveState` enum.**

The lifecycle state space is genuinely 2-dimensional. The reachable products are ~7 (Draft·None, Draft·InReview, Draft·Reviewed, Published·None, Published·Reviewed, PendingChanges·None, PendingChanges·InReview, PendingChanges·Reviewed). Flattening a 2-D space into one enum forces you to either enumerate all seven with awkward names, or pick a precedence and mask an axis — which is exactly the status quo. A flat combined enum therefore *reintroduces* the tangle; it is the "too complicated" option and is explicitly rejected.

If a single value is wanted for a badge, it is a **presentation pairing** (two chips, or a tiny readonly value object holding both facets), never a flattened enum. Crucially, the precedence decision ("in the list, emphasise *In review* over *Pending changes*") becomes a **view** choice, not a **model** fact: the model always tells the truth on both axes; the view decides which to emphasise. This is the clean separation the original review asked for.

## Design

### Two enums, two single-axis accessors

**`app/Enums/PublicationState.php`** — backed string enum, `{Draft, Published, PendingChanges}`, implementing `HasColor`/`HasIcon`/`HasLabel`.

| Case | Meaning | Derivation (working row) |
|---|---|---|
| `Draft` | never published; not on the public site | `published_at === null && published_id === null` |
| `Published` | the live canonical, nothing pending | `published_at !== null && published_id === null` |
| `PendingChanges` | a shadow draft of a live canonical (unpublished edits exist) | `published_id !== null` |

Labels roughly as today: `Draft` / `Published` / `Published — pending changes`.

**`app/Enums/ReviewState.php`** — backed string enum, `{None, InReview, Reviewed}`, implementing `HasColor`/`HasIcon`/`HasLabel`.

| Case | Meaning | Derivation |
|---|---|---|
| `InReview` | a review is outstanding | `review_requested_at !== null && reviewed_at === null` |
| `Reviewed` | the review was completed/approved | `reviewed_at !== null` |
| `None` | no active or completed review on this working row | otherwise |

`Reviewed` becomes a first-class member of its own axis instead of the orphaned "orthogonal marker" it is today — which is what it always was. `None` and `Reviewed` are decided by `reviewed_at`; `InReview` by an outstanding request. (Precedence *within* the review axis only: `reviewed_at` set means `Reviewed` even if a stale `review_requested_at` lingers — though `completeReview()` and `requestReview()` keep these consistent.)

### Model changes — `app/Models/Trove.php`

- Replace the single `reviewStatus(): Attribute` with `publicationState(): Attribute` (returns `PublicationState`) and `reviewState(): Attribute` (returns `ReviewState`). Each derives from its own columns only — no precedence between the two, no cross-axis guards.
- Replace `scopeWithReviewStatus(ReviewStatus ...)` with two independent scopes: `scopeWithPublicationState(PublicationState ...)` and `scopeWithReviewState(ReviewState ...)`. Each maps its cases to plain `where` predicates on one or two columns. **No `whereNot($outstandingReview)` guards** — the reason those existed (reproducing cross-axis precedence in SQL) is gone.
- `reviewInProgress` accessor: keep, redefine as `review_state === ReviewState::InReview`.
- `scopeAwaitingReviewBy` ([Trove.php:218-224](../../app/Models/Trove.php#L218-L224)): unchanged (already single-axis on the review columns).
- `isPublished` / `hasPublishedVersion`: unchanged (already clean publication-axis accessors).
- Delete `app/Enums/ReviewStatus.php` once no consumer references it.

### Parity test

Update [docs/plans/trove-review-status-parity-test.md](trove-review-status-parity-test.md) and the corresponding test to assert accessor/scope parity for **each axis independently** (`publicationState()` vs `withPublicationState`, `reviewState()` vs `withReviewState`). Each becomes simpler to lock because there is no precedence interaction to reproduce.

### Filament consumers (all six current `review_status ===` / `withReviewStatus` sites)

- **Status column** ([TroveResource.php:362-367](../../app/Filament/Resources/TroveResource.php#L362-L367)): render the **publication** state as the primary badge; render the review facet alongside it — an `In review` chip when `review_state === InReview`, and keep the existing "✓ reviewed by X" description line driven by `reviewed_at`. This is the presentation pairing; the two facets are shown, not flattened. (If Filament ergonomics favour it, a second `badge()` column for review, or a formatted state combining both chips — either way both axes are visible.)
- **`EditTrove` header actions** ([EditTrove.php:39-77](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L39-L77)):
  - `mark_reviewed` → `visible(review_state === ReviewState::InReview)`.
  - `discard_draft` → `visible(publication_state === PublicationState::PendingChanges)` (fixes the vanishing-under-review bug).
  - `unpublish` → `visible(publication_state === PublicationState::PendingChanges)` (same fix).
- **`shouldForkOnSave`** ([EditTrove.php:103-107](../../app/Filament/Resources/TroveResource/Pages/EditTrove.php#L103-L107)): `review_status === ReviewStatus::Published` → `publication_state === PublicationState::Published`. This is a pure publication check; it should not be perturbed by review state (today it happens to work only because a live row with no draft can't be `InReview`, but the intent is publication-axis).
- **`ListTroves` tabs** ([ListTroves.php:42-64](../../app/Filament/Resources/TroveResource/Pages/ListTroves.php#L42-L64)):
  - `Drafts` → `withPublicationState(Draft, PendingChanges)->withReviewState(None, Reviewed)` (i.e. not currently in review) — or express as "not `InReview`". Composed from two explicit axes rather than the fused `withReviewStatus(Draft, PublishedWithPendingChanges)` which silently also meant "excluding InReview".
  - `In review` → `withReviewState(InReview)`.
  - `Needs my review` → `awaitingReviewBy(auth()->id())` (unchanged).
  - `Published` → `withPublicationState(Published, PendingChanges)` — pure publication axis; the "InReview intentionally excluded (for now)" special case is deleted (a pending-changes trove under review now correctly still counts as published).
- **`HasTroveFormActions`** ([HasTroveFormActions.php:114-116](../../app/Filament/Resources/TroveResource/Concerns/HasTroveFormActions.php#L114-L116)): `reviewedAlready()` already reads `reviewed_at` directly — unchanged. `publishLabel()` reads `has_published_version` — unchanged.

### Workflow narrative this enables (the PR notes that were hard to write)

Every user question maps to exactly one facet, so the story is one line per step:

- Create → `Draft · —`
- Request review → `Draft · In review`
- Mark as reviewed → `Draft · ✓ Reviewed` — review facet advanced; publication facet deliberately untouched. "Reviewing records the second pair of eyes; publishing pushes it live. They're separate on purpose — get a review before you're ready to publish, or publish without one."
- Publish → `Published · ✓ Reviewed`
- Edit the live trove → `Published — pending changes · —` (fresh review slate, per `draftFor()`)
- Request review on the edits → `Published — pending changes · In review`
- Publish changes → `Published · ✓ Reviewed`

## Scope note

This is a derivation-and-presentation refactor, **not** a data-model change. The columns (`published_at`, `published_id`, `review_requested_at`, `reviewed_at`, `reviewer_id`) already cleanly encode both axes, and `TrovePublisher` already performs review and publication as independent transitions — **no migration and no change to `TrovePublisher` are required.** The work is: two accessors + two scopes replace one accessor + one fused scope; six call sites split into publication-axis / review-axis checks (resolving the two bugs above as a side-effect); the status column renders two facets; the parity test splits per axis; the old `ReviewStatus` enum is deleted.

## Verification (end-to-end; no automated suite yet)

- `php artisan migrate:fresh --seed`, then the Example seeder; boot `/admin`.
- **Two-facet rendering:** confirm the list shows publication state and review facet independently across: a fresh draft, a draft in review, a reviewed-but-unpublished draft, a published trove (with and without a ✓ stamp), and a live trove with pending edits (with and without those edits in review).
- **Bug fix — controls under review:** edit a live trove, request review on the edits (now `PendingChanges · In review`); confirm *Discard draft changes* and *Unpublish* are **still visible** (they vanish on `dev`).
- **Bug fix — Published tab:** the same `PendingChanges · In review` trove still appears under the **Published** tab.
- **Mark as reviewed leaves publication untouched:** a `Draft · In review` trove → Mark as reviewed → becomes `Draft · ✓ Reviewed`, NOT published; a `PendingChanges · In review` trove → Mark as reviewed → `PendingChanges · ✓ Reviewed`.
- **Optionality:** publish without a review still reachable; published trove shows `Published` with no ✓ marker.
- **Parity:** the split parity test passes for both axes.

## Out of scope / noted only

- **Stale `reviewed_at`.** If a draft is edited *after* its review completes, the `✓ Reviewed` stamp becomes stale (it attests to content that has since changed). This is a pre-existing issue, orthogonal to the axis split, and is **not addressed by this plan** — noted here so it isn't lost. Address separately if it matters (e.g. clear `reviewed_at` on the next content-mutating save of a reviewed working row).
- Domain events / real notifications (bug 1.1) — still deferred, per the prior plan.
- Review history — still excluded by decision (single working row, no history table).
