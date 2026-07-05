# Regression Review — `update-to-laravel-13` vs `dev`

**Date**: 2026-07-05
**Branch**: `update-to-laravel-13`
**Reviewer**: Claude (automated multi-angle review)
**Scope**: 58 files, 3,656 insertions / 3,073 deletions — Laravel 11→13, Filament 3.2→5.6, Livewire 3→4, Tailwind 3→4 + DaisyUI 5 + Vite 8. Review restricted to regressions introduced by the upgrade; pre-existing issues (including those in `docs/issues/`) were deliberately excluded.

**Method**: Five parallel review passes (Laravel core, Filament resources/pages, custom translatable layer, Livewire 4, Tailwind/Vite), each verifying changed API usage against the *installed* vendor source rather than documentation. Full Pest suite re-run (135 passed, 1 pre-existing skip); `npm run build` re-run (passes). High-severity candidate findings were independently re-verified before inclusion.

---

## Finding 1 — HIGH: `EditTagType` corrupts translatable JSON on every save

**File**: `app/Filament/Resources/TagTypeResource/Pages/EditTagType.php:8`

The old page used Filament 3's **resource-level** concern:

```php
use Filament\Resources\Concerns\Translatable;   // static helpers only — no save override
```

The branch swapped it for lara-zeus's **page-level** concern:

```php
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
```

The page-level trait overrides `handleRecordUpdate()` (vendor: `lara-zeus/spatie-translatable/src/Resources/Pages/EditRecord/Concerns/Translatable.php:44`) and writes each translatable attribute with:

```php
$record->setTranslation($key, $this->activeLocale, $value);
```

But `TagTypeResource` builds `label` and `description` with the app's custom `TranslatableComboField`, whose form state is the **entire locale dictionary** (e.g. `['en' => 'Theme', 'fr' => 'Thème']`). Spatie's `setTranslation()` stores that value verbatim under the active locale, producing nested JSON:

```json
{"en": {"en": "Theme", "fr": "Thème"}}
```

