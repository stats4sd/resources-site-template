# Quick Fixes from Template Review

**Status**: Not Started

Extracted from [docs/code-reviews/template-full-review.md](../code-reviews/template-full-review.md) (2026-07-07): every bug/issue fixable without significant rewrites or architecture changes. Section numbers (§) reference that review. Excluded items are listed at the end so the cut-line is explicit.

Suggested delivery: one branch/PR per workstream below (A–F). Each item names the fix approach; anything needing a judgement call is flagged.

---

## A. Security & access control

1. **Escape trove title in result card** (§3.A-1). `{!! $item['title'] !!}` → `{{ $item['title'] }}` in `resources/views/components/resource-result-card.blade.php:36`. Titles are plain-text TextInput fields; nothing legitimate is lost. (The broader rich-text sanitiser for descriptions/SiteContent is excluded — see cut list.)
2. **Enforce `public` on collections** (§3.A-2), three call sites:
   - `routes/web.php:59-62` — add `->where('public', 1)` to the `/collections/{id}` route.
   - `app/Livewire/TroveCollections.php:16` — filter `$resource->collections` to public ones.
   - `app/Models/Collection.php` — add `shouldBeSearchable(): bool { return (bool) $this->public; }`, then `scout:import` Collections. Also mirror the Trove `maxTotalHits` index setting for Collection in `config/scout.php`.
3. **Block bulk-delete bypass on users** (§3.A-3). Add `deleteAny(): bool { return false; }` to `UserPolicy` and remove `DeleteBulkAction` from `UserResource.php:131` (single-row delete keeps the existing per-record guards). Test: bulk delete cannot remove the last admin.
4. **Re-validate invite at submit** (§3.A-4). In `Register::handleRegistration()`, re-check `isUsable()` + email-not-taken + `open_registration` (for the open track) exactly as `SetPassword::resetPassword()` does; on failure, notify + redirect rather than proceeding. Wrap the create in a try/catch on the email unique violation → friendly error instead of a 500. Test: invite expiring between mount and submit is rejected (copy the pattern from `PasswordSetupTest.php:120`).
5. **Password strength on admin-set passwords** (§3.A-5). Add `Password::default()` to the password field in `UserResource.php:75-87` (create + edit).
6. **Hide Resend for accepted invites** (§3.A-7). `->visible(fn (Invite $r) => $r->status !== InviteStatus::Accepted)` on the Resend action in `InviteResource.php:86-100`.

## B. Draft/publish lifecycle & data integrity

7. **Fix `unpublish()` premature media purge** (§3.B-8). In `TrovePublisher::publish()`, run `purgeSupersededMedia()` via `DB::afterCommit(...)` instead of calling it directly — under a nested transaction it then correctly defers to the outer commit; standalone behaviour is unchanged. Test: rollback after `unpublish()`'s inner publish leaves stashed media files on disk.
8. **Stop deleting draft media files inside the publish transaction** (§3.B-9). Confirmed viable via Spatie's preserve mechanism: the `deleting` hook in `InteractsWithMedia` (vendor `InteractsWithMedia.php:49-64`) checks `shouldDeletePreservingMedia()` *before* the force-delete branch, so setting that flag skips `deleteAllMedia()` entirely. Gotcha: `deletePreservingMedia` is a `protected` typed property — setting it from `TrovePublisher` would go through Eloquent `__set` into attributes while the hook reads the real property, so it must be set inside the model. Approach:
   - Add `Trove::forceDeletePreservingMedia(): bool` — sets `$this->deletePreservingMedia = true` then `return $this->forceDelete();`.
   - In `deleteDraftRow()`: collect the draft's media IDs, call `forceDeletePreservingMedia()`, then `DB::afterCommit(fn () => Media::whereIn('id', $ids)->get()->each->delete())` — `Media::delete()` removes files via its own observer, independent of the (now deleted) parent row.
   - Backstop: the preserved rows are orphans (their model row is hard-deleted), which `PruneSupersededMedia`'s current query can never match — extend the command with an orphan sweep (Trove-type media whose `model_id` is not in `Trove::withoutGlobalScope(...)->withTrashed()->select('id')`), which composes with item 12.
   - Test: rollback after `deleteDraftRow()` leaves the draft's files on disk; successful publish still removes them post-commit.
