# Change log: Trove CSV import command

Implements [docs/plans/trove-csv-import-command.md](../plans/trove-csv-import-command.md): a deterministic artisan command for bulk-populating the library from a CSV file, with tags, tag types and collections created or linked from the file's contents.

## New files

- **`app/Console/Commands/ImportTroves.php`** — `troves:import {file} --uploader=email [--publish] [--create-tag-types] [--skip-media] [--dry-run]`. Two-pass design: pass 1 validates every row and prints the full plan (troves to import, duplicates skipped, tags/tag types/collections to create vs link, all row errors); any validation error aborts before anything is written. Pass 2 creates everything in one DB transaction with Scout syncing disabled for both `Trove` and `Collection`, publishes via `TrovePublisher::publish()` when `--publish` is passed (preserving the "only TrovePublisher mutates lifecycle" invariant), then downloads cover images post-commit (best effort — a dead URL warns and continues).
- **`docs/import/README.md`** — column reference and behaviour notes.
- **`docs/import/trove-import-template.csv`** — working example/template.
- **`tests/Feature/Console/ImportTrovesCommandTest.php`** — 8 tests, 38 assertions: happy path (translations, tag reuse case-insensitively across locales, tag/collection creation, external links shape), `--publish`, `--dry-run` writes nothing, duplicate skipping (against DB and within-file), `--create-tag-types` gating, all-or-nothing validation, header validation (unknown columns, unconfigured locales), missing uploader/file.

## Format highlights

- `title:<locale>` / `description:<locale>` columns for translatable fields; `tag:<tag-type-slug>` columns with pipe-separated names for the tagging taxonomy; `collections` pipe-separated, created as public if missing.
- `link_url` (external links — ecoagtube goes here) vs `youtube_url` (real YouTube only; ID extracted from any usual URL form).
- Idempotent re-runs: rows are skipped when their `link_url`/YouTube ID already exists on any trove (including drafts and trashed).
- All name matching (trove types, tags, collections) is case-insensitive across every locale value, done in PHP so MySQL and SQLite behave identically.

## Out of scope (future work)

- `--update` mode for folding CSV changes onto existing troves via `TrovePublisher::draftFor()` + `publish()`.
- A Claude skill to prepare the canonical CSV from raw source metadata (mapping free-text keywords onto the existing taxonomy).

## Verification

- New tests pass; full suite green (241 passed, 1 pre-existing skip).
