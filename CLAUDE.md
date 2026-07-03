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
- **MySQL** — primary database (tests also run against MySQL — SQLite in-memory is commented out in `phpunit.xml`)
- Three local git submodules under `packages/`: `laravel-drafts`, `filament-drafts`, `filament-scout` (own forks, loaded as Composer path repos)

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

# Tests

**NOTE**: The Test Suite is not yet built out. Do not use the tests as a diagnostic or review tool!
#php artisan test
#php artisan test --testsuite=Unit
#php artisan test --testsuite=Feature
#php artisan test --filter=TestClassName

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

**Draft/versioning**: `Trove` uses `HasDrafts` (from `packages/laravel-drafts`). `$draftableRelations` on the model (`tags`, `troveTypes`, `collections`) controls what gets cloned into a new draft — media is cloned manually via the `drafted` model event in `Trove::booted()`. Preview route (`/resources/preview/{slug}`, auth-gated) loads drafts via `Trove::withDrafts()`; the public route (`/resources/{troveKey}`) only loads published records via `Trove::findBySlugOrRedirect()`, which also falls back through `previous_slugs` (JSON array) for old slugs before 404ing. The Filament admin UI uses `guava/filament-drafts` (`packages/filament-drafts`), with local customisations in `app/Filament/Draftable/`.

**Search**: `Trove` and `Collection` implement `Searchable` (Scout) with a custom `toSearchableArray()` that flattens all locale variants of title/description into single searchable strings. `UsesCustomSearchOptions` trait (used by `BrowseAll`) forces `showRankingScore` and a configurable `hitsPerPage` so results can be ranked and merged with DB-filtered results. After changing `toSearchableArray()` or index settings, re-run `scout:sync-index-settings` then reimport.

**Media**: Per-locale media collections registered dynamically from `config('app.locales')`/`config('branding.locales')` (e.g. `cover_image_en`, `content_en`). Cover images have a `cover_thumb` (450px) conversion. Both `Trove` and `Collection` implement locale-fallback accessors (`coverImage`, `coverImageThumb`) that try the current locale first, then fall back through other configured locales, then a static default asset. Storage disk is `FILESYSTEM_DISK`/`MEDIA_DISK` (S3 by default; set both to `local`/`public` for local dev without S3).

**Admin panel**: Filament resources in `app/Filament/Resources/` (Trove, Collection, TroveType, Tag, TagType). Panel config/navigation/plugins (Socialment for Azure AD login, Spatie translatable plugin) live in `app/Providers/Filament/AdminPanelProvider.php`. Custom Filament pages `SiteOptionsPage` and `SiteContentPage` manage the `SiteSetting`/`SiteContent` singleton-style config from the admin UI rather than `.env`.

**Frontend browsing**: `app/Livewire/BrowseAll.php` is the main search/browse surface — it queries Meilisearch for ranked hits, applies DB-side filters (tag, language), then merges Trove + Collection results into one `items` collection sorted by ranking score, with manual pagination (`loadPage`/`perPage`, not Laravel's paginator). Other Livewire components (`CollectionTroves`, `TroveCollections`, `TroveRelatedTroves`, `SearchBar`) are smaller, single-purpose pieces embedded in `trove.blade.php`/`collection.blade.php`.

### Directory layout

```
app/
  Filament/
    Resources/       # Admin CRUD for Trove, Collection, TroveType, Tag, TagType
    Pages/            # SiteOptionsPage, SiteContentPage, Login
    Draftable/        # Local customisations on top of guava/filament-drafts
    Translatable/     # Custom translatable form/table components for Filament
  Livewire/           # Public-facing interactive components (BrowseAll is the main one)
  Models/
  Traits/             # e.g. UsesCustomSearchOptions
packages/             # Git submodules: laravel-drafts, filament-drafts, filament-scout (own forks)
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
- Filament admin access gated by `canAccessPanel()` on `User`
