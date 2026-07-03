# Change Log — Test Suite Build-Out

Implements [docs/plans/test-suite-buildout.md](../plans/test-suite-buildout.md).

**Date:** 2026-07-03

## Summary

Built a Pest 4 test suite on SQLite `:memory:` from scratch, replacing the stale Laravel Breeze auth scaffolding. Final state: **135 passing, 1 skipped, 0 failing** (56 Unit + 79 Feature), running in ~8s. The suite gives smoke coverage of every public page and Filament panel resource/page, deep unit coverage of the `Trove` model logic, and integration coverage of the custom shadow-draft/canonical publishing model (`TrovePublisher`, the `PublicationState`/`ReviewState` enums, and their DB-mirror scopes).

## Tooling (Phase 0)

- Installed `pestphp/pest ^4.0` + `pestphp/pest-plugin-laravel ^4.0`. Pest 4 requires **PHPUnit 12** (the plan's "^11" was out of date), so `phpunit/phpunit` was bumped `^10.1 → ^12.0`. `php` constraint bumped `^8.1 → ^8.2` (Pest 4's floor).
- `phpunit.xml`: enabled `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`, `SCOUT_DRIVER=collection` (LIKE-based, no external Meilisearch), and `MEDIA_DISK`/`FILESYSTEM_DISK=public` (for `Storage::fake`).
- `tests/Pest.php`: binds `Tests\TestCase` + `RefreshDatabase` to Unit and Feature; adds helpers `actingAsAdmin()`, `publishedTrove()`, `draftTrove()`, `usePublicContext()`, `bootPublicSite()`.
- Deleted the dead Breeze auth tests (`tests/Feature/Auth/*`, `ProfileTest`, both `ExampleTest`s).

## Factories (Phase 1)

Rewrote all domain factories to match the current schema (JSON-translatable `title`/`description` set via `['en' => …]`; removed references to the deleted `App\Models\Type` and dropped columns `type_id`/`cover_image`/`public`/`youtube`). Added `TroveFactory` states (`published`, `draftOf`, `inReview`, `reviewed`, `withSlug`), `CollectionFactory::private()`, `TagFactory::ofType()`, `TagTypeFactory::slug()/shownInFilter()`. Added new `SiteSettingFactory` + `SiteContentFactory` (and the `HasFactory` trait on those two models). Modernised `UserFactory`'s password to `Hash::make('password')` (the old hard-coded cost-10 hash failed `Hash::verifyConfiguration()` under `BCRYPT_ROUNDS=4`).

## Tests by phase

- **Unit (Phase 2)** — enum label/color/icon maps; `Trove` state accessors, slug generation, `findBySlugOrRedirect`, `toSearchableArray`/`shouldBeSearchable`, download-link normalisation, cover-image locale fallback; `Collection` searchable shape; `SiteSetting`/`SiteContent`; `User::canAccessPanel`; `UsesCustomSearchOptions`.
- **TrovePublisher (Phase 3)** — `draftFor` (single draft, copy content/relations/media, idempotency + media UUID map on both paths, clean review slate), `publish` (never-published and shadow-draft branches: stable PK, preserved `published_at`, `previous_slugs` accumulation/dedup, review-approval handling), media rollback safety, `discardDraft`, `delete`, `unpublish`, `requestReview`/`completeReview`.
- **Model scopes (Phase 4)** — `TroveStateParity` (accessor ↔ DB-mirror scope agree for every column combination, both axes), `PublishedScope`, `workingVersions`/`awaitingReviewBy`, `relatedTroves`/`themeAndTopicTags`/`draft`/`publishedVersion`.
- **Public HTTP (Phase 5)** — `/`, `/home`, trove show (200 / 404 / 301-via-previous-slug / unpublished hidden), preview (guest vs authed), collection show, download-all-zip (stream vs error redirect), BrowseAll data-selection, and mount-smoke of the embedded Livewire components.
- **Filament (Phase 6)** — panel-access smoke for every registered page; Trove list tabs; Trove lifecycle header actions (mark reviewed / discard / unpublish / delete) and create/publish/fork-draft; Collection CRUD + AllTrovesTable attach/detach; TagType/Tag/TroveType create + reset-order + toggle; Site Options / Site Content page saves.

