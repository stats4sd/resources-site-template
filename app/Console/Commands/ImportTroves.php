<?php

namespace App\Console\Commands;

use App\Contracts\ResolvesVideoLinks;
use App\Models\Collection;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;
use App\Services\TrovePublisher;
use App\Services\VideoLink\YouTubeAdapter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bulk-import troves (with tags, tag types and collections) from a CSV file.
 * Format reference and a template live in docs/import/.
 *
 * Two passes: pass 1 validates every row and prints the full plan (creates, links,
 * duplicate skips, errors); any error aborts before anything is written. Pass 2 writes
 * everything in one transaction, then downloads cover images (best effort, post-commit).
 *
 *   php artisan troves:import list.csv --uploader=admin@example.com --publish --dry-run
 */
class ImportTroves extends Command
{
    protected $signature = 'troves:import
        {file : Path to the CSV file (see docs/import/README.md for the format)}
        {--uploader= : Email of the existing user recorded as the troves\' uploader}
        {--publish : Publish the imported troves immediately (default: import as unpublished drafts)}
        {--create-tag-types : Create tag types for unknown tag:<slug> columns instead of aborting}
        {--skip-media : Do not download cover images}
        {--dry-run : Validate the file and print the import plan without writing anything}';

    protected $description = 'Bulk-import troves (with tags, tag types and collections) from a CSV file.';

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

    private ResolvesVideoLinks $videoLinkResolver;

    /** @var array<string, int|true> URL / "yt:<id>" keys of troves already in the DB or earlier in the file */
    private array $seenSourceKeys = [];

    /** @var array<string, array<string, int>> [tag type slug => [lowercased name => tag id]] */
    private array $tagIndex = [];

    /** @var array<string, int> [lowercased collection title (any locale) => collection id] */
    private array $collectionIndex = [];

    /** @var array<string, int> [lowercased trove type label (any locale) => trove type id] */
    private array $troveTypeIndex = [];

    public function handle(TrovePublisher $publisher, ResolvesVideoLinks $videoLinkResolver): int
    {
        $this->videoLinkResolver = $videoLinkResolver;

        $path = $this->argument('file');
        if (! is_readable($path)) {
            $this->error("Cannot read file \"{$path}\".");

            return self::FAILURE;
        }

        $uploaderEmail = $this->option('uploader');
        if (! $uploaderEmail) {
            $this->error('The --uploader option is required (email of an existing user).');

            return self::FAILURE;
        }

        $uploader = User::where('email', $uploaderEmail)->first();
        if (! $uploader) {
            $this->error("No user found with email \"{$uploaderEmail}\".");

            return self::FAILURE;
        }

        [$header, $rows] = $this->readCsv($path);
        if ($header === null) {
            $this->error('The file is empty or has no header row.');

            return self::FAILURE;
        }

        $locales = array_keys(config('branding.locales', ['en' => 'English']));

        $headerErrors = [];
        $columns = $this->parseHeader($header, $locales, $headerErrors);
        if ($headerErrors) {
            foreach ($headerErrors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $tagTypes = TagType::with('tags')->get()->keyBy('slug');
        $tagTypesToCreate = array_values(array_diff(array_keys($columns['tags']), $tagTypes->keys()->all()));
        if ($tagTypesToCreate && ! $this->option('create-tag-types')) {
            $this->error('Unknown tag type slug(s): '.implode(', ', $tagTypesToCreate)
                .'. Create them in the admin panel first, or re-run with --create-tag-types.');

            return self::FAILURE;
        }

        $this->buildIndexes($tagTypes);

        $plan = $this->buildPlan($rows, $columns, $locales);

        $this->printPlan($plan, $tagTypesToCreate);

        if ($plan['errors']) {
            $this->error(count($plan['errors']).' row error(s) found; nothing was imported.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete; nothing was written.');

            return self::SUCCESS;
        }

        if (! $plan['rows']) {
            $this->info('Nothing to import.');

            return self::SUCCESS;
        }

        foreach ($plan['rows'] as &$planRow) {
            if ($planRow['video_url'] === null) {
                continue;
            }

            $this->line("  Resolving video {$planRow['video_url']}...");
            $planRow['video_links'] = [$planRow['primary_locale'] => [$this->videoLinkResolver->resolve($planRow['video_url'])->toArray()]];
        }
        unset($planRow);

        $created = $this->executePlan($plan, $tagTypes, $tagTypesToCreate, $uploader, $publisher, $locales[0]);

        if (! $this->option('skip-media')) {
            $this->downloadCoverImages($created);
        }

        $this->info(sprintf(
            'Imported %d trove(s)%s.',
            count($created),
            $this->option('publish') ? ' (published)' : ' (as unpublished drafts)'
        ));

        if ($this->option('publish') && config('scout.driver')) {
            $this->line('Search syncing was disabled during the import; reindex with:');
            $this->line('  php artisan scout:import "App\Models\Trove"');
            $this->line('  php artisan scout:import "App\Models\Collection"');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?array, 1: array<array{int, array}>} [header, [[line number, row], ...]]
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [null, []];
        }

        // Strip a UTF-8 BOM (Excel exports) from the first header cell.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $rows = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                $rows[] = [$line, $row];
            }
        }
        fclose($handle);

        return [$header, $rows];
    }

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

