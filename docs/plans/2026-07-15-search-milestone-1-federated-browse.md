# Search Milestone 1 — Federated Meilisearch search and the BrowseAll rewrite

**Status:** In Progress — Phase 1 (Tasks 1–6) complete, one commit per task on `search-review`. Pause-point manual testing done (checklist below); the one open item it surfaced — conjunctive facet counts wrongly disabling OR-within-type/type/locale filter checkboxes — is fixed as part of Task 10. Phase 2: Tasks 7, 8 and 10 complete; Task 9 deferred (see its entry). Full suite green (359 passed, 6 skipped) on SQLite, plus the Integration suite passing locally against a real Meilisearch (`MEILISEARCH_INTEGRATION=1 php artisan test --testsuite=Integration`). Implementation note: federation-level pagination uses `limit`/`offset` (the engine/SDK reject `hitsPerPage`/`page` at federation level), with `totalPages` derived from `estimatedTotalHits`.

**Review:** [docs/code-reviews/search-and-browse-review.md](../code-reviews/search-and-browse-review.md) (§3 is the design this plan implements). Decisions taken by the maintainer on 2026-07-15: keep Meilisearch and lean into it; federated multi-search; tags (`tag_ids` + searchable `tag_names`) and trove type into the index as filterable attributes; collections participate in tag filtering via aggregated member-trove tags (option a); browse-mode default sort is publication date descending; remove `Trove::themeAndTopicTags()`; faceted counts from Meilisearch on the tag/type filters; keyboard-accessible clear/pagination controls and alt text on card images.

**Goal:** Replace the search-then-filter-then-merge-in-PHP architecture of `BrowseAll` with a single federated Meilisearch request that handles text search, tag/type/language filtering, facet counts, cross-index ranking and pagination — shrinking the Livewire component to "send one request, hydrate one page of models" and making the whole render path testable on SQLite.

**Structure:** Two phases with a hard pause between them. Phase 1 is the rewrite; **stop after Phase 1 for manual testing and review** (checklist below) before starting Phase 2 (hardening: real-Meilisearch integration suite, reconciliation, Scout 11, docs).

---

## Architecture summary

- **Indexing** stays on Scout: `toSearchableArray()`/`shouldBeSearchable()`, queueing, `after_commit`, `scout:import`, `scout:sync-index-settings`. Index documents gain filterable/sortable attributes; settings (filterable/sortable/searchable attributes) are declared in `config/scout.php` `index-settings` so they are versioned and synced by the existing command.
- **Searching** bypasses Scout's builder (it has no multi-search path): a new `App\Services\Search\MeilisearchLibrarySearch` behind an `App\Contracts\SearchesLibrary` interface calls `meilisearch/meilisearch-php`'s `multiSearch()` with a `MultiSearchFederation` — one request carrying both index queries, per-query filter expressions, federation-level pagination and `facetsByIndex`/`mergeFacets`.
- **Degradation**: a `DatabaseLibrarySearch` implementation (no text ranking, no facets; DB-side filters, date order) serves installs without Meilisearch (`SCOUT_DRIVER=null`) and engine outages.
- `BrowseAll` becomes a single full-page component (SearchBar folded in), with `#[Url]` state, ~24 items per page hydrated fresh each render, and no catalogue data in Livewire state.

### Verified engine facts this design relies on

- Federated multi-search is stable since Meilisearch v1.10; `facetsByIndex`/`mergeFacets` since v1.11. Locally installed: Meilisearch 1.49, `meilisearch/meilisearch-php` 1.16.1 (has `MultiSearchFederation`), Scout 10.25.
- When `federation` is present, per-query `limit`/`offset`/`page`/`hitsPerPage`/`facets` are rejected — pagination and facets are set at the federation level.
- `sort` **is** supported inside federated queries (verified in the engine source, `search/federated/perform.rs`): each query builds its ranking rules including sort, the engine checks queries have **compatible ranking rules**, and merged hits are ordered by comparing weighted score/sort values. Consequence: browse-mode date ordering across both indexes requires the sort attribute to have the **same name in both indexes** — hence a shared `sort_date` field. If the compatibility check still rejects the pair in practice, the fallback is PHP-side date interleave for the empty-query case only (flagged for the integration tests in Phase 2).
- `mergeFacets` merges facet distributions **by attribute name** across indexes — hence identical facet attribute names in both indexes (`tag_ids`, `trove_type_ids`, `locales`).

## Global constraints

