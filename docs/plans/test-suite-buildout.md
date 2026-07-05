# Test Suite Build-Out — resources-site-template

**Status:** Completed — see [docs/change-logs/test-suite-buildout.md](../change-logs/test-suite-buildout.md). Final suite: 135 passing, 1 skipped (56 Unit + 79 Feature) on Pest 4 / SQLite `:memory:`. Note: Pest 4 required PHPUnit 12 (not 11 as written below), and `PublishedScope` self-disables in the test harness because Filament registers the default panel as current at boot — public-context tests call `usePublicContext()`. Several latent product issues were surfaced (see the change log's "Latent product issues" section) but left unfixed as this was a test-only task.

## Context

The application has no meaningful test coverage. `tests/` contains only stale Laravel Breeze auth scaffolding (`tests/Feature/Auth/*`, `ExampleTest`s) that doesn't match this app (it uses Azure AD SSO, has no registration/email-verification flow). Meanwhile the codebase has grown a genuinely intricate, custom **shadow-draft / canonical publishing model** — `App\Services\TrovePublisher` plus the `PublicationState`/`ReviewState` enums and their DB-mirror scopes — that is entirely unverified. This is exactly the kind of logic that regresses silently.

Goal: a Pest 4 suite running on SQLite `:memory:` that gives (1) smoke coverage of every public page and every Filament panel resource/page, (2) deep unit coverage of `TrovePublisher` and the `Trove` model logic, and (3) end-to-end CRUD feature tests for the Filament resources.

Two important discoveries from exploration correct the older CLAUDE.md description:
- There are **no `packages/` submodules** and **no `oddvalue/laravel-drafts` / `guava/filament-drafts`**. Drafting is 100% app-owned (native columns on `troves` + `TrovePublisher` + `PublishedScope`). This removes the main feared SQLite-compatibility risk.
- **Every domain factory is broken** (references a deleted `App\Models\Type`, wrong columns like `cover_image`/`type_id`, plain strings for JSON-translatable fields). Rebuilding factories is the prerequisite blocker for everything else.

## Decisions (confirmed with user)

- **DB engine:** SQLite `:memory:` only. Accept that a few MySQL-specific paths are approximations (see *SQLite caveats*).
- **Frontend smoke tests:** HTTP response assertions (`get()->assertOk()`, `assertSee`) + Livewire component tests. No real-browser/Playwright layer.

## Key files (reference during build)

- `app/Services/TrovePublisher.php` — the lifecycle owner; primary unit-test target.
- `app/Models/Trove.php` — accessors (`publicationState`, `reviewState`, `isPublished`, `hasPublishedVersion`, cover-image fallbacks), scopes (`withPublicationState`, `withReviewState`, `workingVersions`, `awaitingReviewBy`, `withDrafts`), `findBySlugOrRedirect`, `saving` slug generation, `toSearchableArray`/`shouldBeSearchable`.
- `app/Models/Scopes/PublishedScope.php` — self-disables under a Filament panel; else `whereNotNull(published_at)`.
- `app/Enums/PublicationState.php`, `app/Enums/ReviewState.php`.
- `app/Models/Collection.php`, `Tag.php`, `TagType.php`, `TroveType.php`, `SiteSetting.php`, `SiteContent.php`, `User.php`.
- `app/Traits/UsesCustomSearchOptions.php`.
- `app/Filament/Resources/TroveResource/Pages/EditTrove.php` — draft-forking on save (`save()`, `forkToDraftAndRebind`, `remapMediaState`); ties the Filament UI to `TrovePublisher`.
- Existing (broken) factories in `database/factories/`; seeders in `database/seeders/Prep/` and `Example/ExampleDataSeeder.php` (the latter is the correct reference for building Troves in the current schema, incl. `withoutSyncingToSearch()`).

## Phase 0 — Tooling & config

1. **Install Pest 4.** Local PHP is 8.4.17 so Pest 4 runs fine. `composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies`. This upgrades `phpunit/phpunit ^10.1 → ^11`. Bump the `php` constraint in `composer.json` to `^8.2` to match Pest 4's floor (composer resolves against the 8.4 platform regardless, but keep the manifest honest). `pestphp/pest-plugin` is already allow-listed.
2. **`phpunit.xml`** — uncomment/add the SQLite env and add a Scout driver:
   - `<env name="DB_CONNECTION" value="sqlite"/>`
   - `<env name="DB_DATABASE" value=":memory:"/>`
   - `<env name="SCOUT_DRIVER" value="collection"/>` (LIKE-based DB engine — lets `BrowseAll` return real hits with no external service; ignores the Meili-only options callback harmlessly).
   - `<env name="MEDIA_DISK" value="public"/>` and `<env name="FILESYSTEM_DISK" value="public"/>` (media tests use `Storage::fake('public')`).
3. **`tests/Pest.php`** — bind `Tests\TestCase` and `RefreshDatabase` to `tests/Feature` and `tests/Unit`. Add shared helpers/expectations here (e.g. an `actingAsAdmin()` helper, a `publishedTrove()` helper).
4. **Delete dead Breeze auth tests** (`tests/Feature/Auth/*`, `ProfileTest.php`, both `ExampleTest`s) — they test routes this app doesn't have and will just fail. Keep `TestCase.php`/`CreatesApplication.php`.
5. Confirm `config/app.php` already defaults `locales => ['en' => 'English']`, so `registerMediaCollections()`/`toSearchableArray()` never iterate null in tests. Where a test needs multiple locales, it sets `config(['app.locales' => [...], 'branding.locales' => [...]])` in an arrange step.

## Phase 1 — Factories (the blocker)

Rewrite all domain factories to match current migrations; add the two missing ones. Model these on `ExampleDataSeeder`.

- `TroveFactory` — JSON-translatable `title`/`description` (set via `['en' => ...]` or plain string, which Spatie stores under the app locale), `slug` left null so the `saving` hook can be exercised (with an explicit-slug state too), `trove_type_id` nullable, `uploader_id => User::factory()`, `source` bool, `creation_date`, `published_at` null by default. **States:** `published()` (sets `published_at`), `draftOf(Trove $canonical)` (sets `published_id`), `inReview()`, `reviewed()`. Do **not** set `type_id`/`cover_image`/`public`/`youtube`/`download_count`-as-random.
- `CollectionFactory` — JSON `title`/`description`, `public` bool, `uploader_id`; drop `cover_image`.
- `TagFactory` — `name` (JSON), `type_id => TagType::factory()`, `order_column`.
- `TagTypeFactory` — `label`/`description` (JSON), unique `slug`, `freetext`/`show_in_filter`/`use_custom_tag_order` bools.
- `TroveTypeFactory` — `label` (JSON) only; remove the `Type` import.
- **New** `SiteSettingFactory`, `SiteContentFactory` (small, mainly for the SiteContent/SiteSetting unit tests).

Add a `relatedTroves`-friendly helper (attach troves to a shared collection) either as a factory `afterCreating` state or a Pest helper.

## Phase 2 — Unit tests (pure logic, `tests/Unit/`)

Fast, mostly no-DB or single-model. One file per subject:

- `PublicationStateEnumTest` / `ReviewStateEnumTest` — label/color/icon maps (guards against accidental case changes).
- `TroveStateAccessorsTest` — `publicationState` (Draft / Published / PendingChanges by `published_id`+`published_at`), `reviewState` (None / InReview / Reviewed, `reviewed_at` precedence over lingering `review_requested_at`), `isPublished`, `hasPublishedVersion`.
- `TroveSlugGenerationTest` — `saving` hook: generates `slug-of-title-YYYY-MM-DD`; does not regenerate when a slug is already set; appends `-N` on collision (create colliding titles; assert suffix); uniqueness query spans trashed + drafts.
- `TroveFindBySlugOrRedirectTest` — all four resolution branches (slug, numeric id, `previous_slugs` string, `previous_slugs` numeric) + null miss; confirms it only ever returns a canonical (`published_id` null) published row.
- `TroveSearchableTest` — `shouldBeSearchable()` true only for published canonical (false for drafts and unpublished); `toSearchableArray()` aggregates unique non-empty titles/descriptions across `config('app.locales')` and strips HTML from descriptions.
- `TroveDownloadLinksTest` — `getDownloadableLinks()`/`buildLinksManifest()` normalise single-vs-array `external_links`/`youtube_links`, filter empties, build YouTube URLs (pure, no disk).
- `CollectionSearchableTest` — `toSearchableArray()` shape incl. `public` flag; note there is no `shouldBeSearchable()` override (all collections index) — assert that as documented behaviour.
- `CoverImageFallbackTest` — Trove `coverImageThumb` and Collection `coverImage`/`coverImageThumb` ordered-locale fallback (needs `Storage::fake` + media). Flag the `getCoverImageUrl()` **hardcoded `['en','es','fr']`** divergence from the `config('branding.locales')`-driven accessors as a documented quirk (a test that pins current behaviour, tagged for follow-up).
- `SiteSettingTest` — `instance()` singleton (creates once, returns same row); `localesAsConfig()` filters malformed entries.
- `SiteContentTest` — `get()` returns null for missing key, null for empty value, respects locale defaulting.
- `UserCanAccessPanelTest` — currently returns `true` unconditionally; pin it (a change-detector, since it's a security-relevant surface).
- `UsesCustomSearchOptionsTest` — invoke the returned closure against a mock Meili object; assert `hitsPerPage` (from `scout.scout_search_limit`, default 500) and `showRankingScore => true` are passed.

## Phase 3 — TrovePublisher integration tests (the core, `tests/Feature/Services/`)

Highest-value. Uses DB + `Storage::fake('public')`; attach media via `addMediaFromString()` / `UploadedFile::fake()`. One file, grouped by method:

- **`draftFor()`** — creates exactly one shadow draft (`published_id` = canonical id, `published_at` null); copies content, relations (`tags`/`troveTypes`/`collections`) and media; is **idempotent** (second call returns the same draft, no duplicate row); populates `$mediaUuidMap` correctly on both the fresh path and the existing-draft path (`existingDraftMediaMap`: pairs by unique `file_name`, else positionally, and omits divergent-count collections).
- **`publish()` — never-published canonical branch** — sets `published_at` in place (once), applies review state, returns same instance.
- **`publish()` — shadow-draft branch** — folds draft content/relations/media onto the canonical with **stable PK**; preserves original `published_at` on re-publish; records slug change into `previous_slugs` (dedup, accumulates); clears the review *request* but preserves the *approval* stamp (`reviewed_at`+`reviewer_id`) only when the draft was actually reviewed; deletes the draft row; replaces canonical media (stash-then-copy) and purges superseded media **after** commit.
- **Media rollback safety** — force a failure inside the publish transaction (e.g. mock a save throwing) and assert no file was deleted from disk (stash is a DB-only rename; purge is post-commit).
- **`discardDraft()`** — hard-deletes the draft; no-op when passed a non-draft; canonical untouched.
- **`delete()`** — from either a draft or a canonical: hard-deletes the draft (so it can't linger soft-deleted and occupy `unique(published_id)`), soft-deletes the canonical.
- **`unpublish()`** — with no draft (just nulls `published_at`); with a pending draft (folds it in first, then unpublishes, leaving exactly one row and no orphan draft).
- **`requestReview()` / `completeReview()`** — request sets `review_requested_at`/reviewer/requester (requester falls back to `auth()->id()`); complete overwrites `reviewer_id` with the actual reviewer and stamps `reviewed_at`. Round-trip through publish: reviewed draft → published canonical keeps the approver; unreviewed → no false attribution.

## Phase 4 — Model scope / query tests (`tests/Feature/Models/`)

- **`TroveStateParityTest`** — the parity the code comments explicitly ask for: for every combination of `published_at`/`published_id`, the `publicationState()` accessor and `scopeWithPublicationState()` agree on membership; same for `reviewState()` vs `scopeWithReviewState()`. Data-provider over all cases.
- **`PublishedScopeTest`** — outside a panel, default `Trove::all()` returns only published canonicals (drafts + never-published hidden); `withDrafts()` opts out; relationship queries (e.g. `Collection::troves`) also hide unpublished. (Panel-on behaviour is covered implicitly by the Filament tests, where the scope self-disables.)
- **`TroveWorkingVersionsTest`** — `workingVersions()` yields the draft when present, else the canonical; `awaitingReviewBy($userId)` returns only outstanding-review working rows assigned to that user.
- **`TroveRelationsTest`** — `relatedTroves()` (shares a collection, excludes self), `themeAndTopicTags()` (filters to `themes`/`topics` tag types), `draft()`/`publishedVersion()` inverse links ignore `PublishedScope`.

## Phase 5 — Public HTTP smoke tests (`tests/Feature/Http/`)

Guest unless noted. Seed `Prep` data (tag/trove types, site content) where the view needs it; build content via factories.

- `/` → 302 to `/home`; `/home` → 200, renders site content.
- `/browse-all` → 200; `Livewire::test(BrowseAll::class)` mounts, lists published troves + public collections, and filters by tag/language and search `$query` (collection Scout driver returns hits). Assert unpublished troves and non-public collections are absent.
- `/resources/{slug}` → 200 for a published trove; 404 for unknown; **301 redirect** to canonical slug when requested via a `previous_slugs` entry.
- `/resources/preview/{slug}` → empty/blocked for guests; for an authed user returns 200 and shows the working (draft) version.
- `/collections/{id}` → 200 for existing, 404 otherwise.
- `/download-all-zip/{slug}` → streams a zip when the trove has content media/links (Storage::fake + attached media); redirects back with an error when nothing is downloadable.
- Embedded Livewire smoke: `CollectionTroves`, `TroveCollections`, `TroveRelatedTroves`, `SearchBar` mount without error.

## Phase 6 — Filament panel feature tests (`tests/Feature/Filament/`)

Authenticate with a factory user (`canAccessPanel` returns true, so any user works) via `actingAs`. Use Filament's Livewire test helpers (`livewire(ListTroves::class)`, `assertCanSeeTableRecords`, `callAction`, `fillForm`, `assertHasNoFormErrors`, etc.). The `PublishedScope` self-disables under the panel, so drafts are visible here.

- **Panel access & pages render (smoke):** `/admin/login` (custom `Login`) renders; each registered page returns 200/renders for an authed user — `ListTroves`, `CreateTrove`, `EditTrove`, `ListCollections`, `CreateCollection`, `EditCollection`, `ViewCollection`, `ListTroveTypes`, `ListTags`, `ListTagTypes`, `EditTagType`, and the two custom pages `SiteOptionsPage` + `SiteContentPage`.
- **TroveResource list tabs** — the five tabs (All / Drafts / In review / Needs my review / Published) each return the correct record set; "Needs my review" badge count matches `awaitingReviewBy`.
- **Trove CRUD end-to-end** (the important one — exercises the `EditTrove` ↔ `TrovePublisher` seam):
  - Create a trove via `CreateTrove` form → row exists, unpublished.
  - Publish via the form action → `published_at` set, indexed.
  - Edit a **live** trove with a real change → a shadow draft is forked (canonical unchanged, one draft row); "no changes" plain-save forks nothing and notifies.
  - "Mark as reviewed", "Discard draft changes", "Unpublish", Delete header actions each call the right `TrovePublisher` method and leave consistent state.
- **CollectionResource CRUD** — create/edit/view; `TrovesRelationManager` attach/detach; the `AllTrovesTable` Livewire component (used in the collection screen) renders.
- **TagType / Tag / TroveType** — modal/`ManageRecords` create/edit/delete; TagType reorder + "Reset to alphabetical" bulk action; `show_in_filter`/`use_custom_tag_order` toggles persist.
- **SiteOptionsPage / SiteContentPage** — `save()` persists to `SiteSetting` (locales repeater, language-filter toggle) and `SiteContent` (translatable key/values).

## Proposed file layout

```
tests/
  Pest.php                     # bindings + helpers (actingAsAdmin, publishedTrove, ...)
  Unit/
    Enums/{PublicationState,ReviewState}EnumTest.php
    Models/
      Trove/{StateAccessors,SlugGeneration,FindBySlug,Searchable,DownloadLinks,CoverImageFallback}Test.php
      Collection/SearchableTest.php
      {SiteSetting,SiteContent,UserCanAccessPanel}Test.php
    Traits/UsesCustomSearchOptionsTest.php
  Feature/
    Services/TrovePublisherTest.php
    Models/{TroveStateParity,PublishedScope,TroveWorkingVersions,TroveRelations}Test.php
    Http/{Home,BrowseAll,TroveShow,Preview,Collection,Download}Test.php
    Filament/
      PanelAccessTest.php
      Trove/{ListTabs,Crud,LifecycleActions}Test.php
      Collection/CrudTest.php
      {TagType,Tag,TroveType}Test.php
      {SiteOptions,SiteContent}PageTest.php
```

## SQLite caveats (document in `tests/Pest.php` header + the change-log entry)

- **`whereJsonContains` on `previous_slugs`** (`findBySlugOrRedirect`) — works on SQLite via its JSON1 functions, but string-vs-numeric matching can differ subtly from MySQL. Tests pin the behaviour observed on SQLite; keep the note so a prod-only bug isn't masked.
- **`EditTrove::isDuplicateDraftViolation()` checks MySQL errno `1062`** (`app/Filament/Resources/TroveResource/Pages/EditTrove.php:204`). SQLite raises a different code, so the concurrent-first-fork race path is **not** faithfully testable on SQLite. Cover the happy path only; leave the race path as a documented gap (or, as a follow-up, make the check driver-agnostic).
- JSON-translatable ordering/whitespace can differ; assert on decoded values, never raw JSON strings.

## Verification

1. `composer require` succeeds; `php artisan test` boots on SQLite `:memory:` (assert via a trivial passing test first).
2. Run in layers to isolate failures: `php artisan test --testsuite=Unit`, then `--filter=TrovePublisher`, then `--testsuite=Feature`.
3. Green whole-suite run; spot-check with `--coverage` that `TrovePublisher` and `Trove` are well covered.
4. On completion, write `docs/change-logs/test-suite-buildout.md` linking back to this plan, and set this plan's **Status** to Completed with that link.
