# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Work Patterns

1. Save all accepted plans to docs/plans.
2. Every plan file must have a **Status: ** line indicating the current status of the plan. Either: "Not Started", "In Progress", "Completed", "Abandoned". In progress plans should have a short paragraph explaining what has been done and what is left to do. Abandoned plans must have a short paragraph explaining the reason for not completing the plan. Completed plans must have a link to a corresponding change log file.
3. After completing a piece of work (e.g., finishing a plan), save a summary of the changes into docs/change-logs. Change logs files linked to a plan should reference the plan file in the intro.
4. Code reviews conducted via the /code-review skill or via explicit "feature review" requests should be saved to docs/code-reviews, with the following metadata at the top of the document:
    **Date**: 2026-05-27
    **Branch**: `review-roles-and-permissions`
    **Reviewer**: Claude (automated multi-angle review)
    **Scope**: 56 files, 1,945 insertions — new Spatie role/permission model with 5 roles and policies across three Filament panels.
5. If a user asks you to capture, note or save an issue, store it into docs/issues as a new .md file. If this is a Git repository with a Github origin, create a new issue on Github.



## What this is

A generalised, white-label Laravel template for building a "resources library" site (documents, videos, links, etc. organised into browsable/searchable Collections). It's a spin-off of Stats4SD's `stats4sd-resources` project, stripped of organisation-specific concepts (Hubs, Organisations, per-org theming) in favour of `.env`-driven branding so it can be reused for other orgs. When in doubt about a pattern that seems half-finished or overly generic here, it's likely because org-specific logic was deliberately removed — don't reintroduce Hub/Organisation-style concepts without being asked.

## Stack

- **Laravel 11** + **PHP 8.1+**
- **Filament 3.2** — admin panel at `/admin`
- **Livewire 3** — reactive frontend components (browse/search, related items)
- **Vue 3** + **Vite** + **Tailwind CSS** + **DaisyUI** — frontend assets
- **Meilisearch** via Laravel Scout — full-text search
- **Spatie Media Library** — per-locale file/media management
- **Spatie Translatable** — multilingual content (JSON columns; locales configured per-site, not hardcoded)
- **MySQL** — primary database. Tests target SQLite `:memory:` (see the Testing section) but the app runs on MySQL, and a couple of code paths are MySQL-specific (e.g. the `whereJsonContains` on `previous_slugs`, and `EditTrove::isDuplicateDraftViolation()` keying on MySQL errno `1062`).
- **Drafting is fully app-owned** — there are no `packages/` submodules and no `oddvalue/laravel-drafts` / `guava/filament-drafts` dependency (an older version of this doc described those; they are gone). Only `kainiklas/filament-scout` remains, installed as a normal Composer dependency. The shadow-draft/canonical model lives entirely in app code — see the Draft/versioning pattern below.

## Commands

```bash
# Frontend
npm run dev
npm run build

# Backend
php artisan migrate
php artisan migrate --seed                                   # base seed (site content/settings, tag types, trove types)
php artisan db:seed --class="Database\Seeders\Example\ExampleDataSeeder"  # optional example troves/collections/tags
php artisan scout:sync-index-settings                         # sync Meilisearch config after schema changes
php artisan scout:import "App\Models\Trove"                   # reindex a model (also: App\Models\Collection)

# Tests — Pest 4 on SQLite :memory: (see docs/change-logs/test-suite-buildout.md).
# Factories in database/factories/ are current and match the schema. Two test-harness gotchas:
#  - PublishedScope self-disables in tests because Filament registers the default panel as
#    "current" at boot; call usePublicContext() (tests/Pest.php) to exercise public visibility.
#  - The public layout needs config('branding.locales'); use the bootPublicSite() helper.
php artisan test
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=TestClassName

# Local services
meilisearch --master-key="aSampleMasterKey"                   # required unless SCOUT_DRIVER=null
```

There's no PHP linter/formatter config beyond Pint being a dev dependency (`vendor/bin/pint`).

## Branding (the reason this template exists)

Everything org-specific is meant to live in `.env` + `config/branding.php`, not in code:

