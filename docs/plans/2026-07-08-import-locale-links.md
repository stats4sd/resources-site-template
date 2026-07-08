# Per-Locale Link/Video/Cover Import Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Not Started

**Spec:** `docs/superpowers/specs/2026-07-08-import-locale-links-design.md`

**Goal:** Let `troves:import` accept `link_url`, `link_title`, `video_url` (and its `youtube_url` alias), and `cover_image_url` either as today's flat column (primary-locale only) or as one `<column>:<locale>` column per locale, mirroring how `title:<locale>`/`description:<locale>` already work.

**Architecture:** Extend `App\Console\Commands\ImportTroves`'s header parser to recognise a `:<locale>` suffix on the four columns above (in addition to the existing `title`/`description` suffix support), reject a file that defines a column both flat and suffixed, and thread the resulting per-locale value maps through row validation, `external_links`/`video_links` assembly, cover-image download, and cross-row/DB duplicate detection — all of which currently assume a single primary-locale value per row. The header-parsing code is written once, generically, driven by a `$localizableColumns` property list; Task 1 puts `link_url`/`link_title` on that list (a self-contained slice: no separate execution phase), and Task 2 moves `video_url`/`cover_image_url` onto it (they need matching changes to the resolution and download loops). This ordering avoids an intermediate state where a column's header syntax is accepted but its value is silently dropped.

**Tech Stack:** Laravel 13, PHP 8.3, Pest 4 (SQLite `:memory:`).

## Global Constraints

- Follow the Spatie/Laravel guidelines already in use: happy path last, no `else`, no compound `&&` conditions, typed properties, string interpolation, curly braces always, descriptive variable names (never `$e` — use `$exception`).
- **No `private const`** — inline single-use literals or use a private property for multi-use values (this plan only introduces properties, not constants).
- Never let resolution or a failed cover-image download block saving a trove — both remain best-effort per the existing behavior.
- A column's header syntax must never be accepted while its value is silently dropped — each task wires a column's parsing, validation, and consumption together, not header parsing ahead of consumption.
- Tests: `php artisan test` (Pest, SQLite `:memory:`). This command's tests already live in `tests/Feature/Console/ImportTrovesCommandTest.php`; extend it rather than creating a parallel file.
- Run `vendor/bin/pint --dirty` before each commit.
- No migrations in this plan — no schema changes.

---

### Task 1: Locale-suffixed header parsing + per-locale `link_url`/`link_title`

