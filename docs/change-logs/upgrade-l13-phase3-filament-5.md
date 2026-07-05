# Phase 3 — Filament 3 → 5 code migration

**Related plan:** `docs/plans/upgrade-laravel-13-filament-5-livewire-4.md` (Phase 3), with sub-plan `docs/plans/upgrade-l13-phase3a-translatable-combo-field.md` (Phase 3a, the `TranslatableComboField` piece).
**Date:** 2026-07-03
**Branch:** `update-to-laravel-13`
**Status:** Code migration complete and PHP-lint-clean; **the panel boots and the full Pest suite passes** (135 passed, 1 pre-existing skip). The boot blockers described below have been resolved (see "Boot blockers resolved").

## What this covers

Phase 3 is the Filament 3 → 5 layer of the Laravel 11→13 / Filament 3→5 / Livewire 3→4 upgrade. The official `filament/upgrade` codemod was **not** installed, so every change here was done by hand, mapping each Filament-3 API to its Filament-5 home by grepping `vendor/filament` @ 5.6.8 for the authoritative signature before editing. Phase 3a (`TranslatableComboField` + its Blade view) was already done in a prior session; this log covers the rest of Phase 3.

The translatable plugin is `lara-zeus/spatie-translatable ^2.0` (the abandoned `filament/spatie-laravel-translatable-plugin` has no Filament 5 release — see the parent plan). Its trait/plugin/driver namespaces differ from the old first-party plugin and are the source of several of the repoints below.

## The Filament 5 mapping applied (reference)

| Filament 3 | Filament 5 | Notes |
|---|---|---|
| `Filament\Forms\Form` (class + `form(Form $form): Form`) | `Filament\Schemas\Schema` (`form(Schema $schema): Schema`) | `Filament\Forms\Form` deleted. `$schema->schema([...])` still works (alias for `components()`). |
| `Filament\Forms\Components\{Section,Grid,Fieldset,Tabs,Wizard,Group}` | `Filament\Schemas\Components\*` | Layout components moved to Schemas. Input components (`TextInput`, `Select`, `RichEditor`, `MarkdownEditor`, `Repeater`, `DatePicker`, `Hidden`, `Checkbox`, `Toggle`, `Textarea`, `SpatieMediaLibraryFileUpload`) stay in `Filament\Forms\Components\*`. |
| `Wizard\Step` | `Filament\Schemas\Components\Wizard\Step` | Resolves via the moved `Wizard` import. |
| `Filament\Forms\Get` | `Filament\Schemas\Components\Utilities\Get` | |
| `Filament\Tables\Actions\*` (`Action`, `EditAction`, `ViewAction`, `CreateAction`, `DeleteAction`, `BulkAction`, `BulkActionGroup`, `DeleteBulkAction`, `DetachAction`, `DetachBulkAction`) | `Filament\Actions\*` | The whole `Filament\Tables\Actions` namespace is gone (only `HeaderActionsPosition` remains). |
| Table `->actions([...])` / `->bulkActions([...])` | `->recordActions([...])` / `->toolbarActions([...])` | Old names survive as deprecated aliases; we moved to the canonical ones. `->headerActions([...])`, `->emptyStateActions([...])`, `->filters()`, `->filtersLayout()` unchanged. |
| `Filament\Infolists\Infolist` (`infolist(Infolist $il): Infolist`) | `Filament\Schemas\Schema` (`infolist(Schema $schema): Schema`) | |
| `Filament\Infolists\Components\{Grid,Section}` | `Filament\Schemas\Components\*` | Infolist entries (`TextEntry`, `SpatieMediaLibraryImageEntry`) stay in `Filament\Infolists\Components\*`. |
| `Filament\Infolists\Components\Actions\Action` | `Filament\Actions\Action` | |
| `Filament\Forms\Components\Actions\Action` | `Filament\Actions\Action` | (used in `SiteContentPage` section header actions) |
| `Filament\Resources\Components\Tab` | `Filament\Schemas\Components\Tabs\Tab` | `ListTroves::getTabs()`. |
| `Filament\Pages\Auth\Login` | `Filament\Auth\Pages\Login` | |
| `Filament\Resources\Concerns\Translatable` (resource trait) | `LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable` | |
| `…\Pages\{List,Edit,View}Record\Concerns\Translatable` | `LaraZeus\SpatieTranslatable\Resources\Pages\*\Concerns\Translatable` | |
| `…\RelationManagers\Concerns\Translatable` | `LaraZeus\SpatieTranslatable\Resources\RelationManagers\Concerns\Translatable` | |
| `Filament\Actions\LocaleSwitcher` | `LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher` | The core `Filament\Actions\LocaleSwitcher` no longer exists. |
| `Filament\SpatieLaravelTranslatablePlugin` | `LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin` | Panel plugin. |
| `Filament\SpatieLaravelTranslatableContentDriver` | `LaraZeus\SpatieTranslatable\SpatieTranslatableContentDriver` | Used in `AllTrovesTable`. |