9. **Record `previous_slugs` on any canonical slug change** (§3.B-11). In the `Trove` saving hook: if `isDirty('slug')` and there is a non-null original slug on a canonical row, append the original to `previous_slugs`. Covers unpublish→retitle→republish without touching the publish branches. Test: old public URL 301s after the sequence.
10. **Fix slug collision generation** (§3.B-12). Replace the count-based suffix in `Trove::generateSlug()` with a loop: candidate `foo`, `foo-2`, `foo-3`… until no *canonical* row (`whereNull('published_id')`, `withDrafts()`, `withTrashed()`) holds it. No DB unique index — shadow drafts legitimately share the canonical's slug. Test: "Foo 1" + "Foo" + new "Foo" yields three distinct slugs.
11. **`AllTrovesTable` and collection counts use working versions** (§3.B-13). `Trove::query()->workingVersions()` in `AllTrovesTable.php:85`; same treatment for `troves_count` in `CollectionResource.php:124-126`. Test: pending-changes trove appears once.
12. **`PruneSupersededMedia` scope fix** (§3.B-14). Subquery → `Trove::withoutGlobalScope(PublishedScope::class)->withTrashed()->select('id')` in `PruneSupersededMedia.php:34`. Add a first test for the command.
13. **`after_commit => true`** in `config/scout.php:58` (§3.B-15).

## C. Search & public frontend

