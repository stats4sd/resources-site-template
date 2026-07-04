# Phase 3a sub-plan — Migrate `TranslatableComboField` to Filament 5

**Status:** In Progress — code changes done (2026-07-03), end-to-end verification still pending a booting panel. Both files migrated: `TranslatableComboField.php` (imports/traits repointed to `Filament\Schemas\Components\Concerns\*`, `CanBeCompacted`→`CanBeCompact`, header/footer-action traits + nonexistent contracts dropped, dead imports removed) and the Blade view (header/footer-action + icon-size props removed). Verified in isolation via reflection: class loads under Filament 5.6.8, parent is `Filament\Forms\Components\Field`, all 7 traits resolve with no collision, and every Blade-called accessor (`isCollapsed`/`isCompact`/`getDescription`/`getHeading`/`getIcon`/`getIconColor`/`shouldPersistCollapsed`/`getChildComponentContainer`) exists. **Update (2026-07-03):** the sibling Phase-3 items have now landed (see `docs/change-logs/upgrade-l13-phase3-filament-5.md`), but the panel still doesn't boot — a `$navigationIcon` static-property retype blocks it. Re-run the Verification section once that is fixed and the panel boots.
**Parent plan:** `docs/plans/upgrade-laravel-13-filament-5-livewire-4.md` (Phase 3, item #1 — highest-risk)
**Scope of this sub-plan:** the custom `Field` subclass `app/Filament/Translatable/Form/TranslatableComboField.php` **and** its Blade view `resources/views/filament/shared/forms/translatable-combo-field.blade.php` only. The content driver / `TranslatableListView` are separate Phase-3 items, deliberately excluded here (brief note at the end).

## Context

`TranslatableComboField` is a bespoke Filament `Field` that renders one child field per configured locale, all writing to a single JSON-translatable DB column (via Spatie `HasTranslations`). It is used pervasively — every translatable input in the admin goes through it: `TroveResource` (title, description, external_links, youtube_links, name), `CollectionResource`, `Tag`/`TagType`/`TroveType` resources + `TagsRelationManager`, and `SiteContentPage`. If it doesn't migrate cleanly, the whole admin form layer is broken.

The class was built against Filament 3's separate `Forms` / `Infolists` component trees, cherry-picking internal concerns and contracts from both. **Filament 4 unified Forms/Infolists/Tables under `Filament\Schemas`** and moved/renamed those concerns; several of the contracts it `implements` no longer exist. This is a namespace + trait rework, plus a Blade view rework for the header/footer-action props that were removed. Installed target confirmed: **filament/filament v5.6.8** (with `filament/schemas`), **lara-zeus/spatie-translatable** as the plugin.

## What breaks and why (verified against `vendor/filament` @ 5.6.8)

### 1. Base class moved, but child-component API survives
`Filament\Forms\Components\Field` still exists and is still the correct base, but it now extends `Filament\Schemas\Components\Component` (not the old `Filament\Forms\Components\Component`). The base `Component` already `use`s `HasChildComponents` and `HasHeadings`, so **`childComponents()`, `getChildComponents()`, `getChildComponentContainer()` all still exist unchanged** — the core mechanism of this class is intact.

### 2. Dead / relocated imports (the bulk of the change)
Current imports vs. Filament 5 reality:

| Current import | Status in v5 | Action |
|---|---|---|
| `Filament\Forms\Components\Component` | unused here | delete |
| `Filament\Forms\Components\Concerns` (namespace alias) | concerns moved to `Filament\Schemas\Components\Concerns` | repoint |
| `Filament\Forms\Components\Contracts` (`HasHeaderActions`, `HasFooterActions`) | **these contracts no longer exist** (grep of whole vendor = 0 hits) | drop the `implements` clause entirely |
| `Filament\Infolists\Components\Concerns\CanBeCollapsed` | gone; unified into schemas | remove (use the schemas one) |
| `Filament\Support\Concerns\HasDescription` | **moved to** `Filament\Schemas\Components\Concerns\HasDescription` | repoint |
| `Filament\Support\Concerns\HasHeading` | **moved to** `Filament\Schemas\Components\Concerns\HasHeading` | repoint |
| `Filament\Support\Concerns\HasExtraAlpineAttributes` | still in `Filament\Support\Concerns` | keep |
| `Filament\Support\Concerns\HasIcon` / `HasIconColor` | still in `Filament\Support\Concerns` | keep |
| `Placeholder`, `Section`, `SpatieMediaLibraryFileUpload`, `TextInput`, `Collection`, `Str`, `Contrast` | **never referenced in the class body** | delete (dead imports; pre-existing) |

### 3. Trait list rework
Current traits and their v5 home:
- `Concerns\CanBeCollapsed` → `Filament\Schemas\Components\Concerns\CanBeCollapsed` (provides `isCollapsed()`, `isCollapsible()`, `shouldPersistCollapsed()` — all used by the Blade).
- `Concerns\CanBeCompacted` → **renamed** `Filament\Schemas\Components\Concerns\CanBeCompact` (provides `isCompact()`). **Note the name change.**
- `Concerns\HasFooterActions` / `Concerns\HasHeaderActions` → exist at `Filament\Schemas\Components\Concerns\*`, **but no caller uses header/footer actions** (grep of all `TranslatableComboField::make(...)` chains = 0 action calls). **Drop both**, plus their now-nonexistent contracts.
- `HasDescription`, `HasHeading` → repoint to `Filament\Schemas\Components\Concerns\*`.
- `HasExtraAlpineAttributes`, `HasIcon`, `HasIconColor` → unchanged (`Filament\Support\Concerns\*`).
- The Blade also calls `$getIconSize()`. In v5 that comes from `Filament\Support\Concerns\HasIconSize` (a separate concern). Callers never set it → **drop the icon-size prop from the Blade** (simpler than adding the concern).

**Collision check passed:** `Section` (v5) composes exactly this concern set on top of `Component`; `Field` adds only `HasHint`, `CanBeValidated`, `CanBeMarkedAsRequired`, `HasLabel`, `HasName`, etc. — no method-name collision with the icon/description/heading/collapse/compact concerns. `Section.php` is the canonical reference to mirror.

### 4. Contracts clause removed
`class TranslatableComboField extends Field implements Contracts\HasHeaderActions, Contracts\HasFooterActions` → becomes just `class TranslatableComboField extends Field` (contracts deleted from framework; actions unused).

### 5. Method bodies — mostly unchanged, three things to re-verify at runtime
These APIs all still exist on the v5 `Field`/`Component`, so the logic ports as-is, but they are behavioral and must be exercised, not assumed:
- `formatStateUsing(...)` (from `HasState`) + `getTranslations()` hydration — the state-hydration seam. Confirm state still populates per-locale on edit.
- `statePath()` setter and **direct `->statePath` property read** in `makeFieldRequiredWithoutAll()`. `statePath` is now a `protected` prop on the schemas `HasState` trait. Protected cross-instance access still works because both classes descend from `Component`, but this is the most fragile line — verify it compiles and produces the *relative* path (do **not** switch to `getStatePath()`, which returns the absolute container-prefixed path and would break the `requiredWithoutAll` sibling references).
- `required()` override calling `parent::required()`, `isRequired()`, `requiredWithoutAll()`, `isDehydrated()`/`dehydrated(false)` — all present. Re-check `parent::required()` signature and that `requiredWithoutAll` with relative locale paths still fires validation in v5.
- `getHint()` (used as description fallback) — still provided by `Concerns\HasHint` on `Field`.

### 6. Blade view rework (`translatable-combo-field.blade.php`)
The v5 `<x-filament::section>` component `@props` no longer include header/footer actions. Reconcile each bound prop:
- Keep: `aside`, `collapsed`, `collapsible`, `compact`, `content-before`, `description`, `heading`, `icon`, `icon-color`, `persist-collapsed`.
- **Remove:** `:footer-actions`, `:header-actions`, `:footer-actions-alignment`, `:icon-size` — no longer props / concern not used.
- `$getChildComponentContainer()` (the actual field render) — unchanged, keep.
- `\Filament\Support\prepare_inherited_attributes(...)` helper still exists in v5.

## Verification (end-to-end)

- `php artisan about` boots on Filament 5.
- `/admin` Trove create/edit: per-locale title/description render, hydrate from existing translations, `requiredWithoutAll` validation fires when all locales empty.
- `external_links`/`youtube_links` (Repeater children) render per-locale and round-trip to JSON.
- `->extraAttributes(['class'=>'grey-box'])`, `->icon()`, `->heading()`, `->hint()`, `->columns(3)` still render.
- Smoke `SiteContentPage` + Tag/TagType/TroveType forms.
- `php artisan test` (full suite fixed in Phase 6).

## Out of scope (adjacent Phase-3 items, tracked separately)
- `ReadOnlySpatieLaravelTranslatableContentDriver` — `TranslatableContentDriver` contract + `generate_search_column_expression` helper both still exist in v5; belongs to the driver item.
- `TranslatableListView` trait — pulls `HasActiveLocaleSwitcher` / `ListRecords\Concerns\Translatable`, provenance shifts under `lara-zeus/spatie-translatable`; handle with the plugin migration.
