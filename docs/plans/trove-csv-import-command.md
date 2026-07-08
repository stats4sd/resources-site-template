# Plan: Trove CSV import command

**Status:** Completed — see [change log](../change-logs/trove-csv-import-command.md).

## Goal

Bulk-populate the library from a CSV file (e.g. a list of ecoagtube video links with metadata), turning each row into a trove with tags, tag types and collections created or linked as needed. The import must be fully deterministic — any LLM-assisted preparation of the CSV happens upstream of this command, with the CSV file itself as the human-reviewable seam.

## Design

### CSV format

One row per trove. Two suffix conventions carry the structural dimensions:

- `title:<locale>` / `description:<locale>` — translatable fields, one column per locale; at least one `title:*` column required; locales validated against `config('branding.locales')`.
- `tag:<tag-type-slug>` — pipe-separated tag names per tag type. Tags matched case-insensitively across all locale values of existing tag names; created (under the default locale) if missing. Unknown tag-type slugs abort unless `--create-tag-types` is passed.
- Fixed columns: `trove_type` (matched by label across locales, error if unknown, blank allowed), `creation_date` (ISO date, defaults to today), `link_url` + `link_title` (external link — ecoagtube links go here), `youtube_url` (real YouTube links only; ID is extracted), `cover_image_url` (downloaded post-import), `collections` (pipe-separated titles, matched across locales, created as `public` if missing).

### Command

`php artisan troves:import {file} --uploader=email [--publish] [--create-tag-types] [--skip-media] [--dry-run]`

- **Two passes.** Pass 1 validates every row and builds a plan (troves to create, duplicates to skip, tags/tag types/collections to create vs link); the plan is always printed. Any validation error aborts before anything is written (all-or-nothing). `--dry-run` stops after the plan.
- **Idempotency:** rows whose `link_url` or YouTube ID already exists on any trove (including drafts and trashed) — or earlier in the same file — are skipped with a report line, so re-running an amended CSV is safe. No `--update` mode in v1; amend via the admin panel or delete-and-reimport.
- **Publishing:** `--publish` routes through `TrovePublisher::publish()` per trove, keeping the "only TrovePublisher mutates lifecycle" invariant. Without it, troves land as unpublished drafts for admin review.
- **Scout:** all writes run inside `withoutSyncingToSearch()` for both `Trove` and `Collection`; the command prints a `scout:import` reminder when a scout driver is active.
- **Media:** cover images are downloaded after the DB transaction commits; a failed download warns and continues (never rolls back the import). Stored in `cover_image_<primary locale of the row>`.

### Deliverables

- `app/Console/Commands/ImportTroves.php`
- `docs/import/trove-import-template.csv` + `docs/import/README.md` (column reference)
- `tests/Feature/Console/ImportTrovesCommandTest.php`

### Future work (out of scope)

- `--update` mode: fold CSV changes onto existing troves via `TrovePublisher::draftFor()` + `publish()`.
- A Claude skill that prepares the CSV from raw source data (scraped metadata → canonical CSV, reusing existing taxonomy).