Previously the default save path (`$record->update($data)` → Spatie's `setAttribute()` → `setTranslations()`) handled the dictionary correctly. **Every save from the Edit Tag Type admin page now corrupts both `label` and `description`.** No test covers this path, which is why the suite stays green.

This is the only page in the app using a save-overriding lara-zeus page trait (verified by grep — `EditTrove`, `EditCollection`, etc. do not).

**Fix** (one line): use the resource-level equivalent, which is the direct successor of the old import:

```php
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
```

---

## Refuted candidate — `->maxWidth()` on schema `Grid`/`Section` in `ViewCollection`

The Filament pass initially flagged `ViewCollection.php:75/81` (`->maxWidth('full')` / `->maxWidth('7xl')`) because neither `Filament\Schemas\Components\Grid` nor `Section` declares `maxWidth()`. Verified false positive: the base `Filament\Schemas\Components\Component` uses the `HasMaxWidth` concern (`vendor/filament/schemas/src/Components/Concerns/HasMaxWidth.php`), so the method exists on every schema component. No issue.

---

## Areas verified clean

### Laravel 11→13 core
- CSRF rename `VerifyCsrfToken` → `PreventRequestForgery`: base class exists in framework 13.18.1; all four reference sites updated (`Kernel`, `AdminPanelProvider`, `config/sanctum.php`, the middleware class itself); no stray references anywhere in app/config/tests.
- `config/cache.php` `'serializable_classes' => false`: safe — the app caches no PHP objects (no `Cache::put`/`remember` with non-scalars anywhere).
- Legacy `app/Http/Kernel.php` + `bootstrap/app.php` bootstrap still fully supported; `php artisan about` boots clean.
- PHP `^8.3` constraint fine on the 8.4 runtime; no CI files pin an older PHP.

### Filament 3→5 (resources, pages, panel)
- All namespace/API migrations verified against vendor 5.6.8: `Filament\Auth\Pages\Login`, `Schema`/`Wizard\Step`/`Utilities\Get`, `->recordActions()`/`->toolbarActions()`/`->deferFilters()`, `Tabs\Tab` in `ListTroves`, `content(Schema)` on `SiteContentPage`/`SiteOptionsPage`, `ViewCollection` infolist-as-schema, `emptyStateActions()`, `SpatieMediaLibraryFileUpload::visibility()`.
- The five deleted page blades are referenced by nothing; the pages render via schema APIs.
- `AllTrovesTable` trait-conflict `insteadof` resolution verified against both vendor traits.
- `AdminPanelProvider::brandColour()`: `Color::convertToOklch()` handles the 6-digit hex; missing `--brand-danger` falls back to `Color::all()['red']` as intended; the `--brand-primary` regex still matches the rewritten `app.css`.
- `kainiklas/filament-scout` 1.1 explicitly supports Filament ^5; the table-search internals it touches still exist.
- Minor (cosmetic only): `SiteContentPage.php:7–8` aliases `Filament\Actions\Action` twice as both `PageAction` and `FormAction` — works, but misleading.

### Custom translatable layer (`TranslatableComboField` rework, lara-zeus swap)
- All trait repoints (`CanBeCollapsed`, `CanBeCompact`, `HasDescription`, `HasHeading` → `Filament\Schemas\Components\Concerns`) exist in vendor; dropped `HasHeaderActions`/`HasFooterActions` contracts genuinely no longer exist; no method collisions with base `Field`.
- `required($condition)` now forwards the condition (fixes a silent old bug); `getDefaultChildComponents()` and the `isDehydrated()` try/catch are correct v5 workarounds.
- Blade view prop removals match the v5 `x-filament::section` `@props` exactly.
- Plugin swap to `LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin` correct; plugin ID matches; `LocaleSwitcher` and all List/View-page `Translatable` trait repoints verified (only `EditTagType` — Finding 1 — used a wrong-level trait).
- `ReadOnlySpatieLaravelTranslatableContentDriver`: the `TranslatableContentDriver` contract and `generate_search_column_expression` helper still exist in v5.

### Livewire 3→4
- Config key renames (`component_layout`, `component_placeholder`) match the vendor config; omitted new keys merge from vendor defaults; the customised `temporary_file_upload` block survives.
- `Route::livewire()` macro exists (`HandleRouting`); route inherits `set.locale`, and Livewire AJAX updates get locale via `SetLocaleMiddleware` in the `web` group.
- The Phase-4 "no blade changes needed" claims all verified against vendor JS: deferred `wire:model` diffs are batched into the `wire:change`/`wire:keydown.enter` commit; `$wire.entangle('currentPage')` defaults to non-live, correct for Alpine's read-only use; `protected $listeners` still supported; `@livewireScripts` deduplicates against `inject_assets`.
- `layouts/app.blade.php` dual `$slot`/`@yield('content')` mode works for both the Livewire page component and `@extends` pages.

### Tailwind 3→4 / DaisyUI 5 / Vite 8
- `npm run build` passes; both bundles produced (`app` 128 kB, Filament `theme` 617 kB).
- Everything in the deleted `tailwind.config.js` files is accounted for: brand colour utilities regenerate via `@theme`; all old content globs have `@source` equivalents (pagination + socialment vendor classes confirmed present in built CSS); the dropped Figtree `fontFamily` extension was never actually loaded or used; `vendor/filament` scanning is superseded by the v5 pre-compiled theme import.
- DaisyUI `themes: false` faithfully replicates the old `themes: []`.
- TW4 breaking-change sweep (bare `border`, `ring` colour default, `bg-/text-/ring-opacity-*`, shadow scale): no active code path regresses. `ring-opacity-5` in `components/generics/dropdown.blade.php:39` would render as a solid black ring in TW4, but that component is referenced nowhere — dead code, noted for future use.
- Vue plugin/alias removal from `vite.config.js` correct: `resources/js/` doesn't exist and nothing imports Vue.
- Filament admin font/theme wiring (`viteTheme`, self-hosted Inter, `.grey-box`) verified in built output.

---

## Conclusion

One real regression (Finding 1 — one-line fix), zero major visual regressions, and a test suite + build that both pass. The remaining owed item from the upgrade plan — a manual visual pass on the public site and admin panel — is still worth doing, but nothing found here suggests it will surface structural problems.
