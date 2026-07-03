# Plan: Remove `laravel-drafts` / `filament-drafts`, own the published + single-draft model

**Status:** Completed

Implemented 2026-07-01. Schema, model, domain service, Filament rework, call-site updates and package removal are all done and verified (migrate:fresh --seed, boot, end-to-end tinker of the draft/publish lifecycle). See the change log: [docs/change-logs/remove-laravel-drafts-single-shadow-draft.md](../change-logs/remove-laravel-drafts-single-shadow-draft.md). Manual admin UI walk-through + Meilisearch reindex remain as recommended follow-up (require a machine with those services running).

## Context

The Trove draft/review/publish workflow is built on two third-party packages — `oddvalue/laravel-drafts` (the draft/revision/publish engine) and `guava/filament-drafts` (its Filament glue). The review in `docs/from-original-app/trove-review-system.md` concluded they *over-deliver on versioning and under-deliver on review*: we pay for a 10-deep revision history, attribute-swap publishing, per-save row growth, and per-save S3 media duplication, while getting none of the review handshake we actually care about. They were originally carried as forked git submodules; that is now stale — the submodules are not checked out (`packages/` does not exist), `composer.lock` resolves both from **upstream** (`oddvalue/laravel-drafts v1.5.2`, `guava/filament-drafts 2.1.1`), and the `./packages` path-repositories in `composer.json` plus the `.gitmodules` entries are dead config.

This plan removes both packages and reimplements exactly the two requirements that justify them — **(R1)** the public sees only published Troves, and **(R2)** a live Trove can be edited without disturbing the public copy until explicitly republished — with an app-owned model that holds **one published record plus at most one shadow draft** per logical Trove. No version history, no rollback (per decision). Publishing keeps the published row's primary key stable (so Collection membership and any FK survive re-publishes). It also fixes the publish-path bugs the review found (stale checker carried across publish, mis-recorded requester, dead `check_requested`, unreachable Unpublish) and corrects the misleading "a notification will be sent" copy. The explicit review-state enum + real notifications (review Part 2) are **out of scope** here — a follow-up plan.

## Decisions (confirmed)

1. **Migration:** rewrite the create-troves migration in place (fresh-migrate; existing template deployments re-seed).
2. **History:** single draft only — no revision history, no rollback.
3. **Review scope:** re-plumb the existing Check-step review UX on the new model and fix the publish bugs; defer the review-state enum + notifications.

## Target model

Every logical Trove is **one canonical row** (stable PK — the thing Collections/pivots/FKs point at) plus, optionally, **one shadow draft row**. **Published state is a single column, `published_at`**: a row is published iff `published_at IS NOT NULL`. There is no `is_published` column — `is_published` is a read-only computed `Attribute` on the model (`get: fn () => $this->published_at !== null`), used only for PHP/Blade reads; all SQL filters key off `published_at`.

| Row | `published_id` | `published_at` | Meaning |
|---|---|---|---|
| Canonical, never published | `NULL` | `NULL` | brand-new draft (edited in place; no separate draft row) |
| Canonical, published | `NULL` | set | the public/live version |
| Shadow draft | `= canonical id` | `NULL` | pending edits to a live Trove |

