# Change log: Remove laravel-drafts / filament-drafts, own the published + single-shadow-draft model

**Date**: 2026-07-01
**Branch**: `dev`
**Plan**: [docs/plans/remove-laravel-drafts-single-shadow-draft.md](../plans/remove-laravel-drafts-single-shadow-draft.md)

Replaced the `oddvalue/laravel-drafts` + `guava/filament-drafts` draft/revision/publish engine with an app-owned model: each logical Trove is one canonical row (stable primary key) plus, when a live Trove is being edited, at most one shadow draft row. No revision history, no rollback, no attribute-swap publishing, no per-save media duplication.

## Data model

- **`published_at` is the sole source of truth for published state** — a row is published iff `published_at` is not null. There is no `is_published` column any more; `is_published` is a read-only computed `Attribute` on `Trove` used for PHP/Blade reads only. All SQL filters use `whereNotNull('published_at')`.
- New nullable self-referencing FK **`published_id`** links a shadow draft to its canonical row (null on canonical rows), with a `UNIQUE` constraint enforcing at most one draft per canonical, and an index on `published_at`.
- Rewrote `database/migrations/2023_11_24_132900_create_troves_table.php` in place: dropped the `$table->drafts()` macro columns (`uuid`, `is_current`, `published_at` [macro version], `publisher_id/type`, composite index) and added the explicit `published_at` / `published_id` columns. Kept `checker_id`, `requester_id`, `previous_slugs`.

## New code

- **`app/Models/Scopes/PublishedScope.php`** — global scope restricting default queries to published canonical rows (`whereNotNull('published_at')`). Applied to `Trove` in `booted()`. Gives public visibility (R1) for free, including through `Collection::troves()` relationship queries.
- **`app/Services/TrovePublisher.php`** — the single place lifecycle state mutates:
  - `draftFor()` — find-or-create the shadow draft (copies content via `replicate`, plus draftable relations and media from the canonical). Idempotent.
  - `publish()` — folds a shadow draft onto its canonical (copies content/relations/media, records a slug change into `previous_slugs`, sets `published_at`, clears `checker_id`/`requester_id`, deletes the draft), or publishes a never-published canonical in place. Keeps the canonical PK stable.
  - `discardDraft()` — drop a draft's pending edits.
  - `unpublish()` — set `published_at = null` (and discard any draft); implements the documented Unpublish.
- **`app/Filament/Forms/Components/Actions/SaveDraftFormAction.php`** — package-free replacement for the Check-step save-as-draft button.

## Trove model (`app/Models/Trove.php`)

- Removed the `HasDrafts` trait and the `drafted`-event media-cloning listener (media is now copied only at fork/publish, never on routine saves).
- Removed the dead `check_requested` cast.
- Added: `is_published` computed `Attribute`; `draft()` HasOne and `publishedVersion()` BelongsTo (both opting out of `PublishedScope`); `scopeWithDrafts()` and `scopeWorkingVersions()`; `getDraftableRelations()`; `shouldBeSearchable()` (only published canonical rows are indexed).
- `hasPublishedVersion` now derived from `published_id` / `is_published`. `findBySlugOrRedirect()` filters `whereNull('published_id')` (published-only is guaranteed by the global scope).

## Filament

- **`TroveResource`** — dropped the Guava `Draftable` concern; `getEloquentQuery()` opts out of `PublishedScope` so the admin sees all versions. Check step: removed the fragile `Hidden::requester_id` (requester is now stamped in the page save handler — review bug 1.2); publish warnings/`should_publish` read live form state via `Forms\Get` instead of the stale `$record` (bug 1.4); corrected the misleading "a notification will be sent" copy (bug 1.1 — notifications deferred).
- **`EditTrove`** — opening a published canonical forks/reuses its draft and redirects to editing the draft, so the whole edit session targets the draft. `afterSave()` folds the fully-saved draft onto the canonical when publishing. Header actions: **Discard draft changes**, **Unpublish** (bug 1.6), Delete.
- **`CreateTrove`** — a new Trove is a canonical row, published (via the service) or left as a draft.
- **`ListTroves`** — tabs (All / Published / Drafts / Check Requested) rebuilt on the new scopes with plain labels.
- Deleted `app/Filament/Draftable/` (the custom Edit `Draftable` trait and old `SaveDraftFormAction`).

## Other call sites

- `routes/web.php` preview route now resolves `withDrafts()->workingVersions()` (shows the shadow draft when one exists).
- `app/Livewire/BrowseAll.php` and `resources/views/collection.blade.php`: `where('is_published', 1)` → `whereNotNull('published_at')`.
- `database/seeders/Example/ExampleDataSeeder.php`: sets `published_at = now()` instead of `is_published`/`is_current`.
- Accessor reads (`$record->is_published`) in `trove.blade.php`, `AllTrovesTable`, `TrovesRelationManager` are unchanged (the computed attribute still resolves).

## Package removal

- Removed `guava/filament-drafts` from `composer.json` and the two `./packages` path-repositories; `composer remove` dropped it and the transitive `oddvalue/laravel-drafts` (both gone from `vendor/` and `composer.lock`).
- Removed the two draft submodule entries from `.gitmodules` (the stale `filament-scout` entry — the app uses `kainiklas/filament-scout` from Packagist — was left in place; it can be removed separately).
- Deleted `config/drafts.php`.

## Bugs fixed (from the review)

1.1 misleading notification copy; 1.2 requester stamped in the handler; 1.3 checker/requester cleared on publish; 1.4 warnings read live state; 1.5 dead `check_requested` removed; 1.6 Unpublish implemented. **Deferred to a follow-up plan**: explicit `ReviewStatus` enum, domain events, real notifications, personal "assigned to me" queue.

## Verification performed

- `composer remove guava/filament-drafts` clean; `php artisan about` boots; admin trove routes register.
- `php artisan migrate:fresh --seed` builds the new schema (incl. the self-referencing FK); base + example seeders run.
- Tinker end-to-end (Scout driver null): public scope shows only published canonical; `draftFor` copies relations and leaves the public copy untouched; `publish` keeps the PK stable, records the old slug in `previous_slugs`, deletes the draft, sets `published_at`, clears the checker; `draftFor` idempotent; `discardDraft` restores the working-version count.
- No automated test suite added (the app has none yet — a separate effort).

## Recommended follow-up (not yet done)

- Manual admin walk-through on a machine with MySQL + Meilisearch, then `php artisan scout:sync-index-settings && php artisan scout:import "App\Models\Trove"`, and confirm `/browse-all` shows only published Troves.
