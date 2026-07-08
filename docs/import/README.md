# Trove CSV import

Bulk-import troves (with tags, tag types and collections) from a CSV file. See [trove-import-template.csv](trove-import-template.csv) for a working example.

```bash
php artisan troves:import list.csv --uploader=admin@example.com --dry-run   # validate + print the plan
php artisan troves:import list.csv --uploader=admin@example.com --publish   # import and publish
```

## Options

| Option | Effect |
|---|---|
| `--uploader=email` | **Required.** Email of an existing user, recorded as each trove's uploader. |
| `--publish` | Publish imported troves immediately (via `TrovePublisher`). Without it they land as unpublished drafts for review in the admin panel. |
| `--create-tag-types` | Create tag types for unknown `tag:<slug>` columns instead of aborting. |
| `--skip-media` | Skip cover image downloads. |
| `--dry-run` | Validate and print the plan without writing anything. |

Validation is all-or-nothing: any row error aborts the whole import (the plan output lists every error, so fix them in one pass). Duplicate rows are not errors — they are skipped and reported.

## Columns

Column order doesn't matter. Unrecognised column names abort the import (protects against typos silently dropping data).

`link_url`, `link_title`, `video_url` (and its `youtube_url` alias), and `cover_image_url` may each be given **either** as a flat column (applies to the row's primary locale — the first non-empty `title:<locale>`) **or** as one `<column>:<locale>` column per locale (e.g. `link_url:en`, `link_url:fr`), same convention as `title`/`description`. A file that defines both the flat and a suffixed form of the same column is rejected. See [trove-import-template.csv](trove-import-template.csv) for the flat style and [trove-import-template-multilingual.csv](trove-import-template-multilingual.csv) for the per-locale style.

| Column | Notes |
|---|---|
| `title:<locale>` | At least one required (e.g. `title:en`). One column per locale; locales must be configured on the site (Site Options). The first non-empty title's locale becomes the row's *primary locale* (the implicit target of any flat column below). |
| `description:<locale>` | Optional, same locale convention. |
| `trove_type` | Matched case-insensitively against trove type labels in any locale (e.g. `Video`, `Guide`). Unknown values are errors — trove types are deliberate, create them in the admin panel first. Blank = no type. |
| `creation_date` | ISO format (`2023-05-14`) recommended. Blank = today. |
| `link_url` / `link_url:<locale>` and `link_title` / `link_title:<locale>` | External link. `link_title` defaults to "View resource" for any locale that has a `link_url` but no matching `link_title`. A `link_title:<locale>` with no matching `link_url:<locale>` in the same row is an error. |
| `video_url` / `video_url:<locale>` | Share URL of a video (YouTube, Vimeo, EcoAgTube, …), or a bare 11-char YouTube ID. Each locale's URL is resolved independently at import time: embeddable videos get an embedded player on the public page, others a link card. `youtube_url` (and `youtube_url:<locale>`) is accepted as a legacy alias. |
| `cover_image_url` / `cover_image_url:<locale>` | Downloaded after import into the matching locale's cover collection; a failed download warns for that locale and continues. |
| `collections` | Pipe-separated collection titles, matched case-insensitively across locales; created as public collections if missing. |
| `tag:<tag-type-slug>` | One column per tag type (e.g. `tag:topics`), pipe-separated tag names. Tags matched case-insensitively across locales within the type; created under the site default locale if missing. Unknown slugs abort unless `--create-tag-types` is passed. |

## Behaviour notes

- **Idempotent re-runs**: a row whose `link_url` or video source (YouTube video ID for YouTube URLs, or normalised video URL for all other hosts), in *any* of its locales, already exists on any trove (including drafts and trashed) — or earlier in the same file — is skipped and reported. Amending the CSV and re-running only imports the new rows.
- **Search**: index syncing is disabled during the import. If Meilisearch is active (`SCOUT_DRIVER=meilisearch`) and you published, reindex afterwards: `php artisan scout:import "App\Models\Trove"` and `php artisan scout:import "App\Models\Collection"`.
- **Multi-value separator** is a pipe (`|`) so tag and collection names may contain commas.
- Save the file as UTF-8; an Excel BOM is handled.
