# Per-Locale Link/Video/Cover Import Columns — Change Log

Implements [docs/plans/2026-07-08-import-locale-links.md](../plans/2026-07-08-import-locale-links.md), per the spec at [docs/superpowers/specs/2026-07-08-import-locale-links-design.md](../superpowers/specs/2026-07-08-import-locale-links-design.md).

**Date**: 2026-07-09
**Branch**: `feat/import-locale-links` (forked from `dev` after `feat/video-links` merged via PR #24)

## Summary

`troves:import` (`app/Console/Commands/ImportTroves.php`) now accepts `link_url`, `link_title`, `video_url` (and its `youtube_url` alias), and `cover_image_url` either as a flat column — applying to the row's primary locale, unchanged from before — or as one `<column>:<locale>` column per locale (e.g. `link_url:en`, `link_url:fr`), mirroring the existing `title:<locale>`/`description:<locale>` convention. A file that defines both the flat and a locale-suffixed form of the same column is rejected at header-parse time with a clear error, rather than silently preferring one.

## Header parsing

`parseHeader()` is now driven generically by a `$localizableColumns` property (`link_url`, `link_title`, `video_url`, `cover_image_url`). For each, the header may contain the bare column name (targets the primary locale) or `<name>:<locale>` columns (one per locale, validated against `config('branding.locales')` exactly like `title`/`description`) — never both in the same file. A new private helper `localizedColumnValues(array $columnMap, array $row, string $primaryLocale): array<string,string>` extracts a row's non-empty values for one such column, keyed by locale, treating the flat form as "primary locale" under the hood. `$fixedColumns` now holds only `trove_type`, `creation_date`, `collections` (the columns with no locale variant).

## Row building and validation

`buildPlan()` computes `$primaryLocale` immediately after the `title` columns are read (previously computed much later, only for the final row assembly) since the localizable-column extraction now needs it. Each of the four columns is validated per locale (URL format, YouTube-ID normalisation for `video_url`). A `link_title:<locale>` with no matching `link_url:<locale>` in the same row is a row error; a `link_url:<locale>` with no matching `link_title:<locale>` falls back to "View resource" for that locale, as before. `external_links` is assembled as `[locale => [[link_url, link_title]]]` across every locale present in the row, not just the primary one. The plan row now carries `video_urls`/`cover_image_urls` (locale => URL maps) instead of single scalar values, and no longer carries `primary_locale` (nothing reads it once `video_url`/`cover_image_url` are locale-aware maps).

## Deduplication

Cross-row/cross-DB duplicate detection now considers every locale's `link_url` and video source key (via `videoSourceKey()`), not just the primary locale's — a row is skipped as a duplicate if any one of its locale-specific links or videos already exists (in the DB, including drafts and trashed rows, or earlier in the same file).

## Execution — video resolution and cover-image download

`handle()`'s post-plan video-resolution loop now resolves every locale in a row's `video_urls` map independently (each call still only happens on a live run, never `--dry-run`), assembling `video_links` as `[locale => [<resolved record>]]`. `downloadCoverImages()` iterates every locale in `cover_image_urls`, downloading each into its own `cover_image_{locale}` media collection; a failed download warns for that locale specifically and never aborts the row or other locales — best-effort semantics unchanged from before.

## Documentation

`docs/import/README.md`'s column table and "Idempotent re-runs" note now describe the flat-vs-suffixed choice and cross-locale dedup. A new example file, `docs/import/trove-import-template-multilingual.csv`, demonstrates the locale-suffixed style (distinct English/French links and cover images, a locale-suffixed video with no French counterpart, and an English-only link with no French translation), alongside the existing `docs/import/trove-import-template.csv` which continues to demonstrate the flat style unchanged.

## Tests

Added 10 tests to `tests/Feature/Console/ImportTrovesCommandTest.php` (all passing): flat/suffixed exclusivity rejection, per-locale `link_url`/`link_title` import and default-title fallback, orphan `link_title` row error, cross-locale `link_url` dedup, per-locale `video_url` resolution, cross-locale `video_url` dedup, per-locale cover-image download failure warnings, a combined single-row link+video test, and a dry-run smoke test importing the new multilingual template CSV end to end.

## Verification

Full suite: 308 passed, 1 pre-existing skip, on the final branch commit. All existing flat-column tests continue to pass unchanged, confirming backward compatibility.

## Process note

Built with `superpowers:subagent-driven-development`: fresh implementer subagent per task, task-scoped spec+quality review after each (Task 1's review caught and fixed one compound-`&&` style violation inherited from the plan's own sample code), and a final whole-branch review (verdict "ready to merge") that surfaced and closed one cross-task test-coverage gap (a single row combining locale-suffixed `link_url` and `video_url`). Two low-risk Minor findings were left unfixed by design: a duplicated locale-validity error string between `parseHeader()`'s branches, and a same-locale `youtube_url:<locale>`/`video_url:<locale>` collision that silently prefers whichever header comes later (pre-existing in spirit — the flat `youtube_url` alias always shared this property).