**Confirmed unchanged (no edit needed):** `Filament\Support\Contracts\TranslatableContentDriver` + `Filament\Support\generate_search_column_expression` (so `ReadOnlySpatieLaravelTranslatableContentDriver` was left as-is); `Action::form()` modal-schema method; schema `getFlatFields()` / `getRawState()` / `model()`; `SpatieMediaLibraryFileUpload` still under `Filament\Forms\Components`; `RichEditor::disableToolbarButtons()`; `Filament\FontProviders\LocalFontProvider`; the Filament facade panel API (`getCurrentPanel`/`getPanel`/`setCurrentPanel`); `NavigationBuilder`/`NavigationGroup`; `SocialmentPlugin::registerProvider()`; panel `->font()`, middleware classes, `AccountWidget`; the `HasForms` contract + `InteractsWithForms` trait; `formActionsAlignment` page property.

## Files changed

**Content driver / list view**
- `app/Filament/Translatable/TranslatableListView.php` — repointed the `Translatable` list-page concern to lara-zeus; dropped the now-unused `HasActiveLocaleSwitcher` import.
- `app/Filament/Translatable/ReadOnlySpatieLaravelTranslatableContentDriver.php` — **unchanged** (contract + helper still exist).

**The five resources** (`TroveResource`, `CollectionResource`, `TagResource`, `TagTypeResource`, `TroveTypeResource`) — `form(Form)`→`form(Schema)`, layout-component and action namespace repoints, `Translatable` resource-trait repoint, `->actions()`→`->recordActions()` / `->bulkActions()`→`->toolbarActions()`.

**Pages / relation managers**
- `ListCollections`, `ListTroves` — lara-zeus `ListRecords` `Translatable` concern + `LocaleSwitcher`; `ListTroves` also `Tab`→`Schemas\Components\Tabs\Tab`.
- `ListTags`, `ListTagTypes`, `ListTroveTypes` — `LocaleSwitcher` repoint (they use `TranslatableListView` on `ManageRecords`, which extends `ListRecords`).
- `EditTagType` — page-level `Translatable` repointed to the lara-zeus `EditRecord` concern.
- `ViewCollection` — infolist `Infolist`→`Schema`, infolist layout/action repoints; dropped its Blade `$view` and replaced the old conditional template with a `content(Schema)` override (infolist + either an embedded `AllTrovesTable` Livewire component or the relation-managers component, keyed on `$showAllTroves`).
- `EditCollection`, `Login` — dropped their `$view` overrides (both were verbatim copies of Filament defaults); the custom `Login` class is now an empty subclass kept only for the `->login()` binding.
- `TrovesRelationManager`, `TagsRelationManager` — `form(Form)`→`form(Schema)`, action namespaces, `->recordActions()`/`->toolbarActions()`; `TrovesRelationManager` also lara-zeus RM `Translatable` concern.

**Custom pages** — `SiteOptionsPage`, `SiteContentPage`: `form(Form)`→`form(Schema)`, `Section`→Schemas, `Filament\Forms\Components\Actions\Action`→`Filament\Actions\Action`; dropped their Blade `$view`s and added a `content(Schema)` override that wraps the form in `Form::make([EmbeddedSchema::make('form')])->livewireSubmitHandler('save')->footer([Actions::make($this->getFormActions())])` — the v5 idiom for a custom form page (mirrors `Filament\Auth\Pages\EditProfile`).