**Files:**
- Modify: `app/Console/Commands/ImportTroves.php`
- Test: `tests/Feature/Console/ImportTrovesCommandTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `parseHeader()` now returns `array{title: array<string,int>, description: array<string,int>, fixed: array<string,int>, tags: array<string,int>, localized: array<string, array<string,int>>}` — `localized` is `[base column name => [locale-or-"flat" => column index]]`, driven generically by the new `$localizableColumns` property (`['link_url', 'link_title']` after this task). New private helper `localizedColumnValues(array $columnMap, array $row, string $primaryLocale): array<string,string>` — Task 2 reuses this exact signature for `video_url` and `cover_image_url`. `video_url` and `cover_image_url` stay on `$fixedColumns` and untouched in `buildPlan()`/`handle()`/`downloadCoverImages()` in this task — Task 2 moves them.

- [ ] **Step 1: Write the failing tests**

Open `tests/Feature/Console/ImportTrovesCommandTest.php`. Insert the following tests after the existing `it('skips rows whose source url already exists', ...)` block (i.e. right before `it('aborts on unknown tag type slugs unless --create-tag-types is passed', ...)`):

```php
it('rejects a column defined both flat and with locale suffixes', function () {
    $path = importCsv(
        ['title:en', 'link_url', 'link_url:fr'],
        ['A trove', 'https://example.org/a', 'https://example.org/a-fr'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->expectsOutputToContain('is defined both as a flat column and with locale suffixes')
        ->assertExitCode(1);

    expect(Trove::withDrafts()->count())->toBe(0);
});

it('imports per-locale link_url and link_title columns', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_url:fr', 'link_title:en', 'link_title:fr'],
        ['Compost basics', 'Les bases du compost', 'https://example.org/en', 'https://example.org/fr', 'Read more', 'En savoir plus'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();
    expect($trove->getTranslation('external_links', 'en'))->toBe([['link_url' => 'https://example.org/en', 'link_title' => 'Read more']])
        ->and($trove->getTranslation('external_links', 'fr'))->toBe([['link_url' => 'https://example.org/fr', 'link_title' => 'En savoir plus']]);
});

it('defaults link_title to "View resource" per locale when omitted', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_url:fr'],
        ['A trove', 'Un trove', 'https://example.org/en', 'https://example.org/fr'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();
    expect($trove->getTranslation('external_links', 'fr'))->toBe([['link_url' => 'https://example.org/fr', 'link_title' => 'View resource']]);
});

it('errors when link_title has no matching link_url for that locale', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_title:en', 'link_title:fr'],
        ['A trove', 'Un trove', 'https://example.org/en', 'Read more', 'En savoir plus'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->expectsOutputToContain('link_title has no matching link_url for locale "fr"')
        ->assertExitCode(1);

    expect(Trove::withDrafts()->count())->toBe(0);
});

it('dedupes rows whose link_url matches an already-imported locale-specific link', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'link_url:en', 'link_url:fr'],
        ['First trove', 'Premier trove', 'https://example.org/shared', 'https://example.org/fr-only'],
        ['Second trove', 'Deuxieme trove', 'https://example.org/other', 'https://example.org/shared'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=ImportTrovesCommandTest`
Expected: the five new tests FAIL — `link_url:fr`/`link_title:fr` etc. are currently rejected as "Unrecognised column", and the exclusivity/pairing checks don't exist yet. The pre-existing tests in the file still PASS at this point.

- [ ] **Step 3: Replace the column properties, `parseHeader()`, and the top of `buildPlan()`'s row loop**

In `app/Console/Commands/ImportTroves.php`, replace the `$fixedColumns` property (currently lines 41-50):

```php
    /** @var list<string> Recognised single-value columns that don't support a locale suffix. */
    private array $fixedColumns = [
        'trove_type',
        'creation_date',
        'video_url',
        'cover_image_url',
        'collections',
    ];

    /** @var list<string> Columns that may be given flat (primary-locale only) or as one "<name>:<locale>" column per locale, not both. */
    private array $localizableColumns = [
        'link_url',
        'link_title',
    ];
```

Replace `parseHeader()` (currently lines 204-243) with:

```php
    /**
     * Map header cells to column indexes. Unknown headers are errors — a typo'd column
     * silently ignored would mean silently dropped data.
     *
     * @return array{
     *     title: array<string, int>,
     *     description: array<string, int>,
     *     fixed: array<string, int>,
     *     tags: array<string, int>,
     *     localized: array<string, array<string, int>>
     * }
     */
    private function parseHeader(array $header, array $locales, array &$errors): array
    {
        $columns = ['title' => [], 'description' => [], 'fixed' => [], 'tags' => [], 'localized' => []];

        foreach ($header as $index => $raw) {
            $name = strtolower(trim((string) $raw));

            if ($name === 'youtube_url' || str_starts_with($name, 'youtube_url:')) {
                $name = 'video_url'.substr($name, strlen('youtube_url'));
            }

            if ($name === '') {
                $errors[] = 'Column '.($index + 1).' has an empty header.';
            } elseif (preg_match('/^(title|description):([a-z0-9_-]+)$/', $name, $matches)) {
                if (! in_array($matches[2], $locales, true)) {
                    $errors[] = "Column \"{$name}\": locale \"{$matches[2]}\" is not configured on this site (configured: ".implode(', ', $locales).').';
                } else {
                    $columns[$matches[1]][$matches[2]] = $index;
                }
            } elseif (preg_match('/^tag:([a-z0-9_-]+)$/', $name, $matches)) {
                $columns['tags'][$matches[1]] = $index;
            } elseif (preg_match('/^('.implode('|', $this->localizableColumns).'):([a-z0-9_-]+)$/', $name, $matches)) {
                if (! in_array($matches[2], $locales, true)) {
                    $errors[] = "Column \"{$name}\": locale \"{$matches[2]}\" is not configured on this site (configured: ".implode(', ', $locales).').';
                } else {
                    $columns['localized'][$matches[1]][$matches[2]] = $index;
                }
            } elseif (in_array($name, $this->localizableColumns, true)) {
                $columns['localized'][$name]['flat'] = $index;
            } elseif (in_array($name, $this->fixedColumns, true)) {
                $columns['fixed'][$name] = $index;
            } else {
                $errors[] = "Unrecognised column \"{$name}\". Valid columns: title:<locale>, description:<locale>, tag:<tag-type-slug>, ".implode(', ', $this->fixedColumns).', and '.implode(', ', $this->localizableColumns).' (each either flat or as "<name>:<locale>").';
            }
        }

        if (! $columns['title']) {
            $errors[] = 'At least one "title:<locale>" column is required.';
        }

        foreach ($this->localizableColumns as $name) {
            $localeSuffixes = array_values(array_diff(array_keys($columns['localized'][$name] ?? []), ['flat']));

            if (isset($columns['localized'][$name]['flat']) && $localeSuffixes) {
                $errors[] = "Column \"{$name}\" is defined both as a flat column and with locale suffixes ({$name}:{$localeSuffixes[0]}); use one style, not both.";
            }
        }

        return $columns;
    }

    /**
     * Extract a row's non-empty values for one localizable column, keyed by locale. A flat
     * column (no locale suffix in the header) targets the row's primary locale.
     *
     * @param  array<string, int>  $columnMap  locale (or "flat") => column index
     * @return array<string, string>
     */
    private function localizedColumnValues(array $columnMap, array $row, string $primaryLocale): array
    {
        $values = [];

        foreach ($columnMap as $locale => $index) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($value === '') {
                continue;
            }

            $values[$locale === 'flat' ? $primaryLocale : $locale] = $value;
        }

        return $values;
    }
```

In `buildPlan()` (currently lines 310-440), the row loop currently starts with `$fixed = fn (...)` then builds `$titles`, `$descriptions`, `$troveTypeId`, `$creationDate`, then `$linkUrl = $fixed('link_url'); ...` and `$videoUrl = $fixed('video_url'); ...` and `$coverImageUrl = $fixed('cover_image_url') ?: null; ...`, then the error-abort check, then the duplicate-check block. Replace everything from `$linkUrl = $fixed('link_url');` through the end of the duplicate-check block (i.e. up to but not including the `$tags = [];` block) with:

```php
            $linkUrls = $this->localizedColumnValues($columns['localized']['link_url'] ?? [], $row, $primaryLocale);
            $linkTitles = $this->localizedColumnValues($columns['localized']['link_title'] ?? [], $row, $primaryLocale);

            foreach ($linkUrls as $locale => $url) {
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $errors[] = "invalid link_url:{$locale} \"{$url}\"";
                }
            }

            foreach ($linkTitles as $locale => $title) {
                if (! isset($linkUrls[$locale])) {
                    $errors[] = "link_title has no matching link_url for locale \"{$locale}\"";
                }
            }

            $videoUrl = $fixed('video_url');
            if ($videoUrl !== '') {
                if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoUrl)) {
                    $videoUrl = "https://www.youtube.com/watch?v={$videoUrl}";
                }

                if (! filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                    $errors[] = "invalid video_url \"{$videoUrl}\"";
                }
            }

            $coverImageUrl = $fixed('cover_image_url') ?: null;
            if ($coverImageUrl !== null && ! filter_var($coverImageUrl, FILTER_VALIDATE_URL)) {
                $errors[] = "invalid cover_image_url \"{$coverImageUrl}\"";
            }

            if ($errors) {
                $plan['errors'][] = "Line {$line}: ".implode('; ', $errors).'.';

                continue;
            }

            $sourceKeys = array_values(array_merge(
                array_values($linkUrls),
                $videoUrl !== '' ? [$this->videoSourceKey($videoUrl)] : [],
            ));
            $duplicateKey = collect($sourceKeys)->first(fn ($key) => isset($this->seenSourceKeys[$key]));
            if ($duplicateKey !== null) {
                $plan['skipped'][] = "Line {$line}: skipped — a trove with source \"{$duplicateKey}\" already exists.";

                continue;
            }
            foreach ($sourceKeys as $key) {
                $this->seenSourceKeys[$key] = true;
            }