- `BRAND_ORG_NAME`, `BRAND_HOME_URL`, `BRAND_LINKEDIN_URL`, `BRAND_YOUTUBE_URL` — read via `config/branding.php`
- Colours/font: CSS variables under `:root` in `resources/css/app.css` (`--brand-primary`, `--brand-secondary`, `--brand-bg`, `--brand-footer-bg`, `--brand-footer-text`, `--brand-font`). `AdminPanelProvider` even regex-scrapes `--brand-primary` out of `app.css` at runtime to colour the Filament admin panel — see `app/Providers/Filament/AdminPanelProvider.php::brandPrimary()`.
- Logo (`public/images/logo.png`) and banner (`public/images/banner.png`) are optional; views hide them automatically if the file doesn't exist.
- Languages are **not** a static config array — they're managed at runtime via the admin panel ("Site Options" → `SiteSetting` model, `locales` JSON column) and read through `config('branding.locales', ...)` with an `['en' => 'English']` fallback everywhere. `config('app.locales')` is the legacy/static equivalent still used in a few places (media collection registration). When adding locale-aware code, prefer `config('branding.locales', ...)`.
- UI string translation (not content translation) goes through Translation.io (`config/translation.php`, `TRANSLATIONIO_KEY`) — optional, only needed for multi-language UI chrome.

## Architecture

### Core entities

| Model | Purpose |
|---|---|
| `Trove` | A single learning resource (document, video, link, etc.) |
| `Collection` | A curated group of Troves |
| `TroveType` | Resource type classification (replaces the old org-specific `Type`) |
| `Tag` + `TagType` | Flexible tagging taxonomy, polymorphic (`morphToMany`) onto Trove |
| `SiteSetting` | Singleton row (`SiteSetting::instance()`) holding `show_language_filter` and the `locales` list |
| `SiteContent` | Key/value translatable CMS strings, fetched via `SiteContent::get($key)` |

There is no `Organisation` or `Hub` model in this template — those were org-specific concepts removed during generalisation. `AllTrovesTable`, `Resources`/`Collections` search pages, and hub-specific browse pages that existed upstream were also removed; the single `BrowseAll` Livewire component now handles combined resource+collection search.

### Key patterns

**Multilingual content**: `Trove` and `Collection` use `HasTranslations` (Spatie). Fields like `title`, `description` are JSON columns keyed by locale. Locale is set by the `set.locale` middleware on all web routes (`routes/web.php`).

**Draft/versioning (app-owned shadow-draft/canonical model)**: There is no drafts *package*. Each logical Trove is one **canonical** row plus at most one **shadow-draft** row (a separate `troves` row linked by `published_id → canonical.id`, guarded by `unique(published_id)`). `published_at` is the sole source of truth for "is this published" (NULL = not). Two orthogonal state axes are derived from columns alone: `Trove::publicationState()` → `App\Enums\PublicationState` (Draft / Published / PendingChanges, from `published_id`+`published_at`) and `Trove::reviewState()` → `App\Enums\ReviewState` (None / InReview / Reviewed, from `review_requested_at`/`reviewed_at`). Each accessor has a **DB-mirror scope** (`scopeWithPublicationState`, `scopeWithReviewState`) that must stay in parity with it (the code comments call for a locked parity test — see the test plan).

