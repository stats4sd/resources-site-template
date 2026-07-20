# Search & Browse Review — Scout/Meilisearch architecture, BrowseAll, and the road to content-level and semantic search

**Date**: 2026-07-15
**Branch**: `search-review`
**Reviewer**: Claude (focused feature review)
**Scope**: The search/filter/browse stack only — `BrowseAll` + `SearchBar` Livewire components, `UsesCustomSearchOptions`, Scout configuration and index documents (`Trove::toSearchableArray()`, `Collection::toSearchableArray()`), and the Meilisearch integration. Includes a tooling decision review and a three-milestone roadmap towards content-level search and LLM-assisted querying. Tooling facts (versions, API capabilities, pricing) verified against official docs July 2026.

---

## Verdict summary

The current design inverts where the work should happen: Meilisearch is used only as a "which IDs match this text" oracle (capped at 500 hits), and everything else — tag filtering, language filtering, cross-model merging, ranking, pagination — is reimplemented in Eloquent and PHP over the entire catalogue held in Livewire state. Every structural problem in this stack (the 500-hit truncation interacting with filters, no facet counts, tag names being unsearchable, collections ignoring tag filters, MySQL-only SQL blocking tests, the whole-catalogue Livewire snapshot) is a direct consequence of that inversion.

Meilisearch itself is the right engine and should stay — verified against the July 2026 state of the ecosystem, it is the only self-hostable option with first-party Scout support, stable hybrid/vector search (v1.13+), and **federated multi-search** (v1.10+), which is the engine-native replacement for exactly the manual Trove+Collection merge `BrowseAll` does by hand. The correct move is not to abstract away from Meilisearch but to commit to it harder: push filtering, faceting, merging and pagination into one federated query, keep Scout for the indexing lifecycle, and shrink `BrowseAll` to "send one search request, hydrate one page of models". That rewrite is Milestone 1 and is also the prerequisite that makes Milestones 2 (document/transcript content search) and 3 (semantic search, grounded Q&A) cheap increments instead of rewrites — both slot into the same federated query as an additional chunk index.

---

## 1. Review of the current implementation

### 1.1 The core inversion: search first, filter later, merge in PHP