```

`$primaryLocale` must be available at this point — confirm the existing `$primaryLocale = array_key_first($titles);` line further down (currently right before the old `$plan['rows'][] = [...]` block) is moved up to immediately after the `$titles` loop (right after the `if (! $titles) { ... }` check), since `$linkUrls`/`$linkTitles` now need it earlier. Delete the old, now-duplicate `$primaryLocale = array_key_first($titles);` line further down.

Leave the `$tags = [];` and `$collections = [];` blocks untouched. Replace the old `$plan['rows'][] = [...]` block (which built `external_links` inline from `$linkUrl`/`$fixed('link_title')`) with:

```php
            $externalLinks = null;
            if ($linkUrls) {
                $externalLinks = [];
                foreach ($linkUrls as $locale => $url) {
                    $externalLinks[$locale] = [['link_url' => $url, 'link_title' => $linkTitles[$locale] ?? 'View resource']];
                }
            }

            $plan['rows'][] = [
                'line' => $line,
                'title' => $titles,
                'description' => $descriptions,
                'trove_type_id' => $troveTypeId,
                'creation_date' => $creationDate,
                'primary_locale' => $primaryLocale,
                'external_links' => $externalLinks,
                'video_url' => $videoUrl !== '' ? $videoUrl : null,
                'video_links' => null,
                'cover_image_url' => $coverImageUrl,
                'tags' => $tags,
                'collections' => array_keys($collections),
            ];
