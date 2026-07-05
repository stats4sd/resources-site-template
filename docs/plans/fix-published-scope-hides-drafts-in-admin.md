# Plan: Make `PublishedScope` opt out automatically inside the Filament admin panel

**Status:** Completed. See [docs/change-logs/fix-published-scope-hides-drafts-in-admin.md](../change-logs/fix-published-scope-hides-drafts-in-admin.md).

Fixes code-review finding #5 ([docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md)): the global `PublishedScope` hides unpublished troves from admin surfaces that never explicitly opt out. Only [TroveResource::getEloquentQuery()](../../app/Filament/Resources/TroveResource.php#L50) removes the scope. The "Show All Troves" collection picker ([AllTrovesTable:60](../../app/Livewire/AllTrovesTable.php#L60), `->query(fn () => Trove::query())`) and the collection's Troves relation manager ([TrovesRelationManager:30](../../app/Filament/Resources/CollectionResource/RelationManagers/TrovesRelationManager.php#L30)) both run scoped `Trove` queries.

**Failure scenario**: A never-published draft trove can't be found in the picker, so it can't be added to a collection. An attached trove that is later unpublished vanishes from the "Troves in this Collection" table, so an admin can no longer see it or use the `DetachAction` — even though the pivot row still exists.

## Decision (confirmed by prior discussion)

Rather than sprinkle `->withoutGlobalScope(PublishedScope::class)` at each admin query (easy to forget again — this finding is the direct result of that pattern), make the scope **panel-aware**: it disables itself whenever a Filament panel is the current request context. This makes "admin sees every version" the default across the whole panel.

The one wrinkle is that panel context is only reliably present on Filament-*native* Livewire components, so the plain `AllTrovesTable` Livewire component needs a small extra step to participate. See caveat below.

## The mechanism

`Filament\Facades\Filament::getCurrentPanel()` returns `null` unless a panel has been explicitly set for the request — only panel routes (via `SetUpPanel` middleware) and Filament Livewire components call `setCurrentPanel()`. Confirmed in [vendor FilamentManager.php:108](../../vendor/filament/filament/src/FilamentManager.php#L108) — it returns the stored `$currentPanel` with no default fallback. So the public site, Scout imports, queues and console all see `null` and keep the scope; only in-panel requests bail.

```php
// app/Models/Scopes/PublishedScope.php
use Filament\Facades\Filament;

public function apply(Builder $builder, Model $model): void
{
    if (Filament::getCurrentPanel() !== null) {
        return; // admin manages every version; public visibility handled elsewhere
    }

    $builder->whereNotNull($model->getTable() . '.published_at');
}
```

## The caveat: plain Livewire components lose panel context on AJAX updates

The panel is only re-established on subsequent Livewire `/livewire/update` requests for **Filament-native** components (resource pages, relation managers), which restore it in their boot lifecycle. So:

- **`TroveResource`** — already opts out; unaffected either way. ✅
- **`TrovesRelationManager`** — a Filament component; it restores the panel on every `/livewire/update`, so the panel check works on search/filter/paginate. ✅
- **`AllTrovesTable`** — a *plain* `Livewire\Component` (implements `HasTable`/`HasForms` but is not a Filament page) embedded in `ViewCollection`. Its **initial** render runs under `/admin` (panel set → works), but its **subsequent** search/filter/paginate AJAX calls hit `/livewire/update` with no panel set → the scope re-applies and rows vanish mid-interaction. ❌

Fix `AllTrovesTable` by pinning the current panel in its boot lifecycle so it behaves like a Filament-native component:

```php
// app/Livewire/AllTrovesTable.php
public function booted(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
}
```

(`AllTrovesTable` already references `CollectionResource` and uses `InteractsWithRecord`, so it is firmly a panel component in spirit; this just makes the request context match.)

## Implementation steps

1. **`app/Models/Scopes/PublishedScope.php`** — add the `Filament::getCurrentPanel()` early-return guard shown above. Update the class docblock: the scope now also self-disables inside any Filament panel, not only via explicit `withDrafts()` / `withoutGlobalScope()`.
2. **`app/Livewire/AllTrovesTable.php`** — add the `booted()` method pinning the admin panel. Import `Filament\Facades\Filament`.
3. **`app/Filament/Resources/TroveResource.php`** — the explicit `->withoutGlobalScope(PublishedScope::class)` in `getEloquentQuery()` becomes redundant. Leave it (harmless, self-documenting) **or** remove it and rely on the guard. Recommendation: **leave it**, because finding #6's separate "narrow to working versions" work also lives in `getEloquentQuery()` and that override is staying anyway.
4. **`TrovesRelationManager`** — no change needed; verify during testing that unpublished/attached-then-unpublished troves now appear.

## Things to double-check (don't skip)

- **Public site regression**: confirm `Trove::query()` on a normal web route (`/resources/...`, `BrowseAll`) still filters to published rows — i.e. `getCurrentPanel()` is `null` there. The web routes run under `set.locale`, not any panel middleware, so this should hold; verify explicitly.
- **Scout import**: `scout:import "App\Models\Trove"` runs in console → panel `null` → scope applies → only published canonicals indexed, unchanged. Confirm.
- **Global search / record resolution** inside the panel: with the scope off panel-wide, canonicals-with-drafts can now resolve on the edit route. This is exactly the surface finding #6 warns about — coordinate with that plan; this change slightly widens what `getEloquentQuery()` must then narrow.
- **Other models**: `PublishedScope` is currently only added to `Trove` ([Trove.php:71](../../app/Models/Trove.php#L71)). If it is ever attached to another model, the panel guard applies there too — intended, but note it.

## Testing

- Feature test: create a never-published draft trove; assert it is returned by a query executed with the admin panel set as current (`Filament::setCurrentPanel(...)`) and *not* returned on a plain web-context query.
- Feature test: attach a published trove to a collection, then unpublish it; assert it still appears in the collection's Troves relation manager query and that `DetachAction` remains reachable.
- Manual: in the admin panel, open a collection → "Show All Troves", search/filter/paginate, and confirm draft/unpublished troves stay visible across Livewire updates (this exercises the `AllTrovesTable` panel-pinning fix specifically).