- A `UNIQUE` index on `published_id` enforces **at most one draft** per canonical.
- **R1** is a global scope: default Trove queries add `whereNotNull('published_at')` (drafts and never-published canonicals both have `published_at = NULL`, so both are excluded automatically — including through `Collection::troves()` relationship queries, which apply Trove's global scope).
- **R2**: editing a live Trove targets its shadow draft row; the canonical stays live untouched until publish.
- No attribute-swap, no `uuid` family, no `is_current`, no pruning, no per-save media copy.

### Schema (rewrite `database/migrations/2023_11_24_132900_create_troves_table.php`)

Replace the `$table->drafts();` macro (which added `uuid`, `is_current`, `published_at`, `publisher_id/type`, and the composite index) with explicit columns:

```php
$table->timestamp('published_at')->nullable();          // NULL = not published; sole source of truth
$table->foreignId('published_id')->nullable()->constrained('troves')->cascadeOnDelete();
$table->unique('published_id');
$table->index('published_at');
// keep: requester_id, checker_id, previous_slugs, softDeletes, timestamps
// drop entirely: is_published (now a computed Attribute), uuid, is_current, publisher_id/type
```

Delete `config/drafts.php`.

## Domain layer — single source of truth for transitions

Create `app/Services/TrovePublisher.php` (framework-agnostic, unit-testable). It is the **only** place lifecycle state mutates. Mirrors the review's Part-2 recommendation.

```
draftFor(Trove $canonical): Trove          // find-or-create the shadow draft (copies attrs + relations + media)
publish(Trove $trove): Trove               // draft→canonical (copy, clear review, delete draft) OR publish never-published canonical in place; sets published_at=now(); returns the live canonical
discardDraft(Trove $draft): void           // forceDelete draft + its media/pivots
unpublish(Trove $canonical): void          // published_at=NULL (implements documented Unpublish, review 1.6)
```

- `draftFor()`: `$draft = $canonical->replicate(['published_at','checker_id','requester_id']); $draft->published_id = $canonical->id; $draft->save();` (draft's `published_at` stays NULL) then copy draftable relations (sync pivots) and media from canonical.
- `publish()` for a draft: copy scalar/translatable attributes onto the canonical (**PK unchanged**); if `draft->slug !== canonical->slug`, push the old slug into `previous_slugs` and adopt the draft slug; sync `tags`/`troveTypes`/`collections` from draft; replace canonical media from draft media; set `published_at = now()`, **clear `checker_id`/`requester_id`** (fixes review 1.3); save; `draft->forceDelete()`.
- `publish()` for a never-published canonical: set `published_at = now()` in place, clear review fields.
- `unpublish()`: set `published_at = NULL` (removes it from public; no `is_published` column to touch).
- Two private helpers — `copyRelations($from,$to)` (each `$draftableRelations` entry → `$to->rel()->sync($from->rel()->pluck('id'))`) and `copyMedia($from,$to)` (per registered collection, clear target then `$media->copy(...)`) — reuse the exact media-copy pattern currently in `Trove::booted()`'s `drafted` listener (`app/Models/Trove.php:62-74`).

Media is now copied **only** at fork and at publish — never on routine saves (fixes the review's per-save S3 duplication).

## Model changes — `app/Models/Trove.php`

- Remove `use Oddvalue\LaravelDrafts\Concerns\HasDrafts;` (import + trait).
- Remove the `'check_requested' => 'boolean'` cast (review 1.5 — dead column).
- Replace the `drafted`-event media-cloning listener in `booted()` with nothing (media copying now lives in `TrovePublisher`). Keep the `saving` slug hook, but repoint its uniqueness query from the package `withDrafts()` to the new local scope (below); the "generate only when empty" guard already keeps draft rows (which inherit a slug via `replicate()`) from regenerating.
- Add the `is_published` computed `Attribute` (`get: fn () => $this->published_at !== null`). It is read-only — nothing writes it; code that "publishes" sets `published_at`.
- Add relations: `draft(): HasOne` → `hasOne(Trove::class, 'published_id')`; `publishedVersion(): BelongsTo` → `belongsTo(Trove::class, 'published_id')`.
- Add a global scope (a `Scopes\PublishedScope` applied in `booted()`, or a small `HasPublishedState` trait) giving R1 by default via `whereNotNull('published_at')`, plus query-scope macros to replace the package API:
  - `scopeWithDrafts` — remove the global scope (admin/preview use this).
  - `scopeWorkingVersions` — `withDrafts()` then `where(fn ($q) => $q->whereNotNull('published_id')->orWhereDoesntHave('draft'))`: exactly one editable row per logical Trove (the draft if it exists, else the canonical).
- Replace `hasPublishedVersion()` (currently `revisions()->where('is_published',true)->exists()`) with `$this->published_id !== null || $this->is_published` for the publish-button label (a shadow draft's canonical is by definition published; a never-published canonical is not).
- Scout: add `shouldBeSearchable(): bool { return $this->published_at !== null && $this->published_id === null; }` so only published canonical rows are indexed (draft rows never pollute the index). `toSearchableArray()` keeps `'is_published' => (int) $this->is_published` via the accessor.
- `findBySlugOrRedirect()`: change the four `where('is_published', 1)` filters to `whereNotNull('published_at')` and add `->whereNull('published_id')`.

## Filament changes

**Delete** the whole `app/Filament/Draftable/` directory (the custom Edit `Draftable` trait + `SaveDraftFormAction`) and stop using every `Guava\FilamentDrafts\*` symbol.

- **`TroveResource.php`**: drop the `Guava\...\Concerns\Draftable` trait; override `getEloquentQuery()` to return `Trove::workingVersions()`. In the **Check** step (lines 228-336): keep the radio + three-fieldset UX but repoint its actions at `TrovePublisher`; **stamp `requester_id` in the save handler** (`auth()->id()` when a checker is assigned) instead of the fragile `Hidden::formatStateUsing` (fixes review 1.2); make the publish warnings/`should_publish` read the **live** `checker_id` form value via `Forms\Get` rather than the stale `$record` (fixes review 1.4); correct the two "A notification will be sent to the resources team" callouts (lines 236, 297) to describe the Check-Requested tab reality (review 1.1 — notifications deferred).
- **`ListTroves.php`**: drop the Guava List `Draftable` trait; rebuild `getTabs()` on the new scopes with plain labels (not `filament-drafts::` keys):
  - `all` → `workingVersions()` (base)
  - `published` → `withDrafts()->whereNull('published_id')->whereNotNull('published_at')`
  - `drafts` → `workingVersions()->whereNull('published_at')`
  - `review` (Check Requested) → `workingVersions()->whereNull('published_at')->whereNotNull('checker_id')`
- **`EditTrove.php`**: drop the custom trait. In `mount()`/`resolveRecord()`, if the opened record is a **published canonical**, `draftFor()` it and redirect to the draft's edit URL — so the entire edit session (fields, relations, media) targets the draft row and no mid-save forking is needed. `handleRecordUpdate()` becomes: update the record in place; if the "Save and Publish" path ran, call `TrovePublisher::publish()` and redirect to the resulting canonical. `getFormActions()`: **Save Draft**, **Publish**, **Discard draft** (for a shadow draft), and **Unpublish** (for a live canonical with no draft — review 1.6). Simplify the saved-notification titles (no package strings).
- **`CreateTrove.php`**: drop the Guava Create `Draftable`; a new Trove is a canonical row with `published_at = NULL` (Save Draft) or `published_at = now()` (Publish, via the service); clear/stamp review fields via the service.

## Frontend / routes (small)

- `routes/web.php` preview route (line 37): change `Trove::withDrafts()->where('slug',$slug)` → `Trove::withDrafts()->workingVersions()->where('slug',$slug)->firstOrFail()` so preview renders the shadow draft when one exists, else the live/working row.
- **Accessor reads — unchanged** (the `is_published` Attribute still resolves): `resources/views/trove.blade.php:17` (`@if (!$resource->is_published)` banner), `AllTrovesTable.php:101`, `CollectionResource/RelationManagers/TrovesRelationManager.php:70` (`$record->is_published ? …`). Verify only.
- **SQL filters — must change** from `where('is_published', 1)` to `whereNotNull('published_at')` (a computed attribute can't be queried): `app/Livewire/BrowseAll.php:56,72` and `resources/views/collection.blade.php:9` (`$collection->troves()->where('is_published',1)->count()`).
- `database/seeders/Example/ExampleDataSeeder.php:118-119`: remove `$trove->is_current = true;` and replace `$trove->is_published = true;` with `$trove->published_at = now();` (`published_id` stays null).

## Package removal

- `composer.json`: remove `"guava/filament-drafts": "dev-main"` (line 17) and the entire `repositories` block (lines 94-109). Run `composer remove guava/filament-drafts` (drops transitive `oddvalue/laravel-drafts`), then `composer update --lock`.
- `.gitmodules`: remove the `laravel-drafts` and `filament-drafts` entries. (The third entry, `filament-scout`, is also dead — the app uses `kainiklas/filament-scout` from Packagist — so the whole file can be deleted; confirm before doing so.)
- Delete `config/drafts.php`.
- Grep-verify zero remaining references to `laravel-drafts`, `filament-drafts`, `HasDrafts`, `onlyDrafts`/`withoutDrafts`, `is_current`, `uuid`, `revisions(`, `updateAsDraft`, `shouldSaveAsDraft`, `check_requested`, and any SQL `where('is_published'` (should now be `whereNotNull('published_at')`).

## Bugs fixed as a by-product

- **1.1** misleading notification copy → corrected. **1.2** requester stamped in handler. **1.3** checker/requester cleared on publish. **1.4** warnings read live form state. **1.5** `check_requested` removed. **1.6** Unpublish implemented. Deferred (separate plan): explicit `ReviewStatus` enum, domain events, real notifications, personal "assigned to me" queue.

## Verification (end-to-end)

(No automated tests in this plan — the app has no viable test suite yet; tests for this and other features are a separate effort.)

- `composer remove guava/filament-drafts && composer install` succeeds with no dangling refs.
- `php artisan migrate:fresh --seed` then `db:seed --class="Database\Seeders\Example\ExampleDataSeeder"`; `php artisan scout:sync-index-settings && php artisan scout:import "App\Models\Trove"`.
- `php artisan test` green.
- Manual admin walk-through (`/admin`): create→draft→request review→preview→publish; edit a live Trove→confirm public unchanged→publish→confirm updated at same URL; discard draft; unpublish. Confirm the four list tabs show the right rows and the public/preview routes behave.
- `meilisearch` running; confirm `/browse-all` shows only published Troves and drafts never appear.
