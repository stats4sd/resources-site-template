# Fix: tag-type filter checkboxes select the whole group and crash search

**Status:** Completed — see [change log](../change-logs/fix-tag-filter-checkbox-binding.md).

## Bug

On `/browse-all`, when any tag types have `show_in_filter` enabled, clicking a single tag checkbox visually checks every checkbox in that tag type's group, and the subsequent Livewire update request fails (reported as a 419/CORS-looking error in the browser; reproduces locally as an HTTP 500).

## Root cause

The checkboxes bind to a nested key — `wire:model="selectedTagsByType.{{ $filterTagType->id }}"` — but `BrowseAll::$selectedTagsByType` is initialised as an empty array with no key per tag type, so the bound value is `undefined` at click time.

Livewire's (Alpine's) checkbox binding only treats a checkbox as part of a group when the bound value is already an array. With an undefined value it falls back to single-boolean mode:

1. A click sets the whole key to boolean `true`; the client then re-binds every checkbox sharing that `wire:model` with `el.checked = !!value`, checking the entire group instantly.
2. `wire:change="search"` sends `selectedTagsByType.{id} = true` to the server, and `search()` executes `whereIn('tags.id', true)`, throwing `TypeError: count(): Argument #1 ($value) must be of type Countable|array, true given`.

Confirmed by replaying the exact Livewire update request against the local site: boolean payload → 500 with that TypeError; the same request with an array value → 200 with correctly filtered results. (The language filter never had this problem because `selectedLanguages` is a top-level array.)

## Fix

Guarantee each filterable tag type's key exists as an array before first render:

1. Add a private `initialiseTagFilters()` to `BrowseAll` that sets `selectedTagsByType` to `[tagTypeId => []]` for every `TagType` with `show_in_filter = true`, using a plain `pluck('id')` query (SQLite-safe, unlike `getFilterTagTypesProperty()`'s MySQL-only SQL, so tests can call `mount()`).
2. Call it from `mount()` and from `clearFilters()` (which otherwise resets the property to `[]` and reintroduces the bug after clearing filters).
3. Tests: key-per-filterable-type after mount, keys survive `clearFilters()`, and tag filtering via `search()` returns only tagged troves.

No blade changes; no architectural changes to the search/filter page.
