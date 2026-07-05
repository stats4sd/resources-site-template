# Upgrade to Laravel 13 + Filament 5 + Livewire 4

**Status:** In Progress

> Work happens on the existing `update-to-laravel-13` branch. Keep this Status line updated (see CLAUDE.md work-pattern #2).
>
> **Progress (2026-07-04):** Phases 0–5 done. Phase 3 (Filament 3→5) **complete — the panel boots and the full Pest suite passes** (135 passed, 1 pre-existing skip). Phase 5 (Tailwind 4 + DaisyUI 5) **complete — `npm run build` passes** (both `app.css` and the Filament `theme.css` compile). Phase 4 (Livewire 3→4) **complete — see note below; full suite still green (135 passed, 1 skipped)**. Phase 6 (Scout reindex + end-to-end verification) not started. A **visual regression pass on the public site + admin panel is still owed** (high-risk item #4).
>
> **Phase 4 (2026-07-04):** Livewire 3→4 front-end migration. `config/livewire.php`: renamed `layout`→`component_layout` (value kept `layouts.app`) and `lazy_placeholder`→`component_placeholder`; all other new v4 keys (`payload` guards, `release_token`, `csp_safe`, `smart_wire_keys`, `component_locations`/`component_namespaces`, `make_command`) intentionally left unset — Livewire's `mergeConfigFrom` fills them from the vendor default, and the app's customised `temporary_file_upload` block is preserved. `routes/web.php`: `Route::get('/browse-all', BrowseAll::class)` → `Route::livewire('/browse-all', BrowseAll::class)` (v4-preferred macro; registers via `LivewirePageController`). **No blade changes were needed after verifying v4 semantics against the vendor JS:** (a) the nested filter checkboxes in `browse-all.blade.php` each carry `wire:model="selectedTagsByType.{{ $id }}"`/`wire:model="selectedLanguages"` **plus `wire:change="search"` on the same `<input>`** — v4's default `.self` binding is correct here (the change event originates on the element itself), so no `.deep` is required; the deferred `wire:model` value is batched into the `wire:change` network request as before. (b) `$wire.entangle('currentPage')` in the pagination defaults to `live=false` (deferred), which is fine because Alpine only *reads* `currentPage` — server-side `loadPage()` writes it and syncs back on the roundtrip. (c) `SearchBar`/`BrowseAll` `protected $listeners = [...]` and `@livewireScripts` (relying on `inject_assets`) remain valid in v4. Verified: `route:list` shows the browse-all route; `BrowseAllTest`, `EmbeddedLivewireTest` and the full suite pass. **Note the v4 asset URL prefix changed `/livewire/`→`/livewire-{hash}/`** — no code change, but any CSP/firewall/CDN allowlist must be updated at deploy time.
>
> **Phase 5 (2026-07-03):** Full project moved to Tailwind v4 + DaisyUI 5 on Vite 8. `package.json`: `tailwindcss ^4.3`, `daisyui ^5.6`, `@tailwindcss/vite ^4.3` (new), `laravel-vite-plugin ^3.1`, `vite ^8.1`, `@tailwindcss/forms ^0.5.11`, `@tailwindcss/typography ^0.5.20`; dropped `autoprefixer`, `postcss`, `postcss-nesting`, `@vitejs/plugin-vue` (TW4's Lightning CSS handles prefixing; no JS/Vue entry point exists). `vite.config.js`: added `@tailwindcss/vite` plugin, removed the vestigial `@vitejs/plugin-vue` + the `vue` resolve alias. **Deleted** `postcss.config.js`, root `tailwind.config.js`, and `resources/css/filament/admin/tailwind.config.js`. `resources/css/app.css`: `@tailwind` directives → `@import "tailwindcss"`; `forms`/`typography`/`daisyui` now loaded via `@plugin`; brand colours re-exposed as utilities through an `@theme` block (`--color-brand-* : var(--brand-*)`) — the `:root` `--brand-*` hex literals are **kept verbatim** so `AdminPanelProvider::brandPrimary()`'s regex-scrape of this file still resolves; the `@apply`-based custom classes are unchanged (v4 still supports `@apply`). DaisyUI configured `themes: false` to faithfully replicate the old `themes: []` (the public site uses only DaisyUI's structural components — `card`/`btn`/`tabs`/`modal`/`join` — never its theme colour classes, so no theme palette is needed). Because TW4 auto-source-detection skips gitignored `vendor/`, added explicit `@source` directives for the Laravel pagination views and socialment login chrome. `resources/css/filament/admin/theme.css`: dropped `@config`, now `@import`s the vendor Filament v5 theme (which pulls Tailwind with `source(none)`) + `fonts.css`, and declares `@source` for `app/Filament/**/*.php` and `resources/views/filament/**/*.blade.php`; `.grey-box` utility preserved. Self-hosted Inter (`fonts.css`) and the public-site Open Sans Google-Fonts `@import` both still resolve through TW4's bundler. Build verified: brand utilities (`bg-brand-*`/`text-brand-*`/`border-brand-primary`), DaisyUI components, and `.grey-box` all present in output.
>
> **Phase 3 (2026-07-03):** All Filament PHP migrated by hand (no `filament/upgrade` codemod) against `vendor/filament` @ 5.6.8 — see the full mapping table and file list in `docs/change-logs/upgrade-l13-phase3-filament-5.md`. Covered: the 5 Resources (`form(Form)`→`form(Schema)`, layout/action namespace repoints, `->recordActions()`/`->toolbarActions()`), their Pages/RelationManagers/Concerns, the custom Pages (`SiteOptionsPage`/`SiteContentPage` now use a `content(Schema)` override; `Login` de-customised), `AdminPanelProvider` (translatable-plugin swap to `lara-zeus/spatie-translatable`), `AllTrovesTable`, `TranslatableListView`, and the `LocaleSwitcher`/`Translatable`-trait/content-driver repoints to lara-zeus. `ReadOnlySpatieLaravelTranslatableContentDriver` and `PublishedScope` needed no change. Behavioural defaults applied: `->visibility('public')` on every media upload, `->deferFilters(false)` on the Trove/RM/AllTrovesTable tables. Five now-invalid Blade page-view overrides deleted (v5 dropped `<x-filament-panels::form>` etc.; pages render via `{{ $this->content }}`).
>
> **Phase-3 boot blockers resolved (2026-07-03):** Five surfaced while walking the boot, all fixed — see the "Boot blockers resolved" section of the change log for detail. In brief: (1) widened `$navigationIcon` to `string | \BackedEnum | null` on all 5 resources + 2 custom pages (sibling statics were untouched — parent still types them `?string`); (2) widened `$maxContentWidth` to `Width | string | null` in `ListTroves`/`ViewCollection`; (3) cleared stale Filament-3 compiled Blade views; (4) `TranslatableComboField` — stopped calling container-resolving methods (`getChildComponents()`, `isDehydrated()` on a detached prototype) at schema-definition time; (5) `AllTrovesTable` — added `HasActions`/`InteractsWithActions` for v5's unified actions and resolved 5 `insteadof` collisions with `InteractsWithRecord`.
> - **Phase 0:** baseline suite green (135 passed, 1 skipped) on Laravel 11 / Filament 3 / Livewire 3; `composer.lock` + `package-lock.json` snapshotted to the session scratchpad.
> - **Phase 1:** `composer update -W` resolves clean. Installed: laravel/framework 13.18.1, filament/filament 5.6.8, livewire/livewire 4.3.3, laravel/tinker 3.0.2, awcodes/shout 4.0.1, chrisreedio/socialment 5.1.0, parallax/filament-comments 3.0.0, kainiklas/filament-scout 1.1.0, spatie/laravel-google-cloud-storage 2.4.1, collision 8.9.4, debugbar 4.3.0. `composer why-not laravel/framework ^13` empty. `laravel-shift/blueprint ^2.7` did not block — kept. **Two deviations from the matrix below:** (a) `filament/spatie-laravel-translatable-plugin` is **abandoned** (no Filament 4/5 release, tops out at 3.3.54) — replaced with its direct fork **`lara-zeus/spatie-translatable ^2.0`** (2.0.1 requires `filament/filament ^5.0` + `spatie/laravel-translatable ^6.0`, both satisfied; confirmed with user). (b) `nunomaduro/collision` pinned to **`^8.8`**, not `^9` — Pest 4 pins collision `^8.8.x`, so `^9` is unresolvable. The `filament:upgrade`/`package:discover` post-autoload hooks currently fail (`Filament\Resources\Concerns\Translatable` not found) because app code is still Filament-3 shaped — expected, resolved by Phase 3.
> - **Phase 2:** CSRF rename done — `App\Http\Middleware\VerifyCsrfToken` renamed to `PreventRequestForgery` (file `git mv`'d, extends the renamed framework base), references updated in `app/Http/Kernel.php`, `config/sanctum.php`, and `AdminPanelProvider.php` (import + panel middleware). Added `'serializable_classes' => false` to `config/cache.php`. Left `CACHE_PREFIX` default as `_cache_` (old format already hardcoded in config, so existing keys are preserved — no `.env` pin needed). MySQL-specific SQL re-verified: `whereJsonContains previous_slugs`, `BrowseAll` `JSON_EXTRACT` order-by, `EditTrove` errno-1062 check, `PurgeTelescopeEntries` `OPTIMISE TABLE` are all raw/driver idioms unaffected by L13 grammar; TrovePublisher has no `upsert`/`uniqueBy` or joined delete. All edited files pass `php -l`. Full boot/`artisan test` verification deferred to after Phase 3 (app can't boot until Filament code is migrated).
>
> **Phase 3 note:** the `SpatieLaravelTranslatablePlugin` swap now targets `lara-zeus/spatie-translatable` — its trait/plugin/content-driver namespaces differ from the old `filament/spatie-laravel-translatable-plugin`; check its docs when migrating `AdminPanelProvider`, the `Translatable` resource trait, and `ReadOnlySpatieLaravelTranslatableContentDriver`.

## Context

The app runs Laravel 11.54 (on the **legacy** Kernel-based skeleton), Filament 3.3.54, Livewire 3.8, on PHP 8.4. The goal is a single big-bang upgrade to **Laravel 13 + Filament 5 + Livewire 4** rather than incremental hops.

The forcing function is dependency reality, not preference: **Laravel 13 requires PHP 8.3+**, and every Filament plugin this app depends on gates its Laravel-13 support behind a Filament-5 release. Specifically `chrisreedio/socialment` (Azure AD login) only supports Laravel 13 on its `5.x` line — its `3.x`/`4.x` lines pin `owenvoke/blade-fontawesome ^2.4`, which caps at Laravel 12. Same pattern for `parallax/filament-comments` (L13 only on `3.0`, needs Filament 4/5), `kainiklas/filament-scout` (`1.x` needs Filament 4/5) and `awcodes/shout` (`4.x` needs Filament 5). So Laravel 13 + these plugins ⇒ Filament 5 ⇒ Livewire 4 (Filament 5 is built on Livewire 4). Filament 5 is **stable** (released Jan 2026).

**Decisions (confirmed with user):**
- **Full Tailwind v4 migration** — admin theme *and* public site (DaisyUI 4→5). Filament 4+ mandates Tailwind 4 for custom themes, and mixing TW3/TW4 in one Vite build is fragile, so the whole project moves.
- **Keep the legacy skeleton** — no migration to the slim `bootstrap/app.php` builder. Just handle the CSRF class rename in place. Bounds blast radius to framework + Filament + Livewire + CSS.

Assumed goal: app fully working (public + admin) **and** the Pest suite green, not merely booting.

## Target dependency matrix (`composer.json`)

**require** — change:
- `php` → `^8.3`
- `laravel/framework` → `^13.0`
- `laravel/tinker` → `^3.0`
- `filament/filament` → `^5.0`
- `filament/spatie-laravel-media-library-plugin` → `^5.0`
- ~~`filament/spatie-laravel-translatable-plugin` → `^5.0`~~ **REVISED:** package abandoned (no Filament 4/5 release). Removed; replaced with `lara-zeus/spatie-translatable` → `^2.0` (direct fork, requires Filament `^5.0`).
- `livewire/livewire` → `^4.0` (add explicit constraint; the app uses it directly)
- `chrisreedio/socialment` → `^5.1`
- `parallax/filament-comments` → `^3.0`
- `kainiklas/filament-scout` → `^1.1`
- `awcodes/shout` → `^4.0`
- `spatie/laravel-google-cloud-storage` → latest L13-compatible (`^2.4`+ / `^3`)

**require** — verify only (installed versions already allow illuminate `^13`, per `composer why-not`): `laravel/scout`, `laravel/sanctum`, `laravel/telescope`, `sentry/sentry-laravel`, `spatie/laravel-ignition`, `spatie/laravel-ray`, `symfony/*`, `meilisearch/meilisearch-php`, `tio/laravel`, `socialiteproviders/microsoft-azure`. Bump any that the solver rejects.

**require-dev** — change / verify: `barryvdh/laravel-debugbar` → `^3.17|^4.0` (resolved to 4.3.0), `laravel-shift/blueprint` → kept at `^2.7` (did not block resolution), `nunomaduro/collision` → **`^8.8`** (resolved to 8.9.4). **NB:** the plan's guess of `^9` is wrong — Pest 4 pins collision `^8.8.x`, so `^9` is unresolvable. `pestphp/pest ^4`, `pestphp/pest-plugin-laravel ^4`, `phpunit/phpunit ^12` already satisfy.

`owenvoke/blade-fontawesome` resolves transitively to `3.2+` via socialment `5.1` — no direct entry needed. `minimum-stability: dev` / `prefer-stable: true` can stay.

## Work phases (ordered runbook)

### Phase 0 — Safety net
- Confirm on `update-to-laravel-13`; ensure clean tree. Snapshot `composer.lock` and `package-lock`/lockfile.
- Baseline: `php artisan test` green on current versions before touching anything.

### Phase 1 — Composer resolution
- Edit `composer.json` to the matrix above.
- `composer update -W`. Iterate on solver conflicts (bump/drop the verify-only + dev packages as needed). Goal: clean install, `composer why-not laravel/framework ^13` empty.
- Run `php artisan filament:upgrade` (post-autoload hook already present).

### Phase 2 — Laravel 11→13 framework changes (small; legacy skeleton kept)
- **CSRF rename (high-impact):** `Illuminate\...\VerifyCsrfToken` → `PreventRequestForgery`. Touch 4 sites: `app/Http/Middleware/VerifyCsrfToken.php` (extend the renamed base — optionally rename the class), `app/Http/Kernel.php` web group, `config/sanctum.php:63`, `app/Providers/Filament/AdminPanelProvider.php` (import + panel middleware line 30/62). The old alias is deprecated-but-present, so this is low-risk mechanical work.
- **Cache hardening (medium):** add `'serializable_classes' => false` to `config/cache.php` (app doesn't cache PHP objects — safe). If preserving existing cache/session keys matters, pin `CACHE_PREFIX` / `SESSION_COOKIE` in `.env`/`.env.example` (L13 changed default prefix format `_cache_`→`-cache-`).
- **Carbon 3:** already installed (3.13) — no work.
- **MySQL-specific SQL** (`Trove::whereJsonContains previous_slugs`, `BrowseAll` raw `JSON_EXTRACT` order-by, `PurgeTelescopeEntries` `OPTIMISE TABLE`): unaffected by L13 grammar changes but re-verify the joined-delete / upsert `uniqueBy` notes don't bite `TrovePublisher`.
- Diff `config/*` against `laravel/laravel` 13.x for any new keys worth syncing (optional).

### Phase 3 — Filament 3→5 (largest phase)
Run the official codemods in sequence (they are code transforms, not deploys): `filament/upgrade ^4` → `vendor/bin/filament-v4`, then `filament/upgrade ^5` → `vendor/bin/filament-v5`. Then manual fixes, by risk:

- **`app/Filament/Translatable/Form/TranslatableComboField.php` — highest risk.** Custom `Field` subclass that clones child `Field`s and pulls internal traits from *both* `Filament\Forms\Components\Concerns/Contracts` and `Filament\Infolists\Components\Concerns\CanBeCollapsed`, plus `Filament\Support\Concerns\*`. Filament 4 unified Forms/Infolists/Tables under the `Filament\Schemas` architecture and relocated these concerns. Expect a real rework against the v5 Schema/`Field` API; budget the most time here. Re-verify `childField()` cloning, `formatStateUsing` translation hydration, and the `requiredWithoutAll` logic still behave.
- **Custom translatable content drivers:** `ReadOnlySpatieLaravelTranslatableContentDriver` (implements `TranslatableContentDriver`, uses the internal `Filament\Support\generate_search_column_expression`) and `TranslatableListView`. Confirm the contract + helper still exist in v5; adapt.
- **Namespace relocations** across the 5 Resources, their Pages, RelationManagers, Infolists (`ViewCollection`): the codemod moves most `Forms\Components\*` / `Tables\*` / `Infolists\*` imports; hand-verify `form(Form)`→schema signatures, `Filament\Actions\*` vs `Tables\Actions\*` split, `FiltersLayout` enum.
- **Behavioral defaults to preserve:**
  - **Media file visibility** now defaults to `private` on non-local disks (S3/GCS) — cover images/downloads will 403 unless you set `->visibility('public')` on `SpatieMediaLibraryFileUpload` / configure the media disk. Critical for this media-heavy app.
  - **Table filters deferred by default** — add `->deferFilters(false)` where instant filtering is expected (Trove/Collection tables, `AllTrovesTable`).
  - URL param renames (`tableFilters`→`filters`, `tableSort`→`sort`, `activeRelationManager`→`relation`) — update any deep links/tests.
- **Panel + plugins** (`AdminPanelProvider.php`): `SocialmentPlugin` 5.x API (`registerProvider('azure',...)`), `SpatieLaravelTranslatablePlugin` 5.x, `viteTheme`, `LocalFontProvider` (namespace may move), `brandPrimary()` `Color::hex()` (stable). filament-comments `HasFilamentComments` trait + `CommentsAction` on Trove/Collection; `Shout` component in the Trove wizard; `InteractsWithScout` trait in `TroveResource`/`ListTroves`.
- **Custom pages:** `SiteOptionsPage`, `SiteContentPage` (Page + `HasForms`, `form()` signature), `Login` (extends `Pages\Auth\Login`).
- **Blade view overrides** (`filament/pages/login`, `socialment/providers-list`, `edit-collection`, `view-collection`, `shared/forms/translatable-combo-field`): `<x-filament-panels::*>` component API + `PanelsRenderHook` constant names may have changed.
- **`app/Livewire/AllTrovesTable.php`** (Filament `HasTable`/`HasForms` Livewire component, `#[Reactive] activeLocale`, `Filament::setCurrentPanel()` in `booted()`) and the `@livewire(...)` override in `components/generics/custom-relation-managers.blade.php`.
- **`PublishedScope`** couples to `Filament::getCurrentPanel()` — confirm the facade + panel-context API is unchanged in v5.

### Phase 4 — Livewire 3→4 (front-end components)
- `config/livewire.php`: rename `layout`→`component_layout`, `lazy_placeholder`→`component_placeholder`.
- **Full-page route:** `routes/web.php:57` `Route::get('/browse-all', BrowseAll::class)` → `Route::livewire(...)` (v4-preferred).
- **`wire:model` event semantics changed.** `browse-all.blade.php` binds nested `wire:model="selectedTagsByType.{{ $id }}"` and `wire:model="selectedLanguages"` with `wire:change="search"`; v4 only listens to events on the element itself unless `.deep`. Verify filters still fire; add `.live`/`.deep` as needed. `search-bar.blade.php` uses `wire:model="query"` + `wire:keydown.enter`.
- **Pagination bridge:** `$wire.entangle('currentPage')` + `$wire.loadPage(...)` in `browse-all.blade.php` — re-verify entangle behavior under v4 (hand-rolled pagination, not `WithPagination`).
- **Events:** `BrowseAll`/`SearchBar` use old `protected $listeners = [...]`; still supported but consider migrating to `#[On]` (already used in `ViewCollection`).
- **Asset URL prefix** `/livewire/`→`/livewire-{hash}/` — update any CSP/firewall/CDN allowlist and confirm `@livewireScripts` (in `layouts/app.blade.php`, no `@livewireStyles`/`@livewireScriptConfig`; relies on `inject_assets`) still injects correctly.
- `<livewire:...>` tags are already self-closed (trove/collection views) — fine. Inert components (`CollectionTroves`, `TroveCollections`, `TroveRelatedTroves`) need only a smoke check.
- `UsesCustomSearchOptions` trait / Scout integration in `BrowseAll::search()` is framework-agnostic — no change, but re-test ranked results.

### Phase 5 — Tailwind v4 + DaisyUI 5 (whole project)
- `package.json`: `tailwindcss ^4`, add `@tailwindcss/vite`, `daisyui ^5`; update `@tailwindcss/forms`/`typography`; drop `autoprefixer`/`postcss-nesting`/`postcss.config` if TW4 makes them redundant. Bump `laravel-vite-plugin`/`vite` as needed.
- `vite.config.js`: add the `@tailwindcss/vite` plugin. Inputs stay `resources/css/app.css` + `resources/css/filament/admin/theme.css`. The vestigial Vue plugin / `alpinejs` npm dep can stay or be pruned (no JS entry point exists; Alpine ships with Livewire).
- `resources/css/app.css`: v3→v4 — `@import "tailwindcss"`, `@plugin "daisyui"`, migrate `@tailwind`/`@layer`, DaisyUI 5 config in CSS. **Keep the `--brand-*` CSS vars** (esp. `--brand-primary`, which `AdminPanelProvider::brandPrimary()` regex-scrapes from this file).
- `resources/css/filament/admin/theme.css`: v4 form — drop `@config 'tailwind.config.js'` and the `vendor/filament/.../tailwind.config.preset`; use `@source` directives per Filament 5 custom-theme docs. Preserve the `.grey-box` utility (used pervasively via `->extraAttributes(['class'=>'grey-box'])`).
- Retire/convert `resources/css/filament/admin/tailwind.config.js` (TW3 preset) to CSS-based `@theme`.
- `npm run build` must pass.

### Phase 6 — Tests + verification
- Fix the Pest suite for Livewire 4 + Filament 5 test-helper API changes. Preserve the harness gotchas from `docs/change-logs/test-suite-buildout.md`: `usePublicContext()` (PublishedScope self-disable under Filament panel context) and `bootPublicSite()` (`config('branding.locales')`).
- `php artisan scout:sync-index-settings` then re-import (`scout:import "App\Models\Trove"` / `Collection`) — Scout config unchanged but reindex after the upgrade.

## High-risk items (watch list)
1. `TranslatableComboField` rework against Filament 5 Schema API — the single biggest code risk.
2. Media visibility default → `private` on S3/GCS silently breaking public cover images/downloads.
3. Livewire 4 `wire:model` nested-binding event semantics breaking `BrowseAll` filters + the `entangle` pagination.
4. Tailwind 4 / DaisyUI 5 visual regressions across the public site.
5. `laravel-shift/blueprint` / `collision` / `debugbar` dev-dep resolution — be ready to drop blueprint if it blocks.

## Verification (end-to-end)
- `composer update -W` resolves clean; `composer why-not laravel/framework ^13` empty. `php artisan about` boots on Laravel 13 / Filament 5 / Livewire 4.
- `npm run build` passes. Visually check **public** pages (home, `/browse-all`, a trove, a collection) for TW4/DaisyUI-5 regressions.
- `/browse-all`: search box, language + tag filters (nested `wire:model`), and custom pagination all work; Meilisearch ranked results merge correctly.
- **Admin** (`/admin`): Azure login (socialment 5), each Resource CRUD, the Trove **wizard** + full draft/publish/request-review lifecycle (`TrovePublisher`), **media upload + public visibility**, translatable fields (`TranslatableComboField`), comments action, Shout callout, Scout-backed `ListTroves` tabs, and `AllTrovesTable` attach/detach on a Collection.
- `php artisan test` green (drive via `/verify` skill for the lifecycle flows).
- After merge: write `docs/change-logs/` entry per CLAUDE.md #3 and link it from the plan.
