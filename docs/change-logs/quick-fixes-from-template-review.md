# Change Log: Quick Fixes from Template Review (Workstreams A–F)

**Date**: 2026-07-07
**Plan**: [docs/plans/quick-fixes-from-template-review.md](../plans/quick-fixes-from-template-review.md)
**Source review**: [docs/code-reviews/template-full-review.md](../code-reviews/template-full-review.md)

Executes all six workstreams of the quick-fixes plan as a stack of branches off `dev`: `fix/security-access-control` (A) → `fix/draft-lifecycle` (B) → `fix/search-frontend` (C) → `fix/config-boot-ops` (D) → `chore/dead-code-cleanup` (E, with the F follow-through folded in). Full Pest suite green at the end of every workstream; final state 231 passed, 1 pre-existing skip.

## A. Security & access control (`288479d`)

Completed prior to this session: escaped trove titles in result cards; `public` enforced on collections at the route, `TroveCollections`, and Scout (`shouldBeSearchable`); bulk-delete bypass on users blocked (`deleteAny` false + bulk action removed); invite re-validated at registration submit; `Password::default()` on admin-set passwords; Resend hidden for accepted invites. New tests for each.

## B. Draft/publish lifecycle & data integrity (`5b468c3`)

- `TrovePublisher::publish()` now purges superseded media via `DB::afterCommit()`, so `unpublish()`'s nested publish no longer deletes files that a rollback would resurrect rows for.
- Draft deletion no longer removes media files inside the transaction: `Trove::forceDeletePreservingMedia()` sets Spatie's preserve flag inside the model, and file deletion defers to `DB::afterCommit()`. `PruneSupersededMedia` gained an orphan sweep as backstop.
- `previous_slugs` is now recorded on any canonical slug change via the `saving` hook, so unpublish→retitle→republish keeps old URLs 301-ing.
- `Trove::generateSlug()` uses a collision loop over canonical rows (`withDrafts()->withTrashed()->whereNull('published_id')`) instead of the count-based suffix.
- `AllTrovesTable` and `CollectionResource` `troves_count` use `workingVersions()` — pending-changes troves appear once.
- `PruneSupersededMedia` subquery sees all trove rows (`withoutGlobalScope(PublishedScope::class)->withTrashed()`), with first tests for the command.
- `config/scout.php` `after_commit => true`.

## C. Search & public frontend (`c0778d6`)

- Zero-hit searches return zero results instead of the unfiltered library; both Meilisearch `raw()` calls go through a `searchHits()` helper that catches engine outages, reports the exception, and renders a "search temporarily unavailable" notice.
- `translation.target_locales` hydrated at boot from runtime locales; static `locales` fallback added to `config/branding.php`; the "Show language filter" admin toggle now actually gates the filter (plus a >1-locale check).
- `getCoverImageUrl()` derives locales from `config('branding.locales')` instead of a hardcoded list.
- Preview route is `auth`-middleware-gated (guests redirect to the Filament login; `Authenticate::redirectTo()` repointed from the nonexistent `route('login')`).
- Single-item associative `youtube_links` render correctly; post-`@endsection` scripts moved inside sections and the stray `</div>` removed (quirks-mode fixes); pagination fixes (`'bg-gray-50'` quoting, `loadPage()` clamping, `wire:key`, empty-state distinguishes empty library from no matches); zip route falls back through `previous_slugs`; dead `BrowseAll::$locale` assignment and `SearchBar::$previousQuery` removed.

## D. Config, boot & ops (`95e8283`)

- Telescope moved to require-dev with composer `dont-discover`; registered conditionally (`local` + `class_exists`) in `AppServiceProvider::register()`; `PurgeTelescopeEntries` schedule guarded; `??` → `?:` gate bug and `OPTIMISE` → `OPTIMIZE` typo fixed; telescope migrations kept.
- `.env.example`: stray `AZURE_SECRET` and duplicate `SESSION_DRIVER` removed. Scout driver defaults to `null`; README notes that search requires explicitly setting `SCOUT_DRIVER=meilisearch`.
- `SiteContentSeeder` warns (null-safe) when `BRAND_ORG_NAME` is unset/default.
- `config/media-library.php` `max_file_size` set to 100MB and comment aligned.

## E. Dead code & cruft deletion (`43c8714`)

- Orphaned Breeze scaffolding deleted (`routes/auth.php`, 9 Auth controllers, `LoginRequest`, `ProfileUpdateRequest` — including the latent registration bypass in `RegisteredUserController`).
- Deleted: `app:rdbc` + old-DB config remnants, `.github/workflows/main.yml`, `draft.yaml` (+ blueprint dev-dep), 15 unused images, `lang/gettext/{es,fr}` Stats4SD exports, unused pagination component, `ViewTrove` page, `file_name_{locale}` loop, `getRecordTitleAttribute()`, duplicate `Trove::user()` relation (uploader filter retargeted to `uploader`), `ImportTrovesToSearch`, `front_end_url`, unused `Trove::coverImage` accessor.
- `spatie/laravel-ray` moved to require-dev; `composer.json` renamed to `stats4sd/resources-site-template`; MIT `LICENSE` added.

## F. Follow-through (`da62188`, `09297b7`)

- CLAUDE.md drift fixed: Socialment is documented as installed but not wired into the panel; cover-image accessor docs corrected; Scout `after_commit`/null-driver default documented.
- Final whole-branch review fixes: Uploader table column retargeted `user.name` → `uploader.name` (was blank + 500 on sort after the relation deletion) with a column-state test; `translation.source_locale` hydrated at boot alongside `target_locales`; `.env.example` `SCOUT_DRIVER` commented out to match the null default.

## Known follow-ups (deliberately not done here)

- `recordPreviousSlug` also accumulates pre-publish scratch slugs on never-published canonicals (harmless; could guard on "has ever been published").
- `PurgeTelescopeEntries` remains registered in production and would fail if run manually on a `--no-dev` install (schedule is guarded; a `class_exists` early-return would make manual runs graceful).
- Pre-existing `private const` declarations in `TrovePublisher` (predate this work).
- Larger excluded items remain tracked in the plan's cut list (BrowseAll rewrite, stale-editor clobber, rich-text sanitiser, token hashing, Socialment product decision, etc.).