14. **Zero-hit search must return zero results** (§3.C-16). In both blocks of `BrowseAll::search()` (`BrowseAll.php:76-83`, `101-108`): when `$this->query` is non-empty and `$ids` is empty, force an empty result (`whereRaw('1 = 0')`) instead of falling through unfiltered.
15. **Handle Meilisearch outage** (§3.C-17). try/catch around both `->raw()` calls; on exception, log + set a "search is temporarily unavailable" flag rendered in the view, results empty.
16. **Hydrate `translation.target_locales` at boot** (§3.C-18). In `AppServiceProvider::boot()`, alongside the existing hydration: `config(['translation.target_locales' => array_keys($locales)])` (excluding the source locale, matching the tio package's expectation) so admin-added locales actually switch language.
17. **Static locales fallback** (§3.C-20). Add `'locales' => ['en' => 'English']` to `config/branding.php`; sweep `header.blade.php:33,41` and `browse-all.blade.php:61,71` for the no-default `config('branding.locales')` calls (belt and braces).
18. **Wire up the "Show language filter" toggle** (§3.C-19). Gate the language filter in `browse-all.blade.php:61` on `config('branding.features.show_language_filter', true) && count(locales) > 1`.
19. **Fix hardcoded locales in `getCoverImageUrl()`** (§3.C-21). `Trove.php:523` — derive from `config('branding.locales', ['en' => 'English'])` like `coverImageThumb` does. While there: make the unreachable `?? asset(...)` in the `coverImage` accessor (`Trove.php:263-268`) an explicit `?:`/empty-check, or delete the accessor (it's unused — see item 30).
20. **Guest preview → require auth** (§3.C-22). Add `->middleware('auth')` to the preview route, drop the inline check; update `PreviewTest.php:7` to assert a redirect to login instead of a blank 200.
21. **Fix single-item associative `youtube_links`** (§3.C-23). Reorder the probe in `trove.blade.php:125-129` so the associative shape is checked before `[0]`.
22. **Fix quirks mode** (§3.C-24). Move the post-`@endsection` `<script>` blocks in `trove.blade.php` and `collection.blade.php` inside the section (or a `@push('scripts')` stack rendered by the layout). Remove the stray `</div>` at `home.blade.php:43`.
23. **Pagination defects** (§3.C-25). Quote `'bg-gray-50'` in the two Alpine `:class` expressions (`browse-all.blade.php:141,159`); clamp `$page` in `BrowseAll::loadPage()` to `[1, pageCount]`; add `wire:key` to the results `@foreach`; make the empty state distinguish "library is empty" from "no results for your filters".
24. **Zip route slug fallback** (§3.C-26). Use `Trove::findBySlugOrRedirect()` in `/download-all-zip/{slug}` so old-slug links keep working.
25. **Declare `public string $locale = ''`** on `BrowseAll` (§3.C-27), or drop the assignment in `mount()` — the middleware sets the app locale anyway. Also delete the dead `private $previousQuery` guard in `SearchBar`.

## D. Config, boot & ops

26. **`OPTIMISE` → `OPTIMIZE`** in `PurgeTelescopeEntries.php:34-36` (§3.D-28).
27. **Move Telescope to require-dev** (decided 2026-07-07). `composer require --dev laravel/telescope`; remove `TelescopeServiceProvider` from `config/app.php:176` and register it conditionally in `AppServiceProvider::register()` (`environment('local') && class_exists(...)`, per Telescope docs), with `laravel/telescope` in composer `dont-discover`. Keep the telescope migrations/tables (plain Schema, no package dependency). Guard the `PurgeTelescopeEntries` weekly schedule in `app/Console/Kernel.php` on the provider being registered (or local env). Fix the `??` → `?:` gate bug in `TelescopeServiceProvider.php:50` (§3.D-29) while touching it, and the `OPTIMIZE` typo per item 26. `.env.example` no longer needs a `TELESCOPE_ENABLED` entry.
28. **`.env.example` corrections** (§3.D-31): rename `AZURE_SECRET` → `AZURE_CLIENT_SECRET`; remove duplicate `SESSION_DRIVER`; change the `config/scout.php` driver default to `env('SCOUT_DRIVER', 'null')` (decided 2026-07-07) and add a README note that search requires explicitly setting `SCOUT_DRIVER=meilisearch`.
29. **Seeder org-name warning** (§3.D-30). In `SiteContentSeeder`, `warn()` when `BRAND_ORG_NAME` is unset/default so `migrate --seed` before configuring `.env` doesn't silently bake "Your Organisation" into the DB.
30. **Align `config/media-library.php` `max_file_size`** with its comment (pick one — recommend a real limit like 100MB and fix the comment).

## E. Dead code & cruft deletion

31. **Delete the orphaned Breeze scaffolding** (§2.3): `routes/auth.php`, all 9 controllers in `app/Http/Controllers/Auth/`, `LoginRequest`, `ProfileUpdateRequest`. It is unrouted, references views that don't exist, and `RegisteredUserController::store()` is a latent registration bypass.
32. **Delete `app:rdbc`** (`ResetDbAndConvert.php`) + the commented `mysql_old_troves` connection and `DB_*_OLD` env reads in `config/database.php`.
33. **Delete `.github/workflows/main.yml`** (Stats4SD-internal issue assignment; fails in every fork).
34. **Delete unreferenced files**: `draft.yaml` (+ `laravel-shift/blueprint` from require-dev), the 12 unused images in `public/images/`, `lang/gettext/{es,fr}` Stats4SD `.po`/`.mo` exports, `resources/views/components/pagination.blade.php`.
35. **Delete dead code**: `ViewTrove` page; `file_name_{locale}` loop in `HasTroveFormActions.php:125-131`; `TroveResource::getRecordTitleAttribute()`; duplicate `Trove::user()` relation (keep `uploader()`; grep for references first); `ImportTrovesToSearch` command; `front_end_url` in `config/app.php`; unused `Trove::coverImage` accessor if not fixed under item 19.
36. **Move `spatie/laravel-ray` to require-dev.**
37. **Update `composer.json` name/description** from `laravel/laravel` to the template's own identity.
38. **Add a `LICENSE` file** (MIT, matching `composer.json`).

## F. Follow-through

- Update `CLAUDE.md` where behaviour changes (Socialment is *not* wired up — fix the doc drift now even though the package decision is deferred; scout `after_commit`; deleted commands).
- Run the full Pest suite; add the new tests named inline above (items 3, 4, 7, 9, 10, 11, 12, 20).

---

## Needs a product decision first (quick to execute, not quick to decide)

- **Socialment/Azure**: deferred (2026-07-07) — leave the packages, config, views and `connected_accounts` machinery in place until the product decision is made. In the meantime only fix the doc drift (CLAUDE.md currently claims the plugin is wired into the panel; it isn't — covered in workstream F). The `AZURE_SECRET` env-name fix in item 28 still applies.

## Explicitly excluded (rewrites / architecture — separate plans)

- `BrowseAll` rewrite: server-side pagination, eager loading, URL state, portable SQL, removal of the SearchBar event ping-pong (§2.2, §3.C-16/17 root causes beyond the point fixes above).
- Stale-editor clobber fix + removal of the `existingDraftMediaMap`/`remapMediaState` machinery (§3.B-10, §2.2) — behavioural change needing UX decision on the bounce.
- Rich-text sanitiser for descriptions/SiteContent output (§3.A-1 related).
- Token hashing at rest; email normalisation (§3.A-6).
- `SiteSetting` boot caching, admin-panel branding (logo/colour uploads), `brand.css` extraction, analytics config, `site:install`, email verification, 2FA, audit log, SEO layer (title/meta/OG/sitemap/404), accessibility pass, README production docs, CI workflow — §1 and §4 items tracked by the review's architecture recommendations.