[BrowseAll::search()](app/Livewire/BrowseAll.php#L86-L143) runs two independent Scout searches (`searchHits()` per model, raw hits with `showRankingScore`), then rebuilds the result DB-side: `whereIn('id', $ids)` + `orderByRaw('FIELD(id, …)')`, tag filters as `whereHas('tags')`, language as `whereLocales('title', …)`, then [mergeItems()](app/Livewire/BrowseAll.php#L164-L206) zips the two Eloquent result sets back together with their ranking scores and sorts in PHP. Consequences, in decreasing severity:

1. **Filter interaction with the hit cap silently drops results.** [UsesCustomSearchOptions](app/Traits/UsesCustomSearchOptions.php#L10) requests `hitsPerPage = config('scout.scout_search_limit', 500)` — a config key that does not exist in [config/scout.php](config/scout.php), so the cap is a hard 500 (page 1 only, even though `maxTotalHits` is set to 1000). Meilisearch ranks those 500 by *text relevance alone*; the tag/language filters then intersect DB-side. On a catalogue where a broad query matches more than 500 documents, a trove that matches the user's tag filter but sits at relevance rank 501 is silently absent — the user sees "no results match your filters" for content that exists. Filters must be inside the engine query for the cap to be harmless.
2. **Tag filters are not applied to collections at all.** The collection branch of `search()` ([BrowseAll.php:120-139](app/Livewire/BrowseAll.php#L120-L139)) applies only the language filter. Select any tag and the result grid still contains every query-matching collection regardless of tags — the trove list narrows and the collection list doesn't. This is a live behaviour bug, not just a design smell.
3. **Tag names are not searchable.** The index documents are title+description only ([Trove::toSearchableArray()](app/Models/Trove.php#L380-L408)), so searching "gender" finds nothing tagged *Gender* unless the word happens to appear in a title or description. This is the flip side of the facet gap: bringing tags into the index fixes both.
4. **No facet counts, no ranking influence from tags.** Because the engine never sees tags, the UI cannot show per-tag result counts, disable empty filters, or rank by tag-match strength — the "more interesting things" this review was asked to enable.
5. **Cross-index merging is hand-rolled.** `_rankingScore` is normalised per query so the naive merge is roughly sane, but there is no weighting, no tie policy, and in browse mode (empty query) every score is 0 so `sortByDesc('score')` degrades to insertion order — all troves in DB order, then all collections. There are no sort options at all (the earlier template review flagged this too). Meilisearch's federated multi-search does the score-normalised merge, per-query weighting, and merged pagination natively.

### 1.2 State and rendering (confirms template review §2.2, with specifics)

- **The entire catalogue lives in Livewire state.** `$resources`, `$collections`, `$items`, `$renderedItems` are all public properties ([BrowseAll.php:19-43](app/Livewire/BrowseAll.php#L19-L43)). The Eloquent collections are re-queried from the DB on every Livewire hydration; the mapped `$items`/`$renderedItems` support collections — including full `troveType` models, tag collections and media URLs per item — are serialised into the component snapshot shipped to the browser on every interaction. With `$perPage = 100`, a single page renders 100 cards.
- **N+1 on media.** `fetchInitialData()` eager-loads `troveType` and `themeAndTopicTags` but not `media`, so `cover_image_thumb` fires a media query per trove; collections eager-load nothing. `search()` eager-loads nothing at all (`Trove::query()` without `with()`), so a post-search render is N+1 on troveType and tags too.
- **Two divergent code paths for the same page**: empty query goes through [fetchInitialData()](app/Livewire/BrowseAll.php#L71-L76) (full-table `get()` of every published trove and public collection), any query goes through `search()`. Meilisearch handles empty-query "placeholder search" with filters, facets and sorting natively — one path can serve both browse and search.
- **Pagination is reimplemented** as `skip`/`take` over the in-memory collection with an `@for(1..pageCount)` full button range ([browse-all.blade.php:161-168](resources/views/livewire/browse-all.blade.php#L161-L168)) — no windowing, and a 20-page catalogue renders 20 buttons.
- **SearchBar↔BrowseAll event ping-pong** persists: `queryUpdated` dispatched from [SearchBar::search()](app/Livewire/SearchBar.php#L17-L20), listened by BrowseAll, which is also *re-listened* by SearchBar to sync back, plus a `clearSearchInput` event in the reverse direction. Search fires only on Enter ([search-bar.blade.php:6](resources/views/livewire/search-bar.blade.php#L6)). A single component with `wire:model.live.debounce` removes the second component, both events, and the Enter-only limitation.
- **No URL state** — no `#[Url]` on query/filters/page; filtered views cannot be bookmarked, shared or crawled and the back button loses state (template review §1.3).

### 1.3 Portability and testability

- **MySQL-only SQL in the hot path**: `FIELD(id, …)` ordering in `search()` ([BrowseAll.php:100](app/Livewire/BrowseAll.php#L100), [129](app/Livewire/BrowseAll.php#L129)) and `ISNULL`/`JSON_EXTRACT`/`JSON_UNQUOTE` ordering in [getFilterTagTypesProperty()](app/Livewire/BrowseAll.php#L223-L243). This is precisely why [BrowseAllTest](tests/Feature/Http/BrowseAllTest.php#L10-L14) documents that the component's render path is untestable on the SQLite harness — the least-tested surface of the app is the most public one. Hit ordering belongs in PHP (`sortBy` over the hit-ID positions); the tag-type ordering is a small enough dataset to sort in PHP too.
- **Hardcoded taxonomy slugs in a white-label template**: [Trove::themeAndTopicTags()](app/Models/Trove.php#L410-L415) filters card tags to tag types with slugs `themes`/`topics` — Stats4SD leftovers. A fork whose tag types are named differently gets tagless cards while the filters (driven by `show_in_filter`) work fine. The tag types shown on cards should be flag-driven like the filters (e.g. a `show_on_card` flag or reuse `show_in_filter`).

### 1.4 Index documents and settings

- Documents carry `title`+`description` (all locales concatenated, deduped, tags stripped), `id`, and a redundant `is_published`/`public` flag that `shouldBeSearchable()` already guarantees is 1. Nothing filterable, sortable or facetable is indexed.
- **No index settings beyond `maxTotalHits`** in [config/scout.php](config/scout.php#L139-L150): no `filterableAttributes`, `sortableAttributes`, or `searchableAttributes`. With no `searchableAttributes` declared, every field is searchable with equal-ish weight — including `id`, so numeric queries can match record IDs.
- **The locale-flattening approach is fine to keep** for title/description: it gives cross-language recall (an English query surfaces a French-only resource, which is right for a small multilingual library) at the cost of not being able to rank per-locale. What is missing is a `locales` array attribute (locale codes where the title is non-empty) as a filterable field — which would also replace the DB-side `whereLocales()` and fix its quirk of counting a locale as "available" when the translation key exists but may be empty.
- Two fixes from the July-7 template review are confirmed in place on this branch: a zero-hit query now returns nothing (`whereRaw('1 = 0')`) rather than the whole library, and `searchHits()` catches engine outages into a `searchUnavailable` notice instead of a 500.

### 1.5 Indexing lifecycle (mostly sound, one gap)

`shouldBeSearchable()` on both models is correct (published canonicals only; public collections only), `after_commit => true` is set, and the publish lifecycle flows through `TrovePublisher` saves so Scout picks up transitions. The gap: **tag renames and tag attach/detach outside the publish flow never reindex**. A `Tag` rename should cascade `searchable()` over its troves once tags are indexed (Milestone 1 introduces this dependency — it does not exist today only because tags aren't indexed). The absent index-reconciliation job (template review §1.5) also becomes more important as the index grows richer; a scheduled `scout:import` or a checksum-based reconcile command should ship with Milestone 1.

---

## 2. Tooling decision

### 2.1 Do we still recommend Meilisearch? Yes — and more strongly than before

Verified current state (July 2026): Meilisearch v1.49 stable; hybrid/vector search **stable since v1.13** (Feb 2025, on by default); **federated multi-search stable since v1.10** with cross-index facet merging (`facetsByIndex`/`mergeFacets`) since v1.11, and hybrid queries are allowed inside federated requests. The locally installed stack (Meilisearch 1.49 binary, `meilisearch/meilisearch-php` 1.16.1, Scout 10.25) already supports everything Milestones 1–3 need.

The alternatives, briefly:

- **Typesense** (first-party Scout engine since Scout 10.7): viable and self-hostable, with built-in vector search — but it requires explicit typed schemas per collection (awkward with the flattened-locale documents and runtime-configured locales), its multilingual typo tolerance is weaker, and it has no equivalent of federated multi-search for the Trove+Collection merged ranking. Nothing gained for this app.
- **Scout `database` driver**: Laravel 13's docs now promote it for simple cases, but it has no typo tolerance, no facets, no ranking scores and no cross-model merged ranking — it cannot power this page's UX. It is a fallback tier, not an alternative.
- **Algolia**: SaaS-only and usage-metered (~$0.50–0.75/1k searches beyond free tier) — wrong shape for a white-label template whose deployers want a predictable self-hosted stack.

**Decision**: Meilisearch is the engine, stated as a **requirement for the full browse/search UX**, not merely a default. The template keeps `SCOUT_DRIVER=null` as the safe install-time default, but the README/deploy docs should say plainly: the library page runs on Meilisearch; without it you get a degraded browse-only listing.

### 2.2 What "Scout-compatible" should mean here

Chasing engine-agnosticism costs exactly the features this review wants (facets, federation, hybrid search — none exposed through Scout's generic `Builder`). The honest split:

- **Keep Scout for the indexing lifecycle**: model observers, `shouldBeSearchable()`, `toSearchableArray()`, queueing, `after_commit`, `scout:import`, and `scout:sync-index-settings` (which pushes arbitrary settings fields from `config/scout.php` `index-settings`, so `filterableAttributes`/`sortableAttributes`/`searchableAttributes` — and later `embedders` — live in config, versioned).
- **Bypass Scout for the search request**: Scout's `MeilisearchEngine` has no multi-search/federation path (verified against the 11.x source). Introduce a dedicated app service — e.g. `App\Services\LibrarySearch` — that calls `meilisearch/meilisearch-php`'s `multiSearch()` with a `MultiSearchFederation` directly, reusing Scout's index-name/prefix helpers so the two layers can't drift. `UsesCustomSearchOptions` and its phantom config key are deleted.
- **Pluggability for forks**: put the service behind a small app interface (`SearchesLibrary`: takes query + filters + page, returns hit descriptors + facet distributions). A fork that genuinely can't run Meilisearch implements a DB-backed version with reduced features (no facets, LIKE matching). This is honest pluggability — swap the implementation, not pretend Scout makes engines equivalent.

Also worth doing in passing: upgrade Scout 10.25 → 11.x (minor breaking changes only; brings comparison operators in `where()`).

### 2.3 Degradation stance

Keep the existing outage handling and extend it: when the engine is unreachable, fall back to a plain DB listing (published troves + public collections, newest first, tag/language filters still applied DB-side, no text ranking) with the `searchUnavailable` notice — rather than an empty page. This keeps the site usable through Meilisearch restarts and honestly covers the `SCOUT_DRIVER=null` install.

---

## 3. Milestone 1 — Refactor search/filter/browse onto the engine

The target shape, end to end:

1. **One federated request per interaction.** `POST /multi-search` with a `federation` block: merged pagination (`hitsPerPage` ~24, real page numbers), one query per index (`troves`, `collections`), per-query `filter` expressions, `facetsByIndex` for tag/language counts. Empty query is the same request (placeholder search) with a `sort` — one code path for browse and search, and `fetchInitialData()` disappears.
2. **Enriched index documents.** Trove: add `tag_ids` (int array, filterable), `tag_names` (all-locale names flattened, searchable, weighted below title/description), `trove_type_id` (filterable — unlocks the missing type filter from the template review), `locales` (codes with non-empty titles, filterable), `published_at` (sortable). Collection: `locales`, `created_at`. Declare `searchableAttributes` order (`title`, `description`, `tag_names`), `filterableAttributes`, `sortableAttributes` in `index-settings`; drop the redundant `is_published`/`public` fields.
3. **Filters in the engine.** Tag filter compiles to `tag_ids IN [a,b] AND tag_ids IN [c]` (OR within a tag type, AND across types — current semantics preserved); language to `locales IN [...]`. The 500-cap problem and finding 1.1-1 vanish structurally; facet distributions come back on the same response for per-tag counts and zero-count disabling.
4. **Hydration, not reconstruction.** From the merged hit list, take the page's IDs, run two `whereIn` queries with proper eager loads (`media`, `troveType`, card tags), reorder in PHP by hit position (kills `FIELD()`), map to light array DTOs for the cards. Only the current page's ~24 items ever enter Livewire state.
5. **Component consolidation.** Fold `SearchBar` into `BrowseAll` (`wire:model.live.debounce.400ms` — kills both events and Enter-only search), `#[Url]` on query/filters/page/sort, windowed pagination links, PHP-side sorting in `getFilterTagTypesProperty()` (kills the `ISNULL`/`JSON_EXTRACT` SQL).
6. **Reindex triggers.** `Tag` saved/deleted → reindex its troves (queued); tag pivot changes already flow through the publish lifecycle. Ship a scheduled reconcile (nightly `scout:import` is acceptable at this catalogue scale).
7. **Testing.** With `FIELD()`/`JSON_EXTRACT` gone, the full component renders on SQLite. Bind a fake `SearchesLibrary` implementation for feature tests (filters, pagination, URL state, empty states, tag-filtered collections); add an opt-in integration suite against real Meilisearch (env-gated, CI service container) asserting the federated request shape, facet output, and index settings sync.

**Product decisions needed in this milestone** (both answerable during planning):

- **Collections × tag filters** (fixes finding 1.1-2 either way): (a) aggregate member-trove `tag_ids` onto the collection document — collections then genuinely participate in tag filtering, at the cost of reindexing collections when membership or member-tags change; or (b) exclude collections from results while any tag filter is active. Recommendation: (a) — it's what users would expect a filter to mean, and the reindex trigger is one observer.
- **Default sort for browse mode** (no query): `published_at desc` is the obvious default; whether to expose a sort dropdown (newest/relevance/title) is UI scope.

This milestone subsumes the "BrowseAll rewrite" deferred from the July-7 template review and should also pick up its cheap adjacent wins while the blade is open: `wire:key` usage is already fine, but alt text on card images and keyboard-accessible clear/pagination controls (template review §1.3) belong in the same pass.

## 4. Milestone 2 — Search inside trove content (documents and transcripts)

The architecture is settled by a hard engine constraint: Meilisearch ignores everything past **65,535 word-positions per attribute** (silently), and its own guidance for long-form content is one-document-per-chunk with parent metadata. So content search means a third index, not a bigger `content` field on the trove document.

1. **Extraction pipeline.** Queued job per media item (trigger: publish, plus a `troves:extract-content` backfill command). Store raw extracted text per media item (`media_texts`: media_id, content hash, locale, text, extraction status/tooling). Tooling decision:
   - **PDF-only start**: `spatie/pdf-to-text` (poppler). Note: its latest release requires PHP ^8.4 while the app targets 8.3+ — pin an older release or take this as the nudge to move to 8.4.
   - **Broad formats (PDF/DOCX/PPTX in one interface)**: Apache Tika sidecar container + `vaites/php-apache-tika`. Recommendation: start poppler-only (most library content is PDF), design the extractor behind an interface so Tika can slot in when a deployment needs Office formats. OCR for scanned PDFs (`ocrmypdf`) is a flagged follow-up, triggered when extraction returns ~nothing for a PDF with pages.
2. **Chunk index.** New `trove_chunks` Meilisearch index: chunk documents (`id`, `trove_id`, `locale`, source filename/label, `text` ~1–3 paragraphs split at semantic boundaries) carrying copies of the parent's filter attributes (`tag_ids`, `locales`) so filters keep working. Set `distinctAttribute: trove_id` on the index so at most the best chunk per trove surfaces. Add it as a third query in the existing federated request with `federationOptions.weight` below the metadata indexes (a title match should outrank a body match); the app-side hydration dedupes a trove appearing via both its metadata and a chunk, keeping the higher-ranked hit and the chunk's crop/highlight for a "matched in *filename*" snippet on the card — a visible UX win that makes content search legible.
3. **Transcripts.** Now that `video_links` is multi-host, acquisition honestly splits three ways:
   - **Uploaded audio/video media**: transcribe automatically via a queued job — OpenAI `gpt-4o-transcribe`/`whisper-1` at $0.006/min ($0.36/hr; the `-mini` variant halves it), or self-hosted faster-whisper if a deployment wants no API dependency (requires a Python sidecar — probably not template-default).
   - **Externally hosted videos (YouTube etc.)**: there is no clean automatic path — the YouTube Data API only serves captions to the video's owner via OAuth, and `yt-dlp` caption scraping is ToS-grey and breaks quarterly. Recommendation: an operator-run artisan command for best-effort caption fetch on owned channels, not an automatic pipeline.
   - **The universal fallback that should exist regardless**: a per-locale transcript field on the trove (editor-pasteable, machine-prefillable from the above), stored like extracted text and chunked into the same index. This makes transcripts editable/correctable and decouples search from acquisition politics.
4. **Lifecycle wrinkle (the one real integration risk).** `TrovePublisher` copies media between draft and canonical via `Media::copy()` — new media IDs. Key extracted text by **content hash**, not media ID alone, so copies reuse the extraction and publish never re-extracts unchanged files; a post-publish job re-links or backfills anything missing. This needs a test alongside the existing publisher suite.
5. **Ops additions**: queue worker becomes required (it effectively already is for `SCOUT_QUEUE`), poppler (and optionally tika/whisper key) documented in the README, `scout:sync-index-settings` covers the new index.

**Decision gates for this milestone**: extractor scope (poppler vs Tika sidecar), transcription default (API-based with per-site key vs manual-only), and whether transcripts are editor-visible fields (recommended) or hidden derived data.

## 5. Milestone 3 — Semantic search, then grounded Q&A (decision gate, not a commitment)

Milestone 2's chunk index is deliberately the shape embeddings need — this milestone is configuration plus product decisions, not re-architecture.

1. **Phase 3a — hybrid semantic search** (low risk, ship when M2 is stable): declare an embedder on the `trove_chunks` (and optionally `troves`) index — `openAi` `text-embedding-3-small` (cheap, multilingual-adequate) or a self-hosted `rest`/`ollama` embedder (e.g. bge-m3) for API-free deployments — and add `hybrid: {embedder, semanticRatio: ~0.5}` to the federated queries (hybrid inside federation is supported). Two things to know going in: `documentTemplateMaxBytes` defaults to 400 bytes, i.e. only ~400 bytes of each document get embedded — chunks make this a non-issue, whole-trove documents would need the template raised; and embedder settings have a history of breaking changes between Meilisearch minors, so pin versions and read release notes. Costs: embedding at index time (corpus × re-index frequency) plus one embedding call per search query (adds a provider round-trip to search latency unless the embedder is local). Feature-flag per site (`SiteSetting` or env), default off, degrading to pure keyword search when unconfigured.
2. **Phase 3b — grounded Q&A** ("ask the library a question"): two viable builds, decide when we get there with M2 usage data in hand:
   - **Meilisearch Chat**: native RAG route (retrieval → answer → citations in one endpoint, OpenAI-compatible, per-workspace config; heavily invested in through 2025–26 launch weeks) — but still flagged experimental with explicit hallucination warnings and a moving API. Attractive later; too unstable to build a template feature on today.
   - **App-owned RAG** (recommended if/when we commit): retrieve top chunks via the existing hybrid federated search, generate with the official `laravel/ai` SDK (first-party, Feb 2026 — also covers the transcription calls from M2) or `prism-php/prism`, render the answer with per-chunk citations linking to the trove pages. Full control over prompts, grounding rules ("answer only from the library, cite or refuse"), and cost caps; streaming UI as a separate surface from the browse grid.
   - Template stance either way: opt-in feature behind an API key + site setting, clearly labelled, default off. The gate question before building 3b: does M2 search behaviour show users trying to ask questions (query logs will tell us)?

## 6. Recommended sequence

| Step | Scope | Depends on |
|---|---|---|
| 1 | Milestone 1 plan + implementation: federated `LibrarySearch` service, enriched indexes, engine-side filters/facets, `BrowseAll` rewrite (URL state, server-side pagination, SearchBar fold-in), SQLite-testable, reindex triggers, degraded fallback | — |
| 2 | Milestone 1 hardening: integration test suite vs real Meilisearch, reconcile job, Scout 11 upgrade, deploy-docs update | 1 |
| 3 | Milestone 2: extraction pipeline (poppler first), `media_texts` + `trove_chunks` index, transcript field + upload transcription, snippet UX, publisher hash-keying test | 1 |
| 4 | Phase 3a: hybrid embedder on chunks, feature-flagged per site | 3 |
| 5 | Phase 3b decision gate: review M2/3a usage, then Meilisearch Chat vs app-owned RAG (laravel/ai) | 4 |

Steps 1–2 are worth doing even if content search never ships — they fix live bugs (tag-filtered collections, truncated filtered results), the biggest performance liability on the public site, and the test-coverage hole. Each step should get its own plan in `docs/plans` when picked up.