## Test-environment findings (important)

**`PublishedScope` is disabled by default in the test harness.** The scope self-disables whenever `Filament::getCurrentPanel()` is non-null. Because `PanelProvider::register()` resolves the `filament` singleton during boot, the default `admin` panel is registered as the *current* panel in every full-app boot (tests, tinker, console) — so by default a plain `Trove::all()` in a test sees drafts and unpublished rows (the admin-panel view). Tests that need the public visibility rules call `usePublicContext()` (which sets the current panel to `null`); the public HTTP tests use `bootPublicSite()`. This is worth remembering for any future test: assert on `published_at`/`published_id` columns directly when you're not explicitly in a public context.

**The public layout needs `config('branding.locales')`.** In production `AppServiceProvider::boot()` hydrates it from `SiteSetting`; under `RefreshDatabase` the table doesn't exist at boot, so `branding.php`'s (locale-less) defaults remain and `header.blade.php`'s `count(config('branding.locales'))` would fatal. `bootPublicSite()` sets it.

## SQLite caveats (documented gaps)

- **BrowseAll full render is not exercised over HTTP/Livewire.** Its render path builds the filter tag types with MySQL-only SQL (`ISNULL`, `JSON_EXTRACT`, `JSON_UNQUOTE`) and `search()` orders hits with MySQL's `FIELD()`; both raise syntax errors on SQLite. The data-selection logic (`fetchInitialData`/`mergeItems`, which explicitly filters `published_at`/`public`) is tested directly instead.
- **`EditTrove::isDuplicateDraftViolation()` keys on MySQL errno 1062** — the concurrent-first-fork race path is not faithfully testable on SQLite (happy path only), as anticipated by the plan.
- `whereJsonContains` on `previous_slugs` works via SQLite JSON1; tests pin the observed SQLite behaviour.
- Coverage spot-check (`--coverage`) was **not** run: no Xdebug/PCOV driver is installed locally. `TrovePublisher` and `Trove` are instead covered by dedicated, method-by-method test files.

## Latent product issues surfaced by the tests (not fixed here)

These are pre-existing behaviours the tests exposed; flagged for a follow-up decision rather than changed as part of a test-only task:

1. **`Trove::getDownloadableLinks()` chokes on genuinely-null link columns.** For a trove whose `youtube_links`/`external_links` JSON is null, `getTranslation()` returns `''` (not `null`), so the `?? []` guard misses it and a subsequent `foreach ('')` throws. `downloadAllFilesAsZip()` always calls this, so a published trove with null links would 500 on the public download route. The Download tests give explicit empty-array link translations to exercise the intended paths. (Real data seeded via `ExampleDataSeeder` always sets links, which likely masks this in practice.)
2. **The "No changes to save" guard in `EditTrove` never fires.** `$originalFormState` (the afterFill snapshot it compares against) is a `protected` property, so it is lost across Livewire's request round-trip and is `[]` by the time `save()` runs — the dirty-check is therefore always true and every plain Save of a live trove forks a shadow draft. The corresponding test is `->skip()`ed with this note.
3. **`Trove::getCoverImageUrl()` hardcodes `['en','es','fr']`** (plus the current locale) instead of using `config('branding.locales')`, so a cover held in any other configured, non-current locale is invisible to it — unlike the config-driven `coverImage`/`coverImageThumb` accessors. Pinned as a documented quirk in `CoverImageFallbackTest`.
4. **`Trove::coverImage` uses `?? asset(...)`** on `getFirstMediaUrl()`, which returns `''` (not `null`) when absent, so the default-image fallback never triggers for that accessor (returns an empty string). Noted; not asserted.
