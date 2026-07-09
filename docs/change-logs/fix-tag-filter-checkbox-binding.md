# Change log: fix tag-filter checkbox group binding on browse-all

Implements [docs/plans/fix-tag-filter-checkbox-binding.md](../plans/fix-tag-filter-checkbox-binding.md).

## Changes

- `app/Livewire/BrowseAll.php`
  - New private `initialiseTagFilters()`: seeds `selectedTagsByType` with an empty array per filterable tag type (`show_in_filter = true`), so Livewire's client binds the tag checkboxes in checkbox-group mode from the first render instead of single-boolean mode.
  - `mount()` calls it before `fetchInitialData()`.
  - `clearFilters()` no longer `reset()`s `selectedTagsByType` to a bare `[]`; it re-runs `initialiseTagFilters()` so the per-type keys survive clearing filters.
- `tests/Feature/Http/BrowseAllTest.php` — three new tests: per-type array initialisation on mount, re-initialisation after `clearFilters()`, and tag filtering in `search()` returning only troves tagged with the selected tags.

## Verification

- Full Pest suite: 311 passed, 1 skipped (pre-existing).
- End-to-end against the local site with a filterable tag type enabled: the initial Livewire snapshot now carries `selectedTagsByType: {"<typeId>": []}`, and replaying a checkbox click (`selectedTagsByType.<typeId> = ["<tagId>"]` + `search` call) returns HTTP 200 with filtered results — previously the click sent boolean `true` and 500ed with `count(): Argument #1 ($value) must be of type Countable|array, true given` from `whereIn('tags.id', true)`.
