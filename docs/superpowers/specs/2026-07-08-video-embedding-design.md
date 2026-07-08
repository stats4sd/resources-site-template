# Multi-host video links on Troves — design

**Date**: 2026-07-08
**Status**: Approved (brainstormed and approved in-session)

## Problem

A trove can currently hold YouTube videos only, and the editor must manually extract and paste the bare 11-character YouTube ID. This is unintuitive and limits troves to a single host. Editors should be able to paste the share URL from any video hosting site (YouTube, Vimeo, EcoAgTube, AccessAgriculture, …) and have the system embed the video where possible, falling back to a styled link when embedding isn't (e.g. AccessAgriculture offers no embed mechanism at all; some EcoAgTube videos may not be embeddable).

## Platform facts (live-verified 2026-07-08)

- **YouTube / Vimeo**: clean oEmbed endpoints, no API key. oEmbed status doubles as an embeddability probe — embed-disabled/private videos return 4xx (YouTube: 400/401/403; Vimeo: 404 even when the video page exists).
- **EcoAgTube** (Drupal 10): no oEmbed. Every video page bakes an embed iframe into its HTML — either `https://www.ecoagtube.org/embed/{numericId}` (natively hosted; confirmed frameable, no X-Frame-Options/CSP) or a raw YouTube embed iframe (YouTube-backed videos). Their CDN returns 403 to non-browser User-Agents, so page fetches must send a browser UA. Absence of the iframe in the page ⇒ not embeddable.
- **AccessAgriculture** (Drupal 10): self-hosted MP4s on S3 behind expiring signed URLs; no embed modal, no `/embed/` route, no oEmbed, no `og:video`. Link-only is the only integration.
- **PHP tooling**: `embed/embed` (oscarotero, v4.4.18, actively maintained, PHP 8.3-fine, framework-agnostic) implements the generic cascade (oEmbed → discovery → OpenGraph scraping) and supports a custom User-Agent.

## Decisions (from brainstorming)

1. **Resolution timing**: at save time in admin (plus live on-blur feedback in the form). Resolved data is stored; the public page makes zero external calls.
2. **Resolver strategy**: `embed/embed` package for the generic cascade + one app-owned EcoAgTube adapter (+ app-owned YouTube adapter for the common case). Unresolvable URLs become plain links.
3. **Migration**: rename `youtube_links` → `video_links` and convert existing data in place (offline, derivable from the ID, no HTTP).
4. **Fallback UX**: a styled link card (title from oEmbed/OpenGraph metadata, host domain, "Watch on …" button) in the slot the embed would occupy.
5. **Editor feedback**: live on paste/blur — the repeater item shows an Embeds / Link-only / Unreachable status before saving, with a defensive re-check at save.

## 1. Data model

`youtube_links` becomes `video_links` — still a translatable JSON column (per-locale array), still copied untouched by the draft lifecycle (it is a plain content attribute; `TrovePublisher` needs no changes). Each entry stores the resolved record, not just an ID:

```json
{
  "url": "https://www.ecoagtube.org/content/biofertilizer-formulation-1",
  "provider": "ecoagtube",
  "embed_url": "https://www.ecoagtube.org/embed/32021",
  "embeddable": true,
  "title": "Biofertilizer formulation"
}
```

- `url` — the share URL as pasted (trimmed/normalised).
- `provider` — `youtube` | `ecoagtube` | a provider name detected by the generic resolver | `null` (unrecognised).
- `embed_url` — always a validated `https` iframe-src we constructed or extracted ourselves; we never store or render provider-supplied HTML (XSS boundary). `null` when not embeddable.
- `embeddable` — boolean; drives iframe vs link card.
- `title` — from oEmbed/OpenGraph metadata, feeds the link-card fallback; `null` is fine.

## 2. Resolver service

`App\Services\VideoLinkResolver` — one entry point `resolve(string $url): VideoLinkResult`, behind a small interface (`App\Contracts\ResolvesVideoLinks` or similar) so Filament, the CSV importer, and tests share one seam.

Resolution chain:

1. **YouTube adapter** (app-owned): the existing regex patterns (moved out of `ImportTroves::extractYoutubeId()` into the adapter) extract the ID from `watch?v=`, `youtu.be/`, `embed/`, `shorts/`, `live/` forms and bare IDs; an oEmbed probe (`https://www.youtube.com/oembed?url=…`, no key) confirms embeddability and captures the title. 4xx ⇒ not embeddable (private/embed-disabled). `embed_url = https://www.youtube.com/embed/{id}`.
2. **EcoAgTube adapter** (app-owned): matches `ecoagtube.org` hosts; fetches the `/content/{slug}` page with a browser User-Agent; extracts the embed-modal iframe. Its src is either `ecoagtube.org/embed/{id}` (native ⇒ embeddable, that src is the `embed_url`) or a YouTube embed (YouTube-backed ⇒ delegate to the YouTube adapter for the oEmbed probe). No iframe found ⇒ `embeddable: false`, title from `og:title`.
3. **Generic fallback** (`embed/embed` package): any other host — covers Vimeo, PeerTube instances, and most oEmbed/OpenGraph-capable sites. We extract the iframe src from its embed code and accept only `https` iframe sources. No embed code ⇒ not embeddable, keep the metadata title. This is what handles AccessAgriculture — no dedicated adapter; it resolves to a titled link card.
4. Any network failure/timeout (5s cap per outbound call) or unparseable URL ⇒ `embeddable: false` with whatever data we have. Resolution failure never blocks saving.

Guards: `http`/`https` schemes only, no userinfo in URLs, outbound timeouts, browser User-Agent on all fetches. Editors are trusted roles (`canEdit()` gates the form), so full SSRF-proofing (private-IP blocking) is deliberately out of scope.

Adapters 1–2 use Laravel's `Http` client (trivially fakeable in tests); only the generic fallback touches `embed/embed`, and it sits behind the resolver interface so tests can fake the whole resolver at the seam.

## 3. Admin form (live resolution)

The `TranslatableComboField` repeater in `TroveResource` keeps its shape but each item becomes: a `url` TextInput (`live(onBlur: true)`) plus hidden fields for `provider`, `embed_url`, `embeddable`, `title`. `afterStateUpdated` calls the resolver and fills them; the item shows a status line — **Embeds** / **Link only — will show as a link card** / **Couldn't reach that URL**. Resolution errors never block typing or saving.

Defensive re-check on save: `mutateFormDataBeforeSave` (and the create-page equivalent) re-resolves any item whose `url` doesn't match its resolved state (covers paste-then-save-fast and programmatic writes).

Copy changes: heading "YouTube Videos" → "Videos"; hint → "Paste the video's share URL (YouTube, Vimeo, EcoAgTube, …)".

## 4. Public rendering

`trove.blade.php` delegates each entry to a new blade component (`<x-video-link :link="$link" />`):

- `embeddable` ⇒ the existing iframe treatment (same styling, `aspect-video`, rounded, `allowfullscreen`), src = stored `embed_url`.
- not embeddable ⇒ link card in the same slot: title (fallback: host name), host domain, "Watch on {host}" external-link button (`target="_blank" rel="noopener"`).

`Trove::getDownloadableLinks()` switches from building watch URLs out of IDs to using the stored `url` directly (works for every provider); link titles use the stored `title` when present.

## 5. Migration & data conversion

One migration: rename the column, then convert existing entries in place — `{youtube_id: X}` → `{url: https://www.youtube.com/watch?v=X, provider: youtube, embed_url: https://www.youtube.com/embed/X, embeddable: true, title: null}`. Fully offline (no HTTP in the migration), runs over all rows including shadow drafts (`withoutGlobalScopes()`), handles the legacy single-associative shape (`{youtube_id: X}` not wrapped in an array). Works on both MySQL and the SQLite test harness (conversion via Eloquent/PHP, not vendor-specific SQL).

All references to `youtube_links` get renamed: model casts/translatable list, factories, seeders (Example data), tests, docs.

## 6. CSV import

Column `youtube_url` becomes `video_url` (with `youtube_url` accepted as an alias for old files). The importer calls the resolver for each URL (network at import time). Dedupe keys: YouTube URLs keep the existing `yt:{id}` key (stays idempotent against previously-imported data); other providers key on normalised URL (`vid:{url}`). `--dry-run` skips resolution and reports "would resolve". `docs/import/README.md` updated.

## 7. Tests

- **Unit**: YouTube + EcoAgTube adapters against `Http::fake` fixtures (embeddable, embed-disabled 4xx, CDN 403, page without embed iframe, YouTube-backed EcoAgTube page); URL parsing edge cases; generic fallback behind a faked interface; `VideoLinkResult` shape.
- **Feature**: blade rendering (iframe for embeddable, link card for not, mixed lists); migration conversion of both legacy shapes; EditTrove save-path resolution via faked resolver; importer with `video_url` and the legacy `youtube_url` alias; `getDownloadableLinks()` with the new shape.

## Out of scope (deliberate)

- Collections get no video field.
- No re-resolution of stale embeddability (a future "refresh videos" action if ever needed).
- No search indexing of video data.
- No thumbnail capture for link cards.
- No SSRF hardening beyond scheme/timeout guards (editor-only surface).