```

`handle()` and `downloadCoverImages()` are untouched by this task — they still read the scalar `video_url`, `cover_image_url` and `primary_locale` keys, which this task's plan row still provides.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=ImportTrovesCommandTest`
Expected: PASS — all previous tests plus the five new ones.

- [ ] **Step 5: Run the full suite to catch regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Console/Commands/ImportTroves.php tests/Feature/Console/ImportTrovesCommandTest.php
git commit -m "Support per-locale link_url/link_title columns in troves:import"
```

---

### Task 2: Per-locale `video_url`/`cover_image_url` — resolution and download

**Files:**
- Modify: `app/Console/Commands/ImportTroves.php`
- Test: `tests/Feature/Console/ImportTrovesCommandTest.php`

**Interfaces:**
- Consumes: `localizedColumnValues()` (Task 1).
- Produces: plan rows now carry `video_urls` (`array<string,string>`, locale => URL) instead of scalar `video_url`, and `cover_image_urls` (`array<string,string>`) instead of scalar `cover_image_url`; `primary_locale` is removed from the plan row (nothing reads it anymore).

- [ ] **Step 1: Write the failing tests**

Insert after the `it('does not resolve video urls during a dry run', ...)` block at the end of `tests/Feature/Console/ImportTrovesCommandTest.php`:

```php
it('resolves video_url per locale', function () {
    $resolver = fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'title:fr', 'video_url:en', 'video_url:fr'],
        ['A video', 'Une vidéo', 'https://www.youtube.com/watch?v=q76bMs-NwRk', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();

    expect($resolver->resolvedUrls)->toBe([
        'https://www.youtube.com/watch?v=q76bMs-NwRk',
        'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
    ])
        ->and($trove->getTranslation('video_links', 'en')[0]['url'])->toBe('https://www.youtube.com/watch?v=q76bMs-NwRk')
        ->and($trove->getTranslation('video_links', 'fr')[0]['provider'])->toBe('ecoagtube');
});

it('dedupes rows whose video_url matches an already-imported locale-specific video', function () {
    fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'title:fr', 'video_url:en', 'video_url:fr'],
        ['First', 'Premier', 'https://www.youtube.com/watch?v=q76bMs-NwRk', 'https://www.ecoagtube.org/content/x'],
        ['Second', 'Deuxieme', 'https://www.ecoagtube.org/content/x', 'https://www.youtube.com/watch?v=xNN7iTA57jM'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});