`App\Services\TrovePublisher` is the ONLY place that mutates the lifecycle (`draftFor`, `publish`, `discardDraft`, `delete`, `unpublish`, `requestReview`, `completeReview`); the drafting rules (stable canonical PK, at most one draft, review handshake, media stash-then-purge on publish) live there. `$draftableRelations` (`tags`, `collections`) is what `TrovePublisher` copies between canonical and draft (`trove_type_id` is a plain column on `troves` and copies automatically with the rest of the row's attributes); media is copied via Spatie's `Media::copy()` (superseded media is renamed out of its collection in-transaction, then deleted on disk only after commit — swept up by `PruneSupersededMedia` if the post-commit purge fails).

`App\Models\Scopes\PublishedScope` (global on `Trove`) restricts public/console/queue/Scout reads to published canonicals, but **self-disables whenever a Filament panel is the current request context**, so the admin sees every version. Preview route (`/resources/preview/{slug}`, auth-gated) loads the working version via `Trove::withDrafts()->workingVersions()`; the public route (`/resources/{troveKey}`) loads only published canonicals via `Trove::findBySlugOrRedirect()`, which falls back through `previous_slugs` (JSON array) for old slugs before 404ing.

The Filament seam is `app/Filament/Resources/TroveResource/Pages/EditTrove.php`: saving a *live* trove forks a shadow draft (`forkToDraftAndRebind` + `remapMediaState`) so edits land on the draft, not the live row; there is no `app/Filament/Draftable/` directory.

**Search**: `Trove` and `Collection` implement `Searchable` (Scout) with a custom `toSearchableArray()` that flattens all locale variants of title/description into single searchable strings. `UsesCustomSearchOptions` trait (used by `BrowseAll`) forces `showRankingScore` and a configurable `hitsPerPage` so results can be ranked and merged with DB-filtered results. After changing `toSearchableArray()` or index settings, re-run `scout:sync-index-settings` then reimport.

**Media**: Per-locale media collections registered dynamically from `config('app.locales')`/`config('branding.locales')` (e.g. `cover_image_en`, `content_en`). Cover images have a `cover_thumb` (450px) conversion. Both `Trove` and `Collection` implement locale-fallback accessors (`coverImage`, `coverImageThumb`) that try the current locale first, then fall back through other configured locales, then a static default asset. Storage disk is `FILESYSTEM_DISK`/`MEDIA_DISK` (S3 by default; set both to `local`/`public` for local dev without S3).

**Admin panel**: Filament resources in `app/Filament/Resources/` (Trove, Collection, TroveType, Tag, TagType). Panel config/navigation/plugins (Socialment for Azure AD login, Spatie translatable plugin) live in `app/Providers/Filament/AdminPanelProvider.php`. Custom Filament pages `SiteOptionsPage` and `SiteContentPage` manage the `SiteSetting`/`SiteContent` singleton-style config from the admin UI rather than `.env`.

**Frontend browsing**: `app/Livewire/BrowseAll.php` is the main search/browse surface — it queries Meilisearch for ranked hits, applies DB-side filters (tag, language), then merges Trove + Collection results into one `items` collection sorted by ranking score, with manual pagination (`loadPage`/`perPage`, not Laravel's paginator). Other Livewire components (`CollectionTroves`, `TroveCollections`, `TroveRelatedTroves`, `SearchBar`) are smaller, single-purpose pieces embedded in `trove.blade.php`/`collection.blade.php`.

### Directory layout

```
app/
  Enums/              # PublicationState, ReviewState (the two Trove lifecycle axes)
  Filament/
    Resources/       # Admin CRUD for Trove, Collection, TroveType, Tag, TagType
    Pages/            # SiteOptionsPage, SiteContentPage, Login
    Translatable/     # Custom translatable form/table components for Filament
  Livewire/           # Public-facing interactive components (BrowseAll is the main one)
  Models/
    Scopes/           # PublishedScope (global scope on Trove)
  Services/           # TrovePublisher — owns every draft/publish lifecycle transition
  Traits/             # e.g. UsesCustomSearchOptions
  Console/Commands/   # e.g. PruneSupersededMedia (sweeps orphaned superseded media)
resources/
  views/              # Blade templates (home, trove, collection, layouts/, components/)
  css/                # Tailwind entry point + brand CSS variables (app.css)
routes/
  web.php             # Public routes, all under `set.locale` middleware
database/
  seeders/Prep/       # Base seed data (tag types, trove types, site content/settings)
  seeders/Example/    # Optional example troves/collections for local dev
```

### Authentication

- Standard Laravel Auth + Azure AD via `chrisreedio/socialment` (`connected_accounts` table)
- Filament admin access is gated by `User::canAccessPanel()`, which **currently returns `true` unconditionally** — any authenticated user reaches `/admin`. (`spatie/laravel-permission` is not installed; the orphaned `database/migrations/permissions/` folder is dead code and does not run.)
- `App\Providers\AppServiceProvider::boot()` calls `Model::unguard()` globally and hydrates `config('app.locales')` / `config('branding.locales')` from `SiteSetting::instance()` at boot (wrapped in try/catch for fresh installs). In tests with an empty DB at boot, those configs fall back to the static `config/app.php` default of `['en' => 'English']`.
