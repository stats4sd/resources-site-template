# Trove cover-image accessors ignore configured locales / broken default fallback

**Date Reported**: 2026-07-03
**Status**: Open
**GitHub Issue**: https://github.com/stats4sd/resources-site-template/issues/11

## Problem

Two related bugs in `Trove`'s cover-image accessors, both diverging from the config-driven `coverImageThumb` accessor that correctly walks `config('branding.locales')`:

**1. `getCoverImageUrl()` hardcodes the locale list.**

```php
$locales = ['en', 'es', 'fr'];
$orderedLocales = array_merge([$currentLocale], array_diff($locales, [$currentLocale]));
```

It only checks the current locale plus `en`/`es`/`fr`. A cover held in any other configured locale (e.g. `de`) that is not the current locale is invisible to it, so it wrongly returns the default image. It should use `config('branding.locales')`.

**2. `Trove::coverImage` uses `??` on an empty string.**

```php
get: fn () => $this->getFirstMediaUrl('cover_image_'.app()->getLocale()) ?? asset('images/default-cover-photo.jpg')
```

`getFirstMediaUrl()` returns `''` (not `null`) when there is no media, so `?? asset(...)` never fires and the accessor returns `''` instead of the default image. Should be `?:` (or an `empty()` check). `coverImage` also has no locale fallback, unlike `coverImageThumb`.

Surfaced/pinned by `tests/Unit/Models/Trove/CoverImageFallbackTest.php`.

## Location

`app/Models/Trove.php` — `getCoverImageUrl()` and the `coverImage` accessor.

## Expected Behavior

Both accessors should use `config('branding.locales')` for the fallback order and return the default image asset when no cover media exists in any configured locale.
