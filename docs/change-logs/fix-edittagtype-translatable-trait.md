# Fix: EditTagType translatable trait corruption

Identified during the regression review at `docs/code-reviews/upgrade-l13-filament5-livewire4-regression-review.md`.

## What was broken

`app/Filament/Resources/TagTypeResource/Pages/EditTagType.php` was using the lara-zeus **page-level** edit concern:

```php
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
```

This trait overrides `handleRecordUpdate()`, which calls `$record->setTranslation($key, $activeLocale, $value)` for each translatable attribute. `TagTypeResource` uses the app's custom `TranslatableComboField` for `label` and `description`, whose form state is the entire locale dictionary (e.g. `['en' => 'Theme', 'fr' => 'Thème']`). Passing that dictionary as the value to `setTranslation()` produced nested JSON in the database column:

```json
{"en": {"en": "Theme", "fr": "Thème"}}
```

Every save from the Edit Tag Type admin page silently corrupted both translatable columns.

The old Filament 3 code used `Filament\Resources\Concerns\Translatable` — a **resource-level** trait that provides only static helper methods (`getTranslatableLocales()`, `getTranslatableAttributes()`, etc.) without overriding the save path. The default `$record->update($data)` path went through Spatie's `setAttribute()` → `setTranslations()`, which handled the locale dictionary correctly.

## Fix

One-line import swap to the lara-zeus resource-level equivalent:

```php
// Before
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

// After
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
```

## Test coverage added

A regression guard was added to `tests/Feature/Filament/TaxonomyTest.php`: *"it keeps translatable JSON flat per-locale when saving the edit form"*. This test fills both `label` and `description` with a two-locale dict, saves, and asserts `getTranslations()` returns the exact dict rather than nested structure. It was confirmed to fail against the broken trait and pass with the fix.

## Affected files

- `app/Filament/Resources/TagTypeResource/Pages/EditTagType.php` — import changed
- `tests/Feature/Filament/TaxonomyTest.php` — regression test added
