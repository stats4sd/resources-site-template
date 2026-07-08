# Per-locale link_url / link_title / video_url / cover_image_url import columns — Design

**Status:** Approved, ready for planning.

## Context

`app/Console/Commands/ImportTroves.php` (`troves:import`) currently accepts flat `link_url`, `link_title`, `video_url` (alias `youtube_url`), and `cover_image_url` CSV columns. Each is a single value per row and is written to the **primary locale** only (the locale of the first non-empty `title:<locale>` column). But `external_links` and `video_links` are translatable columns on `Trove` (`app/Models/Trove.php`), and cover images are stored per-locale (`cover_image_{locale}` media collections) — so a multilingual trove currently has no way to import different link/video/cover values per locale via CSV; every locale after import shares whatever was put in the primary locale's slot (or has nothing).

`title:<locale>` and `description:<locale>` already support this per-locale pattern for text fields. This change extends the same pattern to `link_url`, `link_title`, `video_url`/`youtube_url`, and `cover_image_url`.

## Goal

Allow each of `link_url`, `link_title`, `video_url` (and `youtube_url` alias), `cover_image_url` to be supplied either as today's flat column (applies to the primary locale only, unchanged behavior) **or** as one `<column>:<locale>` column per locale — never both in the same file for the same base column.

## Column parsing (`parseHeader`)

Extend the existing locale-suffix regex (currently only matching `title`/`description`) to also match `link_url`, `link_title`, `video_url`, `cover_image_url`. The existing `youtube_url` → `video_url` alias normalisation happens first, on the raw header name, so it also covers the suffixed form: `youtube_url:fr` normalises to `video_url:fr` before the locale-suffix regex and exclusivity check run. Locale validation (must be a locale configured via `config('branding.locales')`) is identical to `title:<locale>`.

`trove_type`, `creation_date`, `collections` remain flat-only — no locale variant is meaningful for them and they are not part of this change.

**Flat-vs-suffixed exclusivity**: for each of the four base columns, a header row may define it as a flat column OR as one-or-more `:<locale>` columns, never both. Defining both is a header-level error: `Column "link_url" is defined both as a flat column and with locale suffixes (link_url:en); use one style, not both.` This is a new header validation rule alongside the existing "unrecognised column" and "at least one title:<locale> required" checks — it fails before any row processing, consistent with the two-pass validate-then-write structure.

## Row building (`buildPlan`)

Each of the four base columns is collected into a `[locale => value]` map, the same shape `title`/`description` already produce. The flat form produces a single-entry map keyed by the row's primary locale (i.e. flat columns are now just sugar for "one locale, implicitly the primary one" — same end behavior as today, expressed through the same code path as the suffixed form).

Per-column validation runs **per locale** instead of once:

- `link_url:<locale>` — must be a valid URL (`FILTER_VALIDATE_URL`), same check as today.
- `link_title:<locale>` — free text, no format validation (as today). If a locale has `link_title` but no `link_url` for that same locale **in that row**, it's a row error: `link_title has no matching link_url for locale "fr"`. If a locale has `link_url` but no `link_title`, it falls back to the existing default `"View resource"` for that locale.
- `video_url:<locale>` — same validation as today (bare 11-char YouTube ID normalised to a watch URL, then `FILTER_VALIDATE_URL`), applied per locale.
- `cover_image_url:<locale>` — must be a valid URL, same check as today, applied per locale.

`external_links` on the planned row becomes `[locale => [['link_url' => ..., 'link_title' => ...]]]` covering every locale present in the row, not just the primary locale.

### Dedup (source keys)

Today, one row contributes at most two source keys to `seenSourceKeys` (its `link_url` and its `video_url`, both from the single primary-locale value). This becomes: one key **per locale** per column — every locale's `link_url` and every locale's `video_url` (via `videoSourceKey()`) in the row is checked against and added to `seenSourceKeys`. A row is a duplicate ("skipped") if *any* of its locale-specific link/video values matches something already seen (in the DB, or earlier in the same file). This is a natural extension of the existing rule — a trove is not re-imported just because its `fr` video happens to differ from an already-imported `en` video sourced from the same row's sibling locale, but it *is* skipped if e.g. its `en` link was already imported for another trove.

## Execution phase

**Video resolution** (`handle()`, currently a single `$videoLinkResolver->resolve()` call per row on the primary locale's URL): becomes a loop over every locale present in the row's `video_url` map, resolving each independently and assembling `video_links` as `[locale => [<resolved record>]]` — the same per-locale dict shape the column already stores (`app/Models/Trove.php` translatable). Resolution still only happens on a live run, never during `--dry-run` (unchanged).

**Cover image download** (`downloadCoverImages()`, currently downloads one URL into `cover_image_{primary_locale}`): becomes a loop over every locale present in the row's `cover_image_url` map, downloading each into its own `cover_image_{locale}` media collection. Failures remain per-item, best-effort warnings that don't abort the row or the import.

The **primary locale** concept is unchanged in every other respect (still drives the slug and is still what a *flat* column implicitly targets) — it no longer has any special role once a column is expressed in `:<locale>` form.

## Docs

- `docs/import/README.md`: column table notes that `link_url`, `link_title`, `video_url`/`youtube_url`, `cover_image_url` each accept either the flat form or one-or-more `:<locale>` forms, not both in the same file, mirroring the existing `title`/`description` note.
- Two example CSVs instead of one modified in place:
  - `docs/import/trove-import-template.csv` (existing file, unchanged) — demonstrates the flat-column style.
  - `docs/import/trove-import-template-multilingual.csv` (new) — same example content, but with `link_url:en`/`link_url:fr`, `link_title:en`/`link_title:fr`, `video_url:en`/`video_url:fr`, `cover_image_url:en`/`cover_image_url:fr` instead of the flat forms, to demonstrate the locale-suffixed style is mutually exclusive with the flat one.
  - README links both.

## Testing

`tests/Feature/Console/ImportTrovesCommandTest.php` gains coverage for:

- Importing a row with `link_url:en` + `link_url:fr` (and matching `link_title:*`) produces the correct per-locale `external_links`.
- Importing a row with `video_url:en` + `video_url:fr` resolves both and produces per-locale `video_links` (extend `fakeVideoResolver()` assertions/spy to check both were resolved).
- `cover_image_url:<locale>` downloads into the correct per-locale media collection (mock/stub as the existing cover-image tests do, or extend `--skip-media` coverage if downloads aren't otherwise tested).
- Header error when a file defines both `link_url` and `link_url:en`.
- Row error when `link_title:fr` has no matching `link_url:fr`.
- Dedup: a row whose `fr`-locale `link_url` collides with an existing trove's link (in another locale or the same) is skipped.
- Existing flat-column tests continue to pass unchanged (regression coverage for the "flat is sugar for primary-locale-keyed map" equivalence).