- Invoke the `laravel-php-guidelines` skill before writing any PHP. Spatie style: happy path last, no `else`, typed properties, constructor promotion, descriptive names. **No `private const`** — inline single-use literals or use private properties.
- Tests are Pest 4 on SQLite `:memory:`. Harness gotchas from CLAUDE.md apply: `usePublicContext()` to exercise public visibility, `bootPublicSite()` for the public layout. Everything Phase 1 touches must run on SQLite — no `FIELD()`, no `JSON_EXTRACT` ordering, no MySQL-only SQL anywhere in the rewritten paths.
- **PublishedScope trap** for anything that aggregates member troves at index time: `toSearchableArray()` runs in whatever context triggered the save. In an admin HTTP request the Filament panel is current, so `PublishedScope` self-disables and an ambient `->troves` read would see drafts. Every aggregation query must be explicitly constrained (`whereNotNull('published_at')->whereNull('published_id')`), never rely on the ambient scope.
- `vendor/bin/pint --dirty` before each commit. Migrations (none expected) would be `up()` only.
- After index-shape changes during development: `php artisan scout:sync-index-settings` then `scout:import` for both models.

---

## Phase 1 — Rewrite

### Task 1: Enrich the index documents and declare index settings

**Files:** `app/Models/Trove.php`, `app/Models/Collection.php`, `config/scout.php`. Tests: `tests/Unit/Search/TroveSearchableArrayTest.php`, `tests/Unit/Search/CollectionSearchableArrayTest.php` (or extend existing model tests if present).

- [ ] `Trove::toSearchableArray()` — keep the flattened multi-locale `title`/`description` (and `strip_tags`), drop the redundant `is_published`, and add:
  - `tag_ids`: `int[]` of all attached tag IDs.
  - `tag_names`: flattened unique tag names across all configured locales (searchable — makes "gender" match troves tagged *Gender*).
  - `trove_type_ids`: `int[]` with the single `trove_type_id` (array-shaped and plural-named so the facet attribute merges with the collection index).
  - `locales`: locale codes where the `title` translation is non-empty (replaces DB-side `whereLocales`, and fixes its empty-string quirk).
  - `sort_date`: `published_at` as a Unix timestamp (shared sortable attribute name across indexes).
- [ ] `Collection::toSearchableArray()` — keep flattened `title`/`description`, drop the redundant `public`, and add `tag_ids` and `trove_type_ids` **aggregated from published canonical member troves** (explicit `whereNotNull('published_at')->whereNull('published_id')` — see the PublishedScope trap above), `locales` (non-empty titles), `sort_date` (`created_at` timestamp — collections have no publish lifecycle).
- [ ] `config/scout.php` `index-settings` for both models: `filterableAttributes: [tag_ids, trove_type_ids, locales]`, `sortableAttributes: [sort_date]`, `searchableAttributes: [title, description, tag_names]` for Trove and `[title, description]` for Collection (declaring the order stops `id` being searchable and weights title above body). Keep the existing `maxTotalHits`.
- [ ] Tests: document shape for a published trove with tags across locales; collection aggregation includes published members' tags/types only (create a draft/unpublished member and assert exclusion); `locales` excludes empty-string translations; `sort_date` present.

### Task 2: `SearchesLibrary` contract, DTOs, and the two implementations

**Files:** Create `app/Contracts/SearchesLibrary.php`, `app/Services/Search/LibrarySearchRequest.php`, `app/Services/Search/LibrarySearchResult.php`, `app/Services/Search/LibraryHit.php`, `app/Services/Search/LibraryFacets.php`, `app/Services/Search/SearchUnavailableException.php`, `app/Services/Search/MeilisearchLibrarySearch.php`, `app/Services/Search/DatabaseLibrarySearch.php`; binding in `app/Providers/AppServiceProvider.php`. Delete `app/Traits/UsesCustomSearchOptions.php`. Tests: `tests/Unit/Search/MeilisearchLibrarySearchTest.php`, `tests/Feature/Search/DatabaseLibrarySearchTest.php`.