it('downloads cover images per locale and warns independently on failure', function () {
    $path = importCsv(
        ['title:en', 'title:fr', 'cover_image_url:en', 'cover_image_url:fr'],
        ['A trove', 'Un trove', 'http://127.0.0.1:1/en.jpg', 'http://127.0.0.1:1/fr.jpg'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->expectsOutputToContain('cover image download failed for locale "en"')
        ->expectsOutputToContain('cover image download failed for locale "fr"')
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});
```

Also update the file's leading doc comment (currently: `Media downloads are not exercised here (they need a reachable URL); rows in these fixtures simply omit cover_image_url.`) to:

```php
/**
 * troves:import — CSV bulk import (see docs/import/README.md).
 *
 * Successful media downloads are not exercised here (they need a reachable URL); most
 * fixtures simply omit cover_image_url. The failure path is exercised against a
 * fast-refusing local address (127.0.0.1:1) instead of a real download.
 */
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=ImportTrovesCommandTest`
Expected: the three new tests FAIL — `video_url:fr`/`cover_image_url:fr` are still rejected as unrecognised columns (Task 1 only made `link_url`/`link_title` locale-aware). All other tests still PASS.

- [ ] **Step 3: Move `video_url`/`cover_image_url` onto the localizable column list**

In `app/Console/Commands/ImportTroves.php`, update the two properties from Task 1 to:

```php
    /** @var list<string> Recognised single-value columns that don't support a locale suffix. */
    private array $fixedColumns = [
        'trove_type',
        'creation_date',
        'collections',
    ];

    /** @var list<string> Columns that may be given flat (primary-locale only) or as one "<name>:<locale>" column per locale, not both. */
    private array $localizableColumns = [
        'link_url',
        'link_title',
        'video_url',
        'cover_image_url',
    ];
```

`parseHeader()` needs no further changes — it already drives the suffix regex, the flat-column matching, and the exclusivity check generically off `$this->localizableColumns`.

- [ ] **Step 4: Update `buildPlan()`, `handle()`, and `downloadCoverImages()`**

In `buildPlan()`, replace the `$videoUrl`/`$coverImageUrl` block written in Task 1:

```php
            $videoUrl = $fixed('video_url');
            if ($videoUrl !== '') {
                if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoUrl)) {
                    $videoUrl = "https://www.youtube.com/watch?v={$videoUrl}";
                }

                if (! filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                    $errors[] = "invalid video_url \"{$videoUrl}\"";
                }
            }

            $coverImageUrl = $fixed('cover_image_url') ?: null;
            if ($coverImageUrl !== null && ! filter_var($coverImageUrl, FILTER_VALIDATE_URL)) {
                $errors[] = "invalid cover_image_url \"{$coverImageUrl}\"";
            }
```

with:

```php
            $videoUrls = $this->localizedColumnValues($columns['localized']['video_url'] ?? [], $row, $primaryLocale);
            $normalizedVideoUrls = [];
            foreach ($videoUrls as $locale => $url) {
                if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
                    $url = "https://www.youtube.com/watch?v={$url}";
                }

                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $errors[] = "invalid video_url:{$locale} \"{$url}\"";

                    continue;
                }

                $normalizedVideoUrls[$locale] = $url;
            }
            $videoUrls = $normalizedVideoUrls;

            $coverImageUrls = $this->localizedColumnValues($columns['localized']['cover_image_url'] ?? [], $row, $primaryLocale);
            foreach ($coverImageUrls as $locale => $url) {
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $errors[] = "invalid cover_image_url:{$locale} \"{$url}\"";
                }
            }
```

The `$fixed` closure is now only used for `trove_type`/`creation_date` — leave its declaration in place (`collections` also still reads via `$fixed('collections')` further down).

Replace the `$sourceKeys` computation (written in Task 1) with:

```php
            $sourceKeys = array_values(array_merge(
                array_values($linkUrls),
                array_map(fn ($url) => $this->videoSourceKey($url), array_values($videoUrls)),
            ));
```

Replace the `$plan['rows'][] = [...]` block (written in Task 1) with:

```php
            $plan['rows'][] = [
                'line' => $line,
                'title' => $titles,
                'description' => $descriptions,
                'trove_type_id' => $troveTypeId,
                'creation_date' => $creationDate,
                'external_links' => $externalLinks,
                'video_urls' => $videoUrls,
                'video_links' => null,
                'cover_image_urls' => $coverImageUrls,
                'tags' => $tags,
                'collections' => array_keys($collections),
            ];
