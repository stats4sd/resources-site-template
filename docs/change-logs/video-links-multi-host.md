# Multi-Host Video Links — Change Log

Implements [docs/plans/2026-07-08-video-links-multi-host.md](../plans/2026-07-08-video-links-multi-host.md), per the spec at [docs/superpowers/specs/2026-07-08-video-embedding-design.md](../superpowers/specs/2026-07-08-video-embedding-design.md).

**Date**: 2026-07-08
**Branch**: `feat/video-links`

## Summary

Replaced the YouTube-ID-only `youtube_links` field on Troves with a generic `video_links` field: editors paste any share URL and the system resolves it to an embedded player or a styled link card. Three adapters handle YouTube (keyless oEmbed probe), EcoAgTube (page scrape), and everything else (`embed/embed` generic fallback). Resolution happens live in the admin form on blur, and defensively again at save time. The public page renders with zero external calls, degrading gracefully to a titled link card when the video cannot be embedded.

## `youtube_links` → `video_links` rename and data conversion

- New column name `video_links` (translatable JSON, same Spatie `HasTranslations` setup as before).
- `App\Support\VideoLink\LegacyYoutubeLinksConverter` converts the old `{'youtube_id': id}` shape (both the list form `[{'youtube_id': id}, …]` and the bare single-assoc form `{'youtube_id': id}`) to the new six-key record shape. Entries already in the new shape pass through untouched; empty/invalid entries are dropped.
- Migration `rename_youtube_links_to_video_links_on_troves` renames the column then bulk-converts every existing row in-place via `DB::table()->chunkById()`, bypassing model events, the `PublishedScope`, soft-delete filtering and Scout syncing — so canonical rows, shadow drafts and trashed rows all convert in one pass.
- The new stored record shape for each entry in a locale's `video_links` list: `{url, provider, embed_url, embeddable, title, resolved_url}`. `resolved_url` is internal bookkeeping (the URL the resolution was computed from), used to detect stale resolutions at save time.

## Resolver service and adapters

**`App\Support\VideoLink\VideoLinkResult`** — immutable readonly DTO representing a resolved video. `toArray()` serialises to the stored record shape (snake_case keys).

**`App\Contracts\ResolvesVideoLinks`** — interface with a single `resolve(string $url): VideoLinkResult` method. Container-bound to `App\Services\VideoLinkResolver`.

**`App\Services\VideoLinkResolver`** implements `ResolvesVideoLinks`. Entry point for all resolution: trims the URL, runs URL safety guards (http/https scheme only, no userinfo), routes to the first matching adapter, and wraps every adapter call in a catch-all that returns a non-embeddable result — resolution can never throw or block saving.

Three adapters in `app/Services/VideoLink/`:

- **`YouTubeAdapter`** — matches `youtube.com`, `youtu.be`, `youtube-nocookie.com`. Extracts the video ID via a chain of regexes (watch, short, live, embed, nocookie embed, bare-ID forms). Makes a single keyless oEmbed probe (`youtube.com/oembed`) with a 5-second timeout; a 4xx response means embed-disabled or private → returns a non-embeddable result with the URL still set. `extractId()` is `public static` and reused by the importer.
- **`EcoAgTubeAdapter`** — matches `ecoagtube.org`. Fetches the page with a browser User-Agent (Fastly CDN 403s default clients). Parses the embed-share `<textarea>` for an iframe `src`: native EcoAgTube embeds (`/embed/{numericId}`) are returned directly; YouTube-backed iframes are delegated to `YouTubeAdapter` for an oEmbed probe (embeddability confirmed, title from the EcoAgTube page's OG tag). No iframe found → non-embeddable titled link. `browserUserAgent()` is `public static` and reused by the `Embed` container binding below.
- **`GenericVideoAdapter`** — generic fallback using the new `embed/embed` v4 Composer dependency. Constructs an `Embed\Embed` client backed by Guzzle (also with browser UA and 5-second timeout). Extracts the iframe `src` from the oEmbed or OpenGraph `code.html` response; rejects non-`https` sources. No embed code found → returns a non-embeddable result with whatever title was discovered (so AccessAgriculture and similar sites surface as a titled link card).

The `Embed\Embed` instance is bound in `AppServiceProvider::register()` so the Guzzle client config (timeout, browser UA) is centralised there rather than scattered across adapters.

## Admin form UX

`TroveResource::form()` gained a **Videos** repeater (inside a `TranslatableComboField`) where editors paste any share URL. The URL field uses `->live(onBlur: true)` with an `afterStateUpdated` closure that calls the resolver and writes back the resolved fields (provider, embed URL, embeddable, title) into the repeater row, showing a status line via an uncoloured `Placeholder`: "Embeds on the page — {title}", "Link only — the public page will show a link card for this video.", or "Couldn't verify this URL — it will be shown as a plain link.". The URL itself is always preserved — the status line is informational.

At save time, the `ResolvesVideoLinkFormData` trait (used by both `CreateTrove` and `EditTrove`) re-resolves any entry whose `resolved_url` doesn't match the current `url` (i.e. the URL was pasted but blur resolution never fired, or the URL was edited after resolution). Resolver exceptions are caught here too — stale entries fall back to a non-embeddable placeholder rather than blocking the save.

## Public rendering — `<x-video-link>`

`resources/views/components/video-link.blade.php` receives a single `$link` array (one entry from `video_links`). If `embeddable` and `embed_url` are set (and `embed_url` begins with `https://`) it renders a responsive `<iframe>` with standard allow-attributes; otherwise it renders a styled link card showing the title if available, falling back to the host domain, and a "Watch on …" button. The trove show view iterates `video_links` entries for the current locale and passes each to `<x-video-link>`.

Also fixed a leftover `$youtubeLinks` variable reference in `trove.blade.php` that caused a 500 error on any published trove page mid-branch.

## CSV importer

`app/Console/Commands/ImportTroves.php` gained a `video_url` column (header `youtube_url` accepted as an alias for backward compatibility). On a live run (not `--dry-run`) each URL is resolved via `VideoLinkResolver` and stored in the new shape. Dry-runs skip resolution entirely so no outbound HTTP happens during validation. Dedupe keys are `yt:{id}` for YouTube URLs (extracted via `YouTubeAdapter::extractId()`) and `vid:{url}` for all other hosts, checked against existing troves including drafts and trashed rows. `docs/import/README.md` updated with the new `video_url` / `youtube_url` column docs.

## New Composer dependency

`embed/embed` v4 — provides oEmbed discovery and OpenGraph parsing for arbitrary URLs. Requires `php ^7.4|^8`; its PSR-17/18 transport needs are satisfied by the project's existing `guzzlehttp/guzzle` and `http-interop/http-factory-guzzle`.

## Tests

Added 46 tests across unit and feature suites (all passing):

- **Unit** — `VideoLinkResultTest` (DTO serialisation); `YouTubeAdapterTest` (ID extraction dataset, matching, oEmbed probe success/4xx/timeout, no-ID URL); `EcoAgTubeAdapterTest` (native embed, browser UA, YouTube-backed delegation, YouTube oEmbed failure, no-iframe fallback, HTTP error and connection failure); `GenericVideoAdapterTest` (oEmbed-discoverable page, no-embed fallback, non-https iframe rejection, fetch failure); `VideoLinkResolverTest` (container binding, URL safety guards, YouTube/EcoAgTube routing, adapter exception catch-all); `LegacyYoutubeLinksConverterTest` (list shape, single-assoc shape, pass-through + drop, translations dict, null/empty).
- **Feature** — `TroveVideoRenderingTest` (iframe rendered for embeddable, link card for non-embeddable, nothing rendered for entry with no URL); `VideoLinksFormTest` (live resolution action, save-time re-resolution, error tolerance); updated `ImportTrovesCommandTest` (new `video_url` column + `youtube_url` alias, resolver called on live run, dry-run stays offline).

## Verification

296 tests passed, 1 pre-existing skip. Pint pre-existing style issues unrelated to this feature branch.
