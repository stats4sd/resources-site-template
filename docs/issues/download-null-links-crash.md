# Download route 500s when a trove has null link columns

**Date Reported**: 2026-07-03
**Status**: Open
**GitHub Issue**: https://github.com/stats4sd/resources-site-template/issues/9

## Problem

`Trove::downloadAllFilesAsZip()` always calls `Trove::getDownloadableLinks($locale)`, which iterates the `youtube_links` (and `external_links`) translations:

```php
$youtubeLinks = $this->getTranslation('youtube_links', $locale) ?? [];
// ...
foreach ($youtubeLinks as $link) { ... }
```

When the `youtube_links` / `external_links` JSON column is genuinely `null`, Spatie's `getTranslation()` returns an empty **string** `''`, not `null`. The `?? []` guard only catches `null`, so `$youtubeLinks` stays `''` and the subsequent `foreach ('')` throws `foreach() argument must be of type array|object, string given`.

Because `getDownloadableLinks()` runs on the `/download-all-zip/{slug}` route, a published trove with null link columns causes that route to **500**.

Surfaced by `tests/Feature/Http/DownloadTest.php` (which passes explicit empty-array link translations to exercise the intended paths). Real data seeded via `ExampleDataSeeder` always sets links, which likely masks this in practice.

## Location

`app/Models/Trove.php` — `getDownloadableLinks()`.

## Expected Behavior

Null link columns should be treated as "no links" (empty collection), not throw. Guard against the empty-string case, e.g. coerce a non-array translation to `[]` before iterating.