```

(`primary_locale` is dropped — it was only feeding the scalar `video_url`/`cover_image_url` handling this step just removed.)

In `handle()`, replace the video-resolution loop (currently):

```php
        foreach ($plan['rows'] as &$planRow) {
            if ($planRow['video_url'] === null) {
                continue;
            }

            $this->line("  Resolving video {$planRow['video_url']}...");
            $planRow['video_links'] = [$planRow['primary_locale'] => [$this->videoLinkResolver->resolve($planRow['video_url'])->toArray()]];
        }
        unset($planRow);
```

with:

```php
        foreach ($plan['rows'] as &$planRow) {
            if (! $planRow['video_urls']) {
                continue;
            }

            $videoLinks = [];
            foreach ($planRow['video_urls'] as $locale => $url) {
                $this->line("  Resolving video {$url}...");
                $videoLinks[$locale] = [$this->videoLinkResolver->resolve($url)->toArray()];
            }
            $planRow['video_links'] = $videoLinks;
        }
        unset($planRow);
```

Replace `downloadCoverImages()` entirely:

```php
    /**
     * Post-commit, best effort: a dead image URL should cost a warning, not the import.
     *
     * @param  array<array{Trove, array}>  $created
     */
    private function downloadCoverImages(array $created): void
    {
        foreach ($created as [$trove, $row]) {
            foreach ($row['cover_image_urls'] as $locale => $url) {
                try {
                    $trove->addMediaFromUrl($url)->toMediaCollection("cover_image_{$locale}");
                } catch (Throwable $exception) {
                    $this->warn("Line {$row['line']}: cover image download failed for locale \"{$locale}\" ({$exception->getMessage()}); trove imported without it.");
                }
            }
        }
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=ImportTrovesCommandTest`
Expected: PASS — all tests, including the three new ones and every test carried over from Task 1.

- [ ] **Step 6: Run the full suite to catch regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Console/Commands/ImportTroves.php tests/Feature/Console/ImportTrovesCommandTest.php
git commit -m "Support per-locale video_url/cover_image_url columns in troves:import"
```

---

### Task 3: Documentation — README + locale-suffixed example CSV

**Files:**
- Modify: `docs/import/README.md`
- Create: `docs/import/trove-import-template-multilingual.csv`
- Test: `tests/Feature/Console/ImportTrovesCommandTest.php`

**Interfaces:**
- Consumes: the finished `troves:import` behavior from Tasks 1-2.
- Produces: nothing consumed by other tasks — this is the terminal task.

- [ ] **Step 1: Write the failing smoke test**

Add to the end of `tests/Feature/Console/ImportTrovesCommandTest.php`:

```php
it('imports the multilingual template CSV cleanly as a dry run', function () {
    TroveType::create(['label' => ['en' => 'Guide', 'fr' => 'Guide']]);

    $this->artisan('troves:import', [
        'file' => base_path('docs/import/trove-import-template-multilingual.csv'),
        '--uploader' => 'importer@example.com',
        '--dry-run' => true,
    ])->assertExitCode(0);
});
```

(The `beforeEach` in this file already configures `en`/`fr` locales and creates a `Video` trove type; this test adds the `Guide` type the template also references.)

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="imports the multilingual template CSV"`
Expected: FAIL — `docs/import/trove-import-template-multilingual.csv` doesn't exist yet.

- [ ] **Step 3: Create the multilingual template CSV**

Create `docs/import/trove-import-template-multilingual.csv`:

```csv
title:en,title:fr,description:en,description:fr,trove_type,creation_date,link_url:en,link_url:fr,link_title:en,link_title:fr,video_url:en,video_url:fr,cover_image_url:en,cover_image_url:fr,collections,tag:topics,tag:locations,tag:authors
"How to make compost from farm waste","Fabriquer du compost à partir de déchets agricoles","A step-by-step demonstration of building a compost heap using crop residues and animal manure.","Une démonstration étape par étape de la fabrication d'un tas de compost.",Video,2023-05-14,https://www.ecoagtube.org/w/abc123xyz,https://www.ecoagtube.org/w/abc123xyz-fr,"Watch on EcoAgtube","Voir sur EcoAgtube",,,https://example.org/images/compost-cover-en.jpg,https://example.org/images/compost-cover-fr.jpg,"Soil Health|Getting Started","Composting|Soil fertility",Kenya,"Access Agriculture"
"Push-pull pest management",,"Managing stem borers and striga weed with the push-pull intercropping system.",,Video,2022-11-02,,,,,https://www.youtube.com/watch?v=q76bMs-NwRk,,,,"Pest Management","Integrated pest management|Intercropping","East Africa",
"Farmer field school facilitation guide",,"A practical guide for facilitators running farmer field schools.",,Guide,2024-01-20,https://example.org/ffs-guide.pdf,,"Download the guide (PDF)",,,,,,"Getting Started","Training|Extension",Global,
```

This mirrors `trove-import-template.csv`'s three example rows, but shows the locale-suffixed style: row 1 has distinct English/French links and cover images, row 2 shows a locale-suffixed video with no French counterpart, and row 3 shows an English-only link with no French translation at all — demonstrating that per-locale columns don't require every locale to be filled in.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter="imports the multilingual template CSV"`
Expected: PASS.

- [ ] **Step 5: Update the README**

In `docs/import/README.md`, replace the `## Columns` table rows for `link_url` / `link_title`, `video_url`, and `cover_image_url` (and the sentence introducing the table) as follows.

Replace:

```markdown
Column order doesn't matter. Unrecognised column names abort the import (protects against typos silently dropping data).

| Column | Notes |
|---|---|
| `title:<locale>` | At least one required (e.g. `title:en`). One column per locale; locales must be configured on the site (Site Options). The first non-empty title's locale becomes the row's *primary locale* (used for links, cover image and slug). |
| `description:<locale>` | Optional, same locale convention. |
| `trove_type` | Matched case-insensitively against trove type labels in any locale (e.g. `Video`, `Guide`). Unknown values are errors — trove types are deliberate, create them in the admin panel first. Blank = no type. |
| `creation_date` | ISO format (`2023-05-14`) recommended. Blank = today. |
| `link_url` / `link_title` | External link. `link_title` defaults to "View resource". |
| `video_url` | Share URL of a video (YouTube, Vimeo, EcoAgTube, …), or a bare 11-char YouTube ID. The URL is resolved at import time: embeddable videos get an embedded player on the public page, others a link card. `youtube_url` is accepted as a legacy alias for this column. |
| `cover_image_url` | Downloaded after import into the primary locale's cover collection; a failed download warns and continues. |
| `collections` | Pipe-separated collection titles, matched case-insensitively across locales; created as public collections if missing. |
| `tag:<tag-type-slug>` | One column per tag type (e.g. `tag:topics`), pipe-separated tag names. Tags matched case-insensitively across locales within the type; created under the site default locale if missing. Unknown slugs abort unless `--create-tag-types` is passed. |
```

With:

```markdown
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
```

Also update the "Idempotent re-runs" bullet under "Behaviour notes" — replace:

```markdown
- **Idempotent re-runs**: a row whose `link_url` or video source (YouTube video ID for YouTube URLs, or normalised video URL for all other hosts) already exists on any trove (including drafts and trashed) — or earlier in the same file — is skipped and reported. Amending the CSV and re-running only imports the new rows.
```

with:

```markdown
- **Idempotent re-runs**: a row whose `link_url` or video source (YouTube video ID for YouTube URLs, or normalised video URL for all other hosts), in *any* of its locales, already exists on any trove (including drafts and trashed) — or earlier in the same file — is skipped and reported. Amending the CSV and re-running only imports the new rows.
```

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add docs/import/README.md docs/import/trove-import-template-multilingual.csv tests/Feature/Console/ImportTrovesCommandTest.php
git commit -m "Document per-locale import columns with a multilingual example CSV"
```

---

## Change log

After Task 3 is committed, save a summary to `docs/change-logs/import-locale-links.md` referencing this plan and the spec at `docs/superpowers/specs/2026-07-08-import-locale-links-design.md`, per this repo's `CLAUDE.md` work patterns.