- [ ] DTOs (readonly): `LibrarySearchRequest` — `?string $query`, `array $tagIdsByType` (tagTypeId ⇒ tag IDs), `array $troveTypeIds`, `array $locales`, `int $page`, `int $perPage`. `LibrarySearchResult` — `LibraryHit[] $hits` (each: `type` ('trove'|'collection'), `int $id`, `float $score`), `int $totalHits`, `int $totalPages`, `?LibraryFacets $facets` (tagCounts, troveTypeCounts, localeCounts as id/code ⇒ count; null when the backend can't provide facets). Interface method: `search(LibrarySearchRequest $request): LibrarySearchResult`.
- [ ] `MeilisearchLibrarySearch`: build two `SearchQuery` objects (index UIDs via `(new Trove)->searchableAs()` / `(new Collection)->searchableAs()` so `SCOUT_PREFIX` is respected) and one `MultiSearchFederation` with `hitsPerPage`/`page`; call `Client::multiSearch()`. Resolve the `Meilisearch\Client` from the container if Scout registered it, else construct from `config('scout.meilisearch')`.
  - Filter compilation (same expression for both indexes): per tag type with selections, `tag_ids IN [..]` (OR within type), AND-joined across types; `trove_type_ids IN [..]`; `locales IN [..]`.
  - Facets: `facetsByIndex` requesting `tag_ids`, `trove_type_ids`, `locales` on both indexes + `mergeFacets`, mapped into `LibraryFacets` (facet keys arrive as strings — cast to int for IDs).
  - Empty/blank query: same request as placeholder search with `sort: ['sort_date:desc']` on **both** queries; non-empty query: no sort (relevance merge).
  - Hits: map `_federation`-merged list to `LibraryHit`s (hit index UID → type), read `estimatedTotalHits`/`totalPages` from the federation response.
  - Any `Throwable` from the SDK → `report()` + throw `SearchUnavailableException`.
- [ ] `DatabaseLibrarySearch` (fallback, portable SQL only): two lightweight id+date queries (published canonical troves, public collections) with DB-side filters (tags via `whereHas`, type via `where`, locales via `whereLocales`), merged and date-sorted in PHP, paginated over the merged id list. `facets` = null, `score` = 0.
- [ ] Bind in the container: `SearchesLibrary` ⇒ `MeilisearchLibrarySearch` when `config('scout.driver') === 'meilisearch'`, else `DatabaseLibrarySearch`.
- [ ] Tests: Meilisearch implementation against a mocked `Client` — assert the queries array (filters, sort presence/absence by query emptiness, index UIDs) and federation payload (pagination, facetsByIndex), and the mapping of a canned federated response (hits, totals, facet casts); `SearchUnavailableException` on client throw. Database implementation: full feature coverage of filters/ordering/pagination on SQLite.

### Task 3: Rewrite `BrowseAll`; fold in `SearchBar`

**Files:** `app/Livewire/BrowseAll.php` (rewrite). Delete `app/Livewire/SearchBar.php`, `resources/views/livewire/search-bar.blade.php`. Update `tests/Feature/Http/EmbeddedLivewireTest.php` (drop the SearchBar mount test). Tests: `tests/Feature/Http/BrowseAllTest.php` (rewrite — the SQLite blocker note goes away).

- [ ] Public state (all `#[Url]`, except where noted): `?string $query` (as `q`), `array $selectedTagsByType`, `array $selectedTroveTypes`, `array $selectedLanguages`, `int $page`. Keep the checkbox-group array-initialisation guard for `selectedTagsByType` (see the existing `initialiseTagFilters()` docblock — the fix in `docs/plans/fix-tag-filter-checkbox-binding.md` must survive the rewrite). `perPage` fixed at 24.
- [ ] Any update to query/filters resets `page` to 1 (`updated` hooks).
- [ ] `render()`: build `LibrarySearchRequest` → `SearchesLibrary::search()`; on `SearchUnavailableException`, set a `searchUnavailable` flag and re-run through `DatabaseLibrarySearch` so the page stays usable. Hydrate the page's hits: one `whereIn` per model with eager loads (`media`, `troveType` for troves; `media` for collections), reorder in PHP by hit position, map to the existing card item array shape (`troveType` stays a model so `resource-result-card` is unchanged in contract). Nothing bulky stored on public properties — items, facets and pagination data are computed per render and passed to the view.
- [ ] Remove: `fetchInitialData()`, `mergeItems()`, `searchHits()`, `loadPage()` and the manual pagination fields, the `queryUpdated`/`clearSearchInput` listeners, `UsesCustomSearchOptions`. `clearFilters()`/`clearSearch()` become simple `reset()` calls (no events).
- [ ] `getFilterTagTypesProperty()`: drop the `ISNULL`/`JSON_EXTRACT` SQL — fetch and sort in PHP (order_column nulls-last, then lowercased current-locale label; same for tags within a type, respecting `use_custom_tag_order`).
- [ ] New trove-type filter data for the sidebar: all `TroveType`s ordered by current-locale label (PHP-side).
- [ ] Facet data to the view: per-tag, per-type, per-locale counts (empty map when facets are null so the blade renders without badges in fallback mode).
- [ ] Tests (all on SQLite now): bind a fake `SearchesLibrary` recording requests — mount renders; query/filters/page map into the request correctly; page resets on filter change; hits hydrate in hit order; URL attributes round-trip (`Livewire::withQueryParams`); empty-state variants (no results with active filters vs empty library); `SearchUnavailableException` → fallback results + notice; facet counts reach the view.

### Task 4: Blade rewrite — filters with counts, accessible controls, alt text

**Files:** `resources/views/livewire/browse-all.blade.php`, `resources/views/components/resource-result-card.blade.php`, `resources/views/components/collection-result-card.blade.php`.

- [ ] Inline search input (`wire:model.live.debounce.400ms="query"`) replacing `<livewire:search-bar>`; clear control becomes a real `<button type="button" aria-label="…">` wrapping the icon (keyboard focusable).
- [ ] New "Type" filter group (same collapsible pattern as tag types) bound to `selectedTroveTypes`.
- [ ] Facet count badges next to each tag/type/language checkbox; zero-count options get a muted/disabled treatment but remain rendered (so an active selection can be unticked). No badges when facets are unavailable.
- [ ] Pagination: windowed page links (First/Prev … window … Next/Last) as real `<button>`s with `aria-current="page"` on the active page and proper `disabled` attributes; drop the Alpine `entangle` machinery — plain `wire:click` with server-side page state is sufficient now that only one page of items renders.
- [ ] Card images get `alt="{{ $item['title'] }}"` in both card components (benefits the collection/related pages that share them).
- [ ] Filter checkboxes: drop `wire:change="search"` (state changes re-render automatically now); keep `wire:key` on grid items.

### Task 5: Reindex triggers for the new index dependencies

**Files:** `app/Observers/TagObserver.php` (create; register in `AppServiceProvider` or via attribute), `app/Services/TrovePublisher.php`, `app/Livewire/AllTrovesTable.php` + `app/Filament/Resources/CollectionResource.php` (attach/detach points). Tests: `tests/Feature/Search/ReindexTriggersTest.php`.

Tags and member-trove data are now *in* the index, so changes outside the trove-save path must reindex:

- [ ] `Tag` saved/deleted → `searchable()` over its troves (chunked) and the collections containing those troves.
- [ ] Collection membership changes (attach/detach/bulk-attach in `AllTrovesTable`, any `CollectionResource` relation actions, and `TrovePublisher::copyRelations()`'s collection sync on publish) → reindex the affected collection(s).
- [ ] `TrovePublisher::publish()/unpublish()/delete()` → reindex the trove's collections (their aggregated `tag_ids`/`trove_type_ids`/membership-derived data changed). Follow the publisher's existing after-commit discipline — Scout ops must not fire inside the transaction (`after_commit => true` covers `searchable()` calls made in-transaction, but keep explicit calls after commit for clarity).
- [ ] TagType deletion/`show_in_filter` changes need no reindex (filter UI is DB-driven; documents store tag IDs regardless of type visibility).
- [ ] Tests: spy on the Scout engine (reuse the `fakeSearchEngine` pattern from the old BrowseAllTest) or fake the queue and assert `makeSearchable` dispatch for: tag rename → member troves + their collections; attach/detach → collection; publish → collections.

### Task 6: Cleanup

**Files:** `app/Models/Trove.php`, `tests/Feature/Models/TroveRelationsTest.php`.

- [ ] Remove `Trove::themeAndTopicTags()` (hardcoded `themes`/`topics` slugs — Stats4SD leftover) and its test. Verified remaining usages are only the old `BrowseAll` (rewritten in Task 3, where browse cards pass `show-tags="false"` anyway) — the collection page cards use the plain `tags` relation and are unaffected.
- [ ] Sweep for dangling references (`UsesCustomSearchOptions`, `SearchBar`), run the full suite, `pint --dirty`.

---

## ⏸ PAUSE POINT — manual testing & review before Phase 2

Phase 1 ends here. Do not start Phase 2 until the maintainer has manually tested and reviewed. Suggested checklist (local Meilisearch running, `migrate --seed` + `ExampleDataSeeder`, `scout:sync-index-settings`, `scout:import` for both models):

- [x] Browse mode (no query): troves and collections interleaved by publication/creation date descending; pagination totals correct. **Specifically verify the shared `sort_date` federated sort is accepted by the engine and orders across indexes correctly** — this is the one design point resting on source-code reading rather than documented behaviour.
- [x] Search: relevance-ordered merged results; a query matching only a tag name surfaces the tagged troves; typo tolerance works.
- [x] Filters: tag filters narrow troves **and** collections (option a); type filter works and applies to collections via aggregated member types; language filter respects non-empty translations; OR-within-type / AND-across-types semantics preserved; combined query+filters never lose filtered results regardless of catalogue size.
- [x] Facet counts: correct against seeded data, update as filters/query change, zero-count options muted. Verdict: conjunctive sibling counts did **not** feel acceptable — they wrongly `@disabled` a sibling checkbox within an OR-within-type/type/locale group whenever the current selection (or an unrelated dimension) drove the result set to zero, even though picking that sibling instead could recover results. Fixed in Task 10: disjunctive per-dimension recompute, plus checkboxes are no longer ever `disabled` (muted styling only).
- [x] URL state: filtered/paged views shareable and restore on load; back button behaves.
- [x] Livewire payload: inspect the component snapshot — no catalogue data, just scalars/arrays of the current state.
- [x] Degradation: stop Meilisearch mid-session → notice + date-ordered fallback listing with working filters; `SCOUT_DRIVER=null` install → same fallback, no errors.
- [x] Admin flows: tag rename reflected in search after queue drain; attach/detach reflected on collection results; publish/unpublish lifecycle keeps the index in step.
- [x] Accessibility: keyboard-only pass over search, clear, checkboxes, pagination; screen-reader labels present.

Record findings; adjust scope of Phase 2 accordingly.

---

## Phase 2 — Hardening

### Task 7: Integration test suite against real Meilisearch

**Files:** `phpunit.xml` (new `Integration` testsuite), `tests/Integration/Search/*`, optional `.github/workflows/ci.yml`.

- [x] Env-gated suite (`tests/Integration/Search/RealMeilisearchTest.php`; skip unless `MEILISEARCH_INTEGRATION=1`; unique `scout.prefix` per run so it never collides with a dev/CI index; indexes deleted in `afterEach`). Verified locally against a real Meilisearch instance (`MEILISEARCH_INTEGRATION=1 php artisan test --testsuite=Integration` — 5 passed; plain `php artisan test` shows them skipped). Covers what mocks cannot: `scout:sync-index-settings` applies the declared filterable/sortable/searchable attributes; a federated request returns merged ranked hits with correct pagination across both indexes; tag/type filters plus **the Task 10 disjunctive facet counts** against real seeded documents (deliberately fixtured so conjunctive-vs-disjunctive counts diverge, proving the recompute against the live engine, not just the mock); **the shared `sort_date` sorted federation merge** across troves and collections (locks the Phase-1 pause-point verification into CI); `tag_names` searchability (a tag-name-only query surfaces the tagged trove).
- [x] GitHub Actions workflow (`.github/workflows/ci.yml`): a `test` job runs Unit+Feature on SQLite on every push/PR; a separate `integration` job runs the Integration suite against a `getmeili/meilisearch` service container with `MEILISEARCH_INTEGRATION=1`. Not exercised in CI itself (no workflow run available from this session) — review before relying on it.

### Task 8: Index reconciliation + ops documentation

**Files:** `app/Console/Kernel.php` (schedule), `README.md`.

- [x] Scheduled nightly `scout:import` for `App\Models\Trove` and `App\Models\Collection` (`app/Console/Kernel.php::schedule()`), gated behind `config('scout.driver') === 'meilisearch'` so it's a no-op when search is disabled — closes the silent-drift gap (engine unreachable at publish time) with the simplest correct mechanism at this catalogue scale. Covered by `tests/Feature/Console/ScheduleTest.php` (both commands registered; the `when()` gate passes/fails on driver config).
- [x] README/deploy docs (`README.md`, "In production" paragraph under the Meilisearch section): degraded listing without Meilisearch; first-deploy and post-schema-change steps (`sync-index-settings` + `import`); `SCOUT_QUEUE=true` + queue worker; scheduler requirement (cron `schedule:run` or `schedule:work`) for the new nightly reconciliation import.

### Task 9: Scout 11 upgrade — **deferred**

- [x] Checked `kainiklas/filament-scout` (installed v1.1.0): its `composer.json` requires `"laravel/scout": "^10.5"`, so `composer require laravel/scout:^11` would conflict without forking the package. Per the task's own escape hatch, deferring rather than forking — nothing else in Milestone 1 needs Scout 11. Revisit if/when `filament-scout` ships a Scout-11-compatible release.

### Task 10: disjunctive facet counts (no longer optional — required to fix the checkbox-disabling bug below)

**Context:** `MeilisearchLibrarySearch::mapFacets()` (`app/Services/Search/MeilisearchLibrarySearch.php:134`) currently reads counts straight off the single federated response, i.e. **conjunctively** — every filter dimension's own selection is baked into its own sibling counts. Two symptoms follow directly from this, both observed in `browse-all.blade.php`'s `$tagMuted`/`$troveTypeMuted`/`$localeMuted` (lines ~86, ~115, ~148): (1) ticking one tag in a type drives that type's *other* tag counts toward 0 even though picking a sibling instead (OR-within-type) would give a different, often non-zero, result — the checkbox then gets wrongly `@disabled`; (2) once any dimension's active filters collectively produce zero hits, every dimension's counts collapse to 0 in lockstep, disabling everything even where an OR-relaxation within a single type would recover results. The fix is to make counts disjunctive: for each dimension currently narrowing the result set, recompute that dimension's own counts with **only that dimension's own filter removed**, keeping every other dimension's filter applied — "if I added this option to my current selection, how many hits."

- [x] `LibraryFacets` (`app/Services/Search/LibraryFacets.php`) shape is unchanged (`tagCounts`/`troveTypeCounts`/`localeCounts`), but the *meaning* of each becomes disjunctive-per-dimension rather than one shared conjunctive snapshot.
- [x] `MeilisearchLibrarySearch::search()`: after the main federated request, determine which dimensions have an active selection — each tag type present (non-empty) in `tagIdsByType`, `troveTypeIds` non-empty, `locales` non-empty — and for each, build a second query pair (trove index + collection index) using `compileFilters()` with that one dimension's own filter clause omitted (everything else kept), `limit: 0`, `facets: [the one attribute for that dimension]`. Batch every dimension's query pair needing recompute into a single extra `multiSearch()` call (no `MultiSearchFederation` needed here — nothing needs cross-index hit ranking, only facet distributions), merging each attribute's counts across the two per-index responses in PHP (sum by key; simpler than `mergeFacets`, which requires a federation). Dimensions with no active selection keep their counts straight from the main federated response (already correct, since that dimension's own filter isn't applied there). Zero extra requests when no filter is active at all.
- [x] `mapFacets()` / a new merge step assembles the final `LibraryFacets` from: the main federated `facetDistribution` for un-selected dimensions, overlaid with the recomputed disjunctive counts for selected dimensions.
- [x] Blade (`resources/views/livewire/browse-all.blade.php`): once counts are disjunctive, also stop actually `@disabled`-ing the checkboxes — keep the muted text/badge styling as an accurate hint ("selecting this now would add 0 results given your other filters"), but leave the input clickable. Rationale carried over from the original bug report: even a genuinely-accurate 0 can change once the user *also* changes a different filter afterward, and a disabled control blocks that multi-step path. So this task fixes both the miscomputed-count bug and removes `disabled` as a UX decision, rather than leaving a hard block on top of now-correct numbers.
- [x] `DatabaseLibrarySearch` fallback: no change — `facets` stays `null`, so `facetsAvailable` is `false` and the blade already skips badges/muting entirely in that path.
- [x] Tests: extended `tests/Unit/Search/MeilisearchLibrarySearchTest.php` with cases — one tag type selected (its own sibling counts reflect the OR-relaxed query; other tag types/trove-type/locale counts still reflect the full conjunctive filter set); trove type selected; locale selected; multiple dimensions selected simultaneously (each recomputed independently, batched into one extra call — the mocked client receives exactly one additional `multiSearch()` invocation regardless of how many dimensions need it); no filters active (no extra request at all — existing tests double as this regression check). Feature-level: `tests/Feature/Http/BrowseAllTest.php` asserts a tag/type/locale checkbox with a 0 facet count renders without a `disabled` attribute (confirmed RED against the old `@disabled` blade markup before the fix, then GREEN after).

---

## Out of scope (tracked elsewhere)

Milestone 2 (document/transcript content search — chunk index, extraction pipeline) and Milestone 3 (hybrid semantic search, grounded Q&A) per the review document. SEO/meta-tag work, sort-option UI beyond the date default, and the zero-interactivity Livewire components (`CollectionTroves` etc.) from the template review are not part of this plan.