**Panel + Livewire + scope**
- `AdminPanelProvider` — translatable plugin swap only (everything else in the provider verified unchanged).
- `AllTrovesTable` — table action namespaces + `->recordActions()`/`->toolbarActions()`, content-driver repoint, `->deferFilters(false)`. `Filament::setCurrentPanel()` in `booted()` verified still valid.
- `PublishedScope` — **unchanged**; `Filament::getCurrentPanel()` still exists.

**Behavioral defaults (Filament 5)**
- `->visibility('public')` added to every `SpatieMediaLibraryFileUpload` (Trove files + cover images, Collection cover image) — v5 defaults media to `private` on non-local disks, which would 403 public cover images/downloads.
- `->deferFilters(false)` added to the Trove table, the Collection `TrovesRelationManager` table, and `AllTrovesTable` (v5 defers filters by default; these expect instant filtering).

**Deleted Blade overrides** (no longer valid — v5 dropped `<x-filament-panels::form>`, `…::form.actions`, `…::resources.relation-managers`, and the record-page templates; everything renders through `{{ $this->content }}` now):
`resources/views/filament/pages/{login,edit-collection,view-collection,site-options,site-content}.blade.php`.

**Untouched Blade overrides:** `filament/socialment/providers-list.blade.php` (plain HTML, no Filament page-components) and `filament/shared/forms/translatable-combo-field.blade.php` (Phase 3a).

## Boot blockers resolved

Walking the boot surfaced five distinct blockers, fixed in order:

1. **`$navigationIcon` property retyping.** Filament 5 retyped the parent's `$navigationIcon` to `string | BackedEnum | null`; PHP requires an exactly-matching redeclaration. Widened `protected static ?string $navigationIcon` → `protected static string | \BackedEnum | null $navigationIcon` across all five resources and the two custom pages (`SiteOptionsPage`, `SiteContentPage`). The sibling statics (`$navigationLabel`, `$navigationGroup`, `$navigationSort`, `$modelLabel`) did **not** surface — they are still `?string` in the parent — so they were left as-is.

2. **`$maxContentWidth` property retyping.** Same class of break: parent is now `Filament\Support\Enums\Width | string | null`. Widened the declaration in `ListTroves` and `ViewCollection` and switched the value `'full'` → `\Filament\Support\Enums\Width::Full`.

3. **Stale compiled views.** `/admin/login` 500'd on a Filament-3-era compiled Blade (a `ShowPasswordAction` typed against the old `Filament\Forms\Components\Actions\Action`). `php artisan view:clear` + `cache:clear` fixed it; no code change.

4. **`TranslatableComboField` touching an uninitialized container at schema-definition time.** Two spots called methods that in Filament 5 resolve a child `Schema` (needing an initialized container that doesn't exist while the field is still being configured):
   - `required()` used `getChildComponents()` (which resolves a Schema) → switched to the raw `getDefaultChildComponents()`.
   - `childField()` called `$childField->isDehydrated()` on a detached prototype field; in v5 that reaches container-dependent visibility logic (`getKey()`) — but only for dehydrated fields, which is exactly the no-op case. Wrapped in a `try/catch` defaulting to "dehydrated".

5. **`AllTrovesTable` Livewire component missing the unified-actions plumbing.** Filament 5 merged actions into one system; table actions now need `Filament\Actions\Contracts\HasActions` + `Filament\Actions\Concerns\InteractsWithActions` (which declares `$mountedActions`). Added both. That collided with the page-oriented `InteractsWithRecord` trait on five methods (`afterActionCalled`, `getMountedActionSchemaModel`, `getDefaultActionRecord`, `getDefaultActionRecordTitle`, `getDefaultActionSuccessRedirectUrl`) — all of which are page-context in `InteractsWithRecord` (resolve to the pinned Collection or call `parent::`) and wrong for per-row Trove actions, so each was deferred to `InteractsWithActions` via `insteadof`. `InteractsWithRecord` is kept only for the `$record` property + `getRecord()`/`hasRecord()`.

Verified: `php artisan about`, `route:list`, `/admin/login` (200), public home (`/` → `/home`, 200), and `php artisan test` (135 passed, 1 skipped).

## Not in this phase

Livewire 3→4 (Phase 4), Tailwind v4 / DaisyUI 5 (Phase 5), and the Pest suite fixes + Scout reindex (Phase 6) are untouched. `npm run build` and `php artisan test` have not been run.