            if (isset($columns['localized'][$name]['flat'])) {
                if ($localeSuffixes) {
                    $errors[] = "Column \"{$name}\" is defined both as a flat column and with locale suffixes ({$name}:{$localeSuffixes[0]}); use one style, not both.";
                }
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

    /**
     * Build the case-insensitive lookup indexes used to link (rather than duplicate)
     * existing records, plus the link_url / YouTube-ID set used to skip re-imports.
     * All matching happens in PHP so it behaves identically on MySQL and SQLite.
     */
    private function buildIndexes(\Illuminate\Support\Collection $tagTypes): void
    {
        foreach (TroveType::all() as $type) {
            foreach ($type->getTranslations('label') as $label) {
                $this->troveTypeIndex[mb_strtolower($label)] = $type->id;
            }
        }

        foreach ($tagTypes as $tagType) {
            $this->tagIndex[$tagType->slug] = [];
            foreach ($tagType->tags as $tag) {
                foreach ($tag->getTranslations('name') as $name) {
                    $this->tagIndex[$tagType->slug][mb_strtolower($name)] = $tag->id;
                }
            }
        }

        foreach (Collection::all() as $collection) {
            foreach ($collection->getTranslations('title') as $title) {
                $this->collectionIndex[mb_strtolower($title)] = $collection->id;
            }
        }

        $existing = Trove::withDrafts()->withTrashed()->get(['id', 'external_links', 'video_links']);
        foreach ($existing as $trove) {
            foreach ($trove->getTranslations('external_links') as $links) {
                foreach ($this->normaliseLinkList($links) as $link) {
                    if (! empty($link['link_url'])) {
                        $this->seenSourceKeys[$link['link_url']] = $trove->id;
                    }
                }
            }
            foreach ($trove->getTranslations('video_links') as $links) {
                foreach ($this->normaliseLinkList($links) as $link) {
                    if (! empty($link['url'])) {
                        $this->seenSourceKeys[$this->videoSourceKey($link['url'])] = $trove->id;
                    }
                }
            }
        }
    }

    /**
     * Older rows store a single link as a bare assoc array rather than a list of them
     * (see Trove::getDownloadableLinks()); normalise to a list.
     */
    private function normaliseLinkList(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        return (isset($links['link_url']) || isset($links['youtube_id']) || isset($links['url'])) ? [$links] : $links;
    }

    /**
     * Pass 1: validate every row and plan all writes without touching the database.
     *
     * @return array{rows: array, skipped: array, errors: array, newTags: array<string, array<string, string>>, newCollections: array<string, string>}
     */
    private function buildPlan(array $rows, array $columns, array $locales): array
    {
        $plan = ['rows' => [], 'skipped' => [], 'errors' => [], 'newTags' => [], 'newCollections' => []];

        foreach ($rows as [$line, $row]) {
            $errors = [];
            $fixed = fn (string $column): string => isset($columns['fixed'][$column])
                ? trim((string) ($row[$columns['fixed'][$column]] ?? ''))
                : '';

            $titles = [];
            foreach ($columns['title'] as $locale => $index) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') {
                    $titles[$locale] = $value;
                }
            }
            if (! $titles) {
                $errors[] = 'no title in any locale';
            }

            $primaryLocale = array_key_first($titles) ?? '';

            $descriptions = [];
            foreach ($columns['description'] as $locale => $index) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') {
                    $descriptions[$locale] = $value;
                }
            }

            $troveTypeId = null;
            if (($troveType = $fixed('trove_type')) !== '') {
                $troveTypeId = $this->troveTypeIndex[mb_strtolower($troveType)] ?? null;
                if ($troveTypeId === null) {
                    $errors[] = "unknown trove type \"{$troveType}\"";
                }
            }

            $creationDate = now()->toDateString();
            if (($rawDate = $fixed('creation_date')) !== '') {
                try {
                    $creationDate = Carbon::parse($rawDate)->toDateString();
                } catch (Throwable) {
                    $errors[] = "unparseable creation_date \"{$rawDate}\"";
                }
            }

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

            $tags = [];
            foreach ($columns['tags'] as $slug => $index) {
                foreach ($this->splitMultiValue((string) ($row[$index] ?? '')) as $name) {
                    $lower = mb_strtolower($name);
                    $tags[$slug][$lower] = $name;
                    if (! isset($this->tagIndex[$slug][$lower])) {
                        $plan['newTags'][$slug][$lower] ??= $name;
                    }
                }
            }

            $collections = [];
            foreach ($this->splitMultiValue($fixed('collections')) as $title) {
                $lower = mb_strtolower($title);
                $collections[$lower] = $title;
                if (! isset($this->collectionIndex[$lower])) {
                    $plan['newCollections'][$lower] ??= $title;
                }
            }

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
        }

        return $plan;
    }

    private function splitMultiValue(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode('|', $value)), fn ($v) => $v !== '')));
    }

    private function videoSourceKey(string $url): string
    {
        $youtubeId = YouTubeAdapter::extractId($url);

        if ($youtubeId !== null) {
            return "yt:{$youtubeId}";
        }

        return 'vid:'.mb_strtolower(rtrim($url, '/'));
    }

    private function printPlan(array $plan, array $tagTypesToCreate): void
    {
        $this->info(sprintf(
            'Import plan: %d trove(s) to import, %d duplicate(s) skipped, %d error(s).',
            count($plan['rows']),
            count($plan['skipped']),
            count($plan['errors']),
        ));

        if ($tagTypesToCreate) {
            $this->line('Tag types to create: '.implode(', ', $tagTypesToCreate));
        }
        foreach ($plan['newTags'] as $slug => $names) {
            $this->line("New \"{$slug}\" tags: ".implode(', ', array_map(fn ($n) => "\"{$n}\"", $names)));
        }
        if ($plan['newCollections']) {
            $this->line('New collections: '.implode(', ', array_map(fn ($n) => "\"{$n}\"", $plan['newCollections'])));
        }

        foreach ($plan['skipped'] as $message) {
            $this->warn($message);
        }
        foreach ($plan['errors'] as $message) {
            $this->error($message);
        }
    }

    /**
     * Pass 2: create tag types, tags, collections and troves in one transaction, with
     * search syncing off for both indexed models (reindex afterwards via scout:import).
     *
     * @return array<array{Trove, array}> created troves paired with their planned row
     */
    private function executePlan(
        array $plan,
        \Illuminate\Support\Collection $tagTypes,
        array $tagTypesToCreate,
        User $uploader,
        TrovePublisher $publisher,
        string $defaultLocale,
    ): array {
        $created = [];

        Trove::withoutSyncingToSearch(function () use ($plan, $tagTypes, $tagTypesToCreate, $uploader, $publisher, $defaultLocale, &$created) {
            Collection::withoutSyncingToSearch(function () use ($plan, $tagTypes, $tagTypesToCreate, $uploader, $publisher, $defaultLocale, &$created) {
                DB::transaction(function () use ($plan, $tagTypes, $tagTypesToCreate, $uploader, $publisher, $defaultLocale, &$created) {
                    foreach ($tagTypesToCreate as $slug) {
                        $tagTypes->put($slug, TagType::create([
                            'slug' => $slug,
                            'label' => [$defaultLocale => Str::headline($slug)],
                            'description' => [$defaultLocale => ''],
                            'freetext' => false,
                        ]));
                        $this->tagIndex[$slug] = [];
                    }

                    foreach ($plan['newTags'] as $slug => $names) {
                        foreach ($names as $lower => $name) {
                            $tag = $tagTypes[$slug]->tags()->create(['name' => [$defaultLocale => $name]]);
                            $this->tagIndex[$slug][$lower] = $tag->id;
                        }
                    }

                    foreach ($plan['newCollections'] as $lower => $title) {
                        $collection = Collection::create([
                            'title' => [$defaultLocale => $title],
                            'description' => [$defaultLocale => ''],
                            'uploader_id' => $uploader->id,
                            'public' => true,
                        ]);
                        $this->collectionIndex[$lower] = $collection->id;
                    }

                    foreach ($plan['rows'] as $row) {
                        $trove = new Trove;
                        $trove->title = $row['title'];
                        $trove->description = $row['description'];
                        $trove->trove_type_id = $row['trove_type_id'];
                        $trove->external_links = $row['external_links'];
                        $trove->video_links = $row['video_links'] ?? null;
                        $trove->creation_date = $row['creation_date'];
                        $trove->source = false;
                        $trove->uploader_id = $uploader->id;
                        $trove->save();

                        $tagIds = [];
                        foreach ($row['tags'] as $slug => $names) {
                            foreach (array_keys($names) as $lower) {
                                $tagIds[] = $this->tagIndex[$slug][$lower];
                            }
                        }
                        if ($tagIds) {
                            $trove->tags()->sync($tagIds);
                        }

                        if ($row['collections']) {
                            $trove->collections()->sync(
                                array_map(fn ($lower) => $this->collectionIndex[$lower], $row['collections'])
                            );
                        }

                        if ($this->option('publish')) {
                            $publisher->publish($trove);
                        }

                        $created[] = [$trove, $row];
                    }
                });
            });
        });

        return $created;
    }

    /**
     * Post-commit, best effort: a dead image URL should cost a warning, not the import.
     *
     * @param  array<array{Trove, array}>  $created
     */
    private function downloadCoverImages(array $created): void
    {
        foreach ($created as [$trove, $row]) {
            if (! $row['cover_image_url']) {
                continue;
            }

            try {
                $trove->addMediaFromUrl($row['cover_image_url'])
                    ->toMediaCollection('cover_image_'.$row['primary_locale']);
            } catch (Throwable $e) {
                $this->warn("Line {$row['line']}: cover image download failed ({$e->getMessage()}); trove imported without it.");
            }
        }
    }
}
