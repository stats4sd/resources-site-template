# Change log: Make `PublishedScope` panel-aware

Implements [docs/plans/fix-published-scope-hides-drafts-in-admin.md](../plans/fix-published-scope-hides-drafts-in-admin.md), fixing code-review finding #5 in [docs/code-reviews/trove-review-update.md](../code-reviews/trove-review-update.md): the global `PublishedScope` hid unpublished troves from admin surfaces that never explicitly opted out — the "Show All Troves" collection picker and the collection's Troves relation manager both ran scoped `Trove` queries, so a never-published draft couldn't be added to a collection, and an attached trove vanished from "Troves in this Collection" the moment it was unpublished.

## What changed

### `app/Models/Scopes/PublishedScope.php`

- `apply()` now returns early — without adding the `whereNotNull('published_at')` predicate — whenever `Filament::getCurrentPanel() !== null`. `FilamentManager::getCurrentPanel()` has no fallback and is only ever set by panel route middleware or Filament-native Livewire components' boot lifecycle, so the public site, console commands, queues and Scout imports are all unaffected and keep the scope.
- Docblock updated to describe the panel self-disable alongside the existing `Trove::withDrafts()` opt-out.

### `app/Livewire/AllTrovesTable.php`

- Added a `booted()` method that calls `Filament::setCurrentPanel(Filament::getPanel('admin'))`. `AllTrovesTable` is a plain `Livewire\Component` (not Filament-native), so it loses the panel context on its `/livewire/update` search/filter/paginate requests even though its initial render happens under `/admin`; without this it would drop unpublished/draft troves mid-interaction now that the scope is panel-gated instead of query-gated.

### `TroveResource` / `TrovesRelationManager`

- No changes. `TroveResource::getEloquentQuery()` already calls `->workingVersions()`, which internally does `withoutGlobalScope(PublishedScope::class)` — left in place as it's self-documenting and also does the finding-#6 "narrow to working versions" work. `TrovesRelationManager` is Filament-native and restores the panel on every Livewire update automatically, so it now surfaces unpublished/attached-then-unpublished troves with no changes needed.

## Verification

- `php -l` clean on both edited files.
- Confirmed via `vendor/filament/filament/src/FilamentManager.php` that `getCurrentPanel()` returns `null` with no default fallback outside a panel context, so the public site (`set.locale` middleware, no panel middleware), `scout:import`, and other console/queue contexts are unaffected.
- Manual verification in the admin panel (open a collection → "Show All Troves", search/filter/paginate, confirm draft/unpublished troves stay visible across Livewire updates) is still outstanding — needs a running app + MySQL.

## Out of scope (unchanged)

Finding #6 (canonical-with-existing-draft still route-resolvable inside the panel) is a separate, coordinated piece of work — this change slightly widens what `TroveResource::getEloquentQuery()` must narrow, since canonicals-with-drafts can now resolve panel-wide with the scope off.
