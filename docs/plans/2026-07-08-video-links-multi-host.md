# Multi-Host Video Links Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Completed
**Change log:** docs/change-logs/video-links-multi-host.md

**Spec:** `docs/superpowers/specs/2026-07-08-video-embedding-design.md`

**Goal:** Replace the YouTube-ID-only `youtube_links` field on Troves with a `video_links` field where editors paste any share URL (YouTube, Vimeo, EcoAgTube, …) and the system resolves it to an embedded player, falling back to a styled link card when the video can't be embedded.

**Architecture:** A `VideoLinkResolver` service (behind an `App\Contracts\ResolvesVideoLinks` interface) turns a URL into a `VideoLinkResult` record via a chain: app-owned YouTube adapter (regex + oEmbed probe), app-owned EcoAgTube adapter (page scrape — no oEmbed exists), then the `embed/embed` package as a generic fallback. Resolution happens in the admin form (live on blur + defensive re-check on save) and results are stored in the translatable `video_links` JSON column, so the public page renders with zero external calls.

**Tech Stack:** Laravel 13, PHP 8.3, Filament 5, Pest 4 (SQLite `:memory:`), Spatie Translatable, `embed/embed` v4 (new dependency), Guzzle (already installed).

## Global Constraints

- Follow the Spatie/Laravel guidelines already in use: happy path last, no `else`, no compound `&&` conditions, typed properties, constructor promotion, string interpolation, curly braces always, descriptive variable names (never `$e`, `$m` — the plan's regex captures use `$matches`).
- **No `private const`** — inline single-use literals (with a descriptive local variable if needed) or use a private property for multi-use values. This applies when touching `ImportTroves::FIXED_COLUMNS` (Task 8).
- Never store or render provider-supplied HTML. `embed_url` must always be an `https` URL we constructed or extracted ourselves.
- All outbound HTTP: 5-second timeout. Page fetches to Fastly-fronted sites (EcoAgTube) and the `Embed` client must send a browser User-Agent (their CDN returns 403 to default client UAs).
- Resolution failure must never block saving a trove — every failure path returns a non-embeddable `VideoLinkResult`.
- The stored record shape (one entry in a locale's `video_links` list) is: `{url: string, provider: ?string, embed_url: ?string, embeddable: bool, title: ?string, resolved_url: ?string}`. `resolved_url` is internal bookkeeping — the URL the resolution was computed from, used to detect stale resolutions on save (small addition to the spec's five-key shape).
- Tests: `php artisan test` (Pest, SQLite `:memory:`). Test-harness gotchas from CLAUDE.md apply: `usePublicContext()` for public-page tests, `bootPublicSite()` for the public layout, `Trove::withDrafts()` to see unpublished rows.
- `Model::unguard()` is global (AppServiceProvider), so factories/tests can set any attribute directly.
- Run `vendor/bin/pint --dirty` before each commit.
- Migrations: `up()` only, no `down()`.

---

### Task 1: `VideoLinkResult` DTO + `ResolvesVideoLinks` contract

**Files:**
- Create: `app/Support/VideoLink/VideoLinkResult.php`
- Create: `app/Contracts/ResolvesVideoLinks.php`
- Test: `tests/Unit/VideoLink/VideoLinkResultTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `VideoLinkResult::__construct(string $url, ?string $provider = null, ?string $embedUrl = null, bool $embeddable = false, ?string $title = null, ?string $resolvedUrl = null)`, `VideoLinkResult::toArray(): array` (snake_case keys: `url`, `provider`, `embed_url`, `embeddable`, `title`, `resolved_url`), and `interface ResolvesVideoLinks { public function resolve(string $url): VideoLinkResult; }`. Every later task depends on these exact names.

- [ ] **Step 1: Write the failing test**

`tests/Unit/VideoLink/VideoLinkResultTest.php`:

```php
<?php

use App\Support\VideoLink\VideoLinkResult;

it('serialises to the stored record shape with snake_case keys', function () {
    $result = new VideoLinkResult(
        url: 'https://youtu.be/q76bMs-NwRk',
        provider: 'youtube',
        embedUrl: 'https://www.youtube.com/embed/q76bMs-NwRk',
        embeddable: true,
        title: 'A video',
        resolvedUrl: 'https://youtu.be/q76bMs-NwRk',
    );

    expect($result->toArray())->toBe([
        'url' => 'https://youtu.be/q76bMs-NwRk',
        'provider' => 'youtube',
        'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
        'embeddable' => true,
        'title' => 'A video',
        'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
    ]);
});

it('defaults to a non-embeddable result with only a url', function () {
    $result = new VideoLinkResult(url: 'https://example.org/video');

    expect($result->toArray())->toBe([
        'url' => 'https://example.org/video',
        'provider' => null,
        'embed_url' => null,
        'embeddable' => false,
        'title' => null,
        'resolved_url' => null,
    ]);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=VideoLinkResultTest`
Expected: FAIL — `Class "App\Support\VideoLink\VideoLinkResult" not found`.

- [ ] **Step 3: Write the implementation**

`app/Support/VideoLink/VideoLinkResult.php`:

```php
<?php

namespace App\Support\VideoLink;

final readonly class VideoLinkResult
{
    public function __construct(
        public string $url,
        public ?string $provider = null,
        public ?string $embedUrl = null,
        public bool $embeddable = false,
        public ?string $title = null,
        public ?string $resolvedUrl = null,
    ) {
    }

    /** @return array{
     *     url: string,
     *     provider: ?string,
     *     embed_url: ?string,
     *     embeddable: bool,
     *     title: ?string,
     *     resolved_url: ?string
     * } */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'provider' => $this->provider,
            'embed_url' => $this->embedUrl,
            'embeddable' => $this->embeddable,
            'title' => $this->title,
            'resolved_url' => $this->resolvedUrl,
        ];
    }
}
```

`app/Contracts/ResolvesVideoLinks.php`:

```php
<?php

namespace App\Contracts;

use App\Support\VideoLink\VideoLinkResult;

interface ResolvesVideoLinks
{
    public function resolve(string $url): VideoLinkResult;
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=VideoLinkResultTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Support/VideoLink/VideoLinkResult.php app/Contracts/ResolvesVideoLinks.php tests/Unit/VideoLink/VideoLinkResultTest.php
git commit -m "Add VideoLinkResult DTO and ResolvesVideoLinks contract"
```

---

### Task 2: YouTube adapter

**Files:**
- Create: `app/Services/VideoLink/YouTubeAdapter.php`
- Test: `tests/Unit/VideoLink/YouTubeAdapterTest.php`

**Interfaces:**
- Consumes: `VideoLinkResult` (Task 1).
- Produces: `YouTubeAdapter::matches(string $url): bool`, `YouTubeAdapter::resolve(string $url): VideoLinkResult`, `YouTubeAdapter::extractId(string $url): ?string` (public static — the importer in Task 8 and the EcoAgTube adapter's tests rely on it). This adapter absorbs the regexes currently in `ImportTroves::extractYoutubeId()` (`app/Console/Commands/ImportTroves.php:425-443`) — do not delete the importer copy yet (Task 8 does).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/VideoLink/YouTubeAdapterTest.php`:

```php
<?php

use App\Services\VideoLink\YouTubeAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('extracts the video id from all common url forms and bare ids', function (string $input) {
    expect(YouTubeAdapter::extractId($input))->toBe('q76bMs-NwRk');
})->with([
    'q76bMs-NwRk',
    'https://www.youtube.com/watch?v=q76bMs-NwRk',
    'https://www.youtube.com/watch?feature=shared&v=q76bMs-NwRk',
    'https://youtu.be/q76bMs-NwRk',
    'https://youtu.be/q76bMs-NwRk?si=abc',
    'https://www.youtube.com/embed/q76bMs-NwRk',
    'https://www.youtube-nocookie.com/embed/q76bMs-NwRk',
    'https://www.youtube.com/shorts/q76bMs-NwRk',
    'https://www.youtube.com/live/q76bMs-NwRk',
]);

it('returns null for urls without an extractable id', function () {
    expect(YouTubeAdapter::extractId('https://www.youtube.com/@somechannel'))->toBeNull()
        ->and(YouTubeAdapter::extractId('https://example.org/watch?v=q76bMs-NwRk'))->toBeNull();
});

it('matches youtube hosts only', function () {
    $adapter = new YouTubeAdapter;

    expect($adapter->matches('https://www.youtube.com/watch?v=q76bMs-NwRk'))->toBeTrue()
        ->and($adapter->matches('https://youtu.be/q76bMs-NwRk'))->toBeTrue()
        ->and($adapter->matches('https://m.youtube.com/watch?v=q76bMs-NwRk'))->toBeTrue()
        ->and($adapter->matches('https://www.ecoagtube.org/content/some-video'))->toBeFalse()
        ->and($adapter->matches('https://vimeo.com/12345'))->toBeFalse();
});

it('resolves an embeddable video via the oembed probe', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(['title' => 'Making a seedbed'], 200),
    ]);

    $result = (new YouTubeAdapter)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeTrue()
        ->and($result->provider)->toBe('youtube')
        ->and($result->embedUrl)->toBe('https://www.youtube.com/embed/q76bMs-NwRk')
        ->and($result->title)->toBe('Making a seedbed')
        ->and($result->url)->toBe('https://youtu.be/q76bMs-NwRk')
        ->and($result->resolvedUrl)->toBe('https://youtu.be/q76bMs-NwRk');
});

it('marks embed-disabled or missing videos as not embeddable', function (int $status) {
    Http::fake(['https://www.youtube.com/oembed*' => Http::response('', $status)]);

    $result = (new YouTubeAdapter)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeFalse()
        ->and($result->embedUrl)->toBeNull()
        ->and($result->provider)->toBe('youtube');
})->with([400, 401, 403]);

it('marks the video as not embeddable when the probe cannot connect', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $result = (new YouTubeAdapter)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeFalse();
});

it('returns a not-embeddable result for youtube urls without an id', function () {
    Http::fake();

    $result = (new YouTubeAdapter)->resolve('https://www.youtube.com/@somechannel');

    expect($result->embeddable)->toBeFalse()
        ->and($result->provider)->toBe('youtube');
    Http::assertNothingSent();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=YouTubeAdapterTest`
Expected: FAIL — `Class "App\Services\VideoLink\YouTubeAdapter" not found`.

- [ ] **Step 3: Write the implementation**

`app/Services/VideoLink/YouTubeAdapter.php`:

```php
<?php

namespace App\Services\VideoLink;

use App\Support\VideoLink\VideoLinkResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class YouTubeAdapter
{
    public static function extractId(string $url): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }

        foreach ([
            '/youtube\.com\/watch\?.*v=([A-Za-z0-9_-]{11})/',
            '/youtu\.be\/([A-Za-z0-9_-]{11})/',
            '/youtube(?:-nocookie)?\.com\/embed\/([A-Za-z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([A-Za-z0-9_-]{11})/',
            '/youtube\.com\/live\/([A-Za-z0-9_-]{11})/',
        ] as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function matches(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $bareHost = preg_replace('/^(www|m)\./', '', $host);

        return in_array($bareHost, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true);
    }

    public function resolve(string $url): VideoLinkResult
    {
        $videoId = self::extractId($url);

        if ($videoId === null) {
            return new VideoLinkResult(url: $url, provider: 'youtube', resolvedUrl: $url);
        }

        $watchUrl = "https://www.youtube.com/watch?v={$videoId}";

        try {
            $response = Http::timeout(5)->get('https://www.youtube.com/oembed', [
                'url' => $watchUrl,
                'format' => 'json',
            ]);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, provider: 'youtube', resolvedUrl: $url);
        }

        if (! $response->successful()) {
            return new VideoLinkResult(url: $url, provider: 'youtube', resolvedUrl: $url);
        }

        return new VideoLinkResult(
            url: $url,
            provider: 'youtube',
            embedUrl: "https://www.youtube.com/embed/{$videoId}",
            embeddable: true,
            title: $response->json('title'),
            resolvedUrl: $url,
        );
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=YouTubeAdapterTest`
Expected: PASS (all tests, including the datasets).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/VideoLink/YouTubeAdapter.php tests/Unit/VideoLink/YouTubeAdapterTest.php
git commit -m "Add YouTube video link adapter with oEmbed embeddability probe"
```

---

### Task 3: EcoAgTube adapter

**Files:**
- Create: `app/Services/VideoLink/EcoAgTubeAdapter.php`
- Test: `tests/Unit/VideoLink/EcoAgTubeAdapterTest.php`

**Interfaces:**
- Consumes: `VideoLinkResult` (Task 1), `YouTubeAdapter` (Task 2, constructor-injected).
- Produces: `EcoAgTubeAdapter::matches(string $url): bool`, `EcoAgTubeAdapter::resolve(string $url): VideoLinkResult`, `EcoAgTubeAdapter::browserUserAgent(): string` (public static — Task 4's `Embed` container binding reuses it).

**Background (from live research, 2026-07-08):** EcoAgTube is Drupal 10 with no oEmbed. Every embeddable video's page contains a share-modal iframe: natively-hosted videos get `<iframe ... src="https://www.ecoagtube.org/embed/{numericId}">`; YouTube-backed videos get a raw YouTube embed iframe. Their CDN (Fastly) returns 403 to non-browser User-Agents. No iframe in the page ⇒ not embeddable.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/VideoLink/EcoAgTubeAdapterTest.php`:

```php
<?php

use App\Services\VideoLink\EcoAgTubeAdapter;
use App\Services\VideoLink\YouTubeAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function ecoAgTubeAdapter(): EcoAgTubeAdapter
{
    return new EcoAgTubeAdapter(new YouTubeAdapter);
}

function ecoAgTubePage(string $body): string
{
    return "<html><head><meta property=\"og:title\" content=\"Biofertilizer formulation\" /></head><body>{$body}</body></html>";
}

it('matches ecoagtube.org urls only', function () {
    expect(ecoAgTubeAdapter()->matches('https://www.ecoagtube.org/content/biofertilizer-formulation-1'))->toBeTrue()
        ->and(ecoAgTubeAdapter()->matches('https://ecoagtube.org/content/some-video'))->toBeTrue()
        ->and(ecoAgTubeAdapter()->matches('https://www.youtube.com/watch?v=q76bMs-NwRk'))->toBeFalse();
});

it('resolves a natively-hosted video from the embed modal iframe', function () {
    Http::fake([
        'https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(
            '<textarea id="embed-share-video"><iframe width="560" height="315" src="https://www.ecoagtube.org/embed/32021"></iframe></textarea>'
        )),
    ]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/biofertilizer-formulation-1');

    expect($result->embeddable)->toBeTrue()
        ->and($result->provider)->toBe('ecoagtube')
        ->and($result->embedUrl)->toBe('https://www.ecoagtube.org/embed/32021')
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('sends a browser user agent when fetching the page', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(''))]);

    ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/some-video');

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla/5.0'));
});

it('delegates youtube-backed videos to the youtube oembed probe', function () {
    Http::fake([
        'https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(
            '<iframe src="https://www.youtube.com/embed/q76bMs-NwRk?rel=0"></iframe>'
        )),
        'https://www.youtube.com/oembed*' => Http::response(['title' => 'YouTube title'], 200),
    ]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/green-tv-live');

    expect($result->embeddable)->toBeTrue()
        ->and($result->provider)->toBe('ecoagtube')
        ->and($result->embedUrl)->toBe('https://www.youtube.com/embed/q76bMs-NwRk')
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('marks youtube-backed videos not embeddable when the oembed probe fails', function () {
    Http::fake([
        'https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage(
            '<iframe src="https://www.youtube.com/embed/q76bMs-NwRk"></iframe>'
        )),
        'https://www.youtube.com/oembed*' => Http::response('', 401),
    ]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/green-tv-live');

    expect($result->embeddable)->toBeFalse()
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('falls back to a titled link when the page has no embed iframe', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response(ecoAgTubePage('<p>no embed here</p>'))]);

    $result = ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/restricted-video');

    expect($result->embeddable)->toBeFalse()
        ->and($result->provider)->toBe('ecoagtube')
        ->and($result->embedUrl)->toBeNull()
        ->and($result->title)->toBe('Biofertilizer formulation');
});

it('falls back to a plain link on http errors or connection failures', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response('Forbidden', 403)]);

    expect(ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/x')->embeddable)->toBeFalse();

    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(ecoAgTubeAdapter()->resolve('https://www.ecoagtube.org/content/x')->embeddable)->toBeFalse();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=EcoAgTubeAdapterTest`
Expected: FAIL — `Class "App\Services\VideoLink\EcoAgTubeAdapter" not found`.

- [ ] **Step 3: Write the implementation**

`app/Services/VideoLink/EcoAgTubeAdapter.php`:

```php
<?php

namespace App\Services\VideoLink;

use App\Support\VideoLink\VideoLinkResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class EcoAgTubeAdapter
{
    public function __construct(private YouTubeAdapter $youTube)
    {
    }

    /**
     * EcoAgTube (and the Embed fallback client) sit behind CDNs that 403 non-browser
     * user agents, so all page fetches identify as a real browser.
     */
    public static function browserUserAgent(): string
    {
        return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
    }

    public function matches(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host) === 'ecoagtube.org';
    }

    public function resolve(string $url): VideoLinkResult
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::browserUserAgent()])
                ->timeout(5)
                ->get($url);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, provider: 'ecoagtube', resolvedUrl: $url);
        }

        if (! $response->successful()) {
            return new VideoLinkResult(url: $url, provider: 'ecoagtube', resolvedUrl: $url);
        }

        $html = $response->body();
        $title = $this->extractOgTitle($html);

        if (preg_match('/<iframe[^>]+src="(https:\/\/(?:www\.)?ecoagtube\.org\/embed\/\d+)"/i', $html, $matches)) {
            return new VideoLinkResult(
                url: $url,
                provider: 'ecoagtube',
                embedUrl: $matches[1],
                embeddable: true,
                title: $title,
                resolvedUrl: $url,
            );
        }

        if (preg_match('/<iframe[^>]+src="(https:\/\/www\.youtube(?:-nocookie)?\.com\/embed\/[A-Za-z0-9_-]{11})/i', $html, $matches)) {
            $youTubeResult = $this->youTube->resolve($matches[1]);

            return new VideoLinkResult(
                url: $url,
                provider: 'ecoagtube',
                embedUrl: $youTubeResult->embeddable ? $youTubeResult->embedUrl : null,
                embeddable: $youTubeResult->embeddable,
                title: $title ?? $youTubeResult->title,
                resolvedUrl: $url,
            );
        }

        return new VideoLinkResult(url: $url, provider: 'ecoagtube', title: $title, resolvedUrl: $url);
    }

    private function extractOgTitle(string $html): ?string
    {
        if (! preg_match('/<meta\s+property="og:title"\s+content="([^"]*)"/i', $html, $matches)) {
            return null;
        }

        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $title === '' ? null : $title;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=EcoAgTubeAdapterTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/VideoLink/EcoAgTubeAdapter.php tests/Unit/VideoLink/EcoAgTubeAdapterTest.php
git commit -m "Add EcoAgTube video link adapter with page-scrape embed detection"
```

---

### Task 4: Generic fallback adapter (`embed/embed`) + `VideoLinkResolver` + container binding

**Files:**
- Modify: `composer.json` (via `composer require embed/embed`)
- Create: `app/Services/VideoLink/GenericVideoAdapter.php`
- Create: `app/Services/VideoLinkResolver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register bindings)
- Test: `tests/Unit/VideoLink/GenericVideoAdapterTest.php`, `tests/Unit/VideoLink/VideoLinkResolverTest.php`

**Interfaces:**
- Consumes: `VideoLinkResult`, `ResolvesVideoLinks` (Task 1), `YouTubeAdapter` (Task 2), `EcoAgTubeAdapter` (Task 3), `Embed\Embed` + `Embed\Http\Crawler` (new package), `GuzzleHttp\Client` + `Http\Factory\Guzzle\{RequestFactory, UriFactory}` (already installed).
- Produces: `GenericVideoAdapter::__construct(Embed $embed)`, `GenericVideoAdapter::resolve(string $url): VideoLinkResult`; `VideoLinkResolver implements ResolvesVideoLinks` with `__construct(YouTubeAdapter $youTube, EcoAgTubeAdapter $ecoAgTube, GenericVideoAdapter $generic)`; container binding `ResolvesVideoLinks::class → VideoLinkResolver::class` so later tasks type-hint the contract. This is the single place URL-safety guards live (scheme, userinfo).

- [ ] **Step 1: Install the package**

Run: `composer require embed/embed`
Expected: installs `embed/embed` v4.x with no dependency conflicts (requires `php ^7.4|^8`; the project's Guzzle + `http-interop/http-factory-guzzle` satisfy its PSR-17/18 needs).

- [ ] **Step 2: Write the failing tests**

`tests/Unit/VideoLink/GenericVideoAdapterTest.php` — drive `Embed` through a Guzzle mock handler so no real HTTP happens. `embed/embed` may request the page and any discovered oEmbed endpoint, so queue enough responses:

```php
<?php

use App\Services\VideoLink\GenericVideoAdapter;
use Embed\Embed;
use Embed\Http\Crawler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;

function genericAdapterWithResponses(Response ...$responses): GenericVideoAdapter
{
    $handler = HandlerStack::create(new MockHandler($responses));
    $guzzle = new GuzzleClient(['handler' => $handler]);

    return new GenericVideoAdapter(new Embed(new Crawler($guzzle, new RequestFactory, new UriFactory)));
}

it('resolves an embeddable video from an oembed-discoverable page', function () {
    $page = <<<'HTML'
    <html><head>
        <title>A Vimeo video</title>
        <link rel="alternate" type="application/json+oembed" href="https://example.org/oembed?url=x" title="oEmbed">
    </head><body></body></html>
    HTML;

    $oembed = json_encode([
        'type' => 'video',
        'version' => '1.0',
        'title' => 'A Vimeo video',
        'html' => '<iframe src="https://player.example.org/video/123" width="640" height="360"></iframe>',
    ]);

    $adapter = genericAdapterWithResponses(
        new Response(200, ['Content-Type' => 'text/html'], $page),
        new Response(200, ['Content-Type' => 'application/json'], $oembed),
    );

    $result = $adapter->resolve('https://example.org/videos/123');

    expect($result->embeddable)->toBeTrue()
        ->and($result->embedUrl)->toBe('https://player.example.org/video/123')
        ->and($result->title)->toBe('A Vimeo video');
});

it('falls back to a titled link for pages without any embed code', function () {
    $page = <<<'HTML'
    <html><head>
        <title>Crop rotation with legumes | Access Agriculture</title>
        <meta property="og:title" content="Crop rotation with legumes">
    </head><body>no embed here</body></html>
    HTML;

    $adapter = genericAdapterWithResponses(
        new Response(200, ['Content-Type' => 'text/html'], $page),
        new Response(200, ['Content-Type' => 'text/html'], $page),
    );

    $result = $adapter->resolve('https://www.accessagriculture.org/crop-rotation-legumes');

    expect($result->embeddable)->toBeFalse()
        ->and($result->embedUrl)->toBeNull()
        ->and($result->title)->toContain('Crop rotation');
});

it('rejects non-https iframe sources', function () {
    $page = <<<'HTML'
    <html><head>
        <link rel="alternate" type="application/json+oembed" href="https://example.org/oembed?url=x">
    </head><body></body></html>
    HTML;

    $oembed = json_encode([
        'type' => 'video',
        'version' => '1.0',
        'html' => '<iframe src="http://insecure.example.org/video/123"></iframe>',
    ]);

    $adapter = genericAdapterWithResponses(
        new Response(200, ['Content-Type' => 'text/html'], $page),
        new Response(200, ['Content-Type' => 'application/json'], $oembed),
    );

    expect($adapter->resolve('https://example.org/videos/123')->embeddable)->toBeFalse();
});

it('falls back to a plain link when the fetch fails entirely', function () {
    $adapter = genericAdapterWithResponses(new Response(403, [], 'Forbidden'));

    $result = $adapter->resolve('https://example.org/videos/123');

    expect($result->embeddable)->toBeFalse()
        ->and($result->url)->toBe('https://example.org/videos/123');
});
```

`tests/Unit/VideoLink/VideoLinkResolverTest.php`:

```php
<?php

use App\Contracts\ResolvesVideoLinks;
use App\Services\VideoLinkResolver;
use App\Support\VideoLink\VideoLinkResult;
use Illuminate\Support\Facades\Http;

it('is bound to the contract in the container', function () {
    expect(app(ResolvesVideoLinks::class))->toBeInstanceOf(VideoLinkResolver::class);
});

it('rejects unresolvable or unsafe urls without any http call', function (string $badUrl) {
    Http::fake();

    $result = app(ResolvesVideoLinks::class)->resolve($badUrl);

    expect($result->embeddable)->toBeFalse()
        ->and($result->provider)->toBeNull();
    Http::assertNothingSent();
})->with([
    'not a url at all',
    'ftp://example.org/video.mp4',
    'https://user:secret@example.org/video',
]);

it('routes youtube urls to the youtube adapter', function () {
    Http::fake(['https://www.youtube.com/oembed*' => Http::response(['title' => 'Yt'], 200)]);

    $result = app(ResolvesVideoLinks::class)->resolve('  https://youtu.be/q76bMs-NwRk  ');

    expect($result->provider)->toBe('youtube')
        ->and($result->embeddable)->toBeTrue()
        ->and($result->url)->toBe('https://youtu.be/q76bMs-NwRk');
});

it('routes ecoagtube urls to the ecoagtube adapter', function () {
    Http::fake(['https://www.ecoagtube.org/*' => Http::response('<html></html>', 200)]);

    $result = app(ResolvesVideoLinks::class)->resolve('https://www.ecoagtube.org/content/some-video');

    expect($result->provider)->toBe('ecoagtube');
});

it('returns a plain-link result when an adapter throws unexpectedly', function () {
    Http::fake(fn () => throw new RuntimeException('boom'));

    $result = app(ResolvesVideoLinks::class)->resolve('https://youtu.be/q76bMs-NwRk');

    expect($result->embeddable)->toBeFalse()
        ->and($result->url)->toBe('https://youtu.be/q76bMs-NwRk');
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter="GenericVideoAdapterTest|VideoLinkResolverTest"`
Expected: FAIL — classes not found / contract not bound.

- [ ] **Step 4: Write the implementations**

`app/Services/VideoLink/GenericVideoAdapter.php`:

```php
<?php

namespace App\Services\VideoLink;

use App\Support\VideoLink\VideoLinkResult;
use Embed\Embed;
use Throwable;

class GenericVideoAdapter
{
    public function __construct(private Embed $embed)
    {
    }

    public function resolve(string $url): VideoLinkResult
    {
        try {
            $info = $this->embed->get($url);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }

        $provider = strtolower((string) $info->providerName) ?: null;
        $title = $info->title;
        $embedUrl = $this->extractIframeSrc((string) $info->code?->html);

        if ($embedUrl === null) {
            return new VideoLinkResult(url: $url, provider: $provider, title: $title, resolvedUrl: $url);
        }

        return new VideoLinkResult(
            url: $url,
            provider: $provider,
            embedUrl: $embedUrl,
            embeddable: true,
            title: $title,
            resolvedUrl: $url,
        );
    }

    private function extractIframeSrc(string $embedHtml): ?string
    {
        if (! preg_match('/<iframe[^>]+src="(https:\/\/[^"]+)"/i', $embedHtml, $matches)) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }
}
```

`app/Services/VideoLinkResolver.php`:

```php
<?php

namespace App\Services;

use App\Contracts\ResolvesVideoLinks;
use App\Services\VideoLink\EcoAgTubeAdapter;
use App\Services\VideoLink\GenericVideoAdapter;
use App\Services\VideoLink\YouTubeAdapter;
use App\Support\VideoLink\VideoLinkResult;
use Throwable;

class VideoLinkResolver implements ResolvesVideoLinks
{
    public function __construct(
        private YouTubeAdapter $youTube,
        private EcoAgTubeAdapter $ecoAgTube,
        private GenericVideoAdapter $generic,
    ) {
    }

    public function resolve(string $url): VideoLinkResult
    {
        $url = trim($url);

        if (! $this->isResolvableUrl($url)) {
            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }

        try {
            if ($this->youTube->matches($url)) {
                return $this->youTube->resolve($url);
            }

            if ($this->ecoAgTube->matches($url)) {
                return $this->ecoAgTube->resolve($url);
            }

            return $this->generic->resolve($url);
        } catch (Throwable) {
            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }
    }

    private function isResolvableUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user'])) {
            return false;
        }

        if (isset($parts['pass'])) {
            return false;
        }

        return true;
    }
}
```

In `app/Providers/AppServiceProvider.php`, add to the `register()` method (create the method if the class only has `boot()`), with the matching `use` statements at the top of the file (`App\Contracts\ResolvesVideoLinks`, `App\Services\VideoLink\EcoAgTubeAdapter`, `App\Services\VideoLinkResolver`, `Embed\Embed`, `Embed\Http\Crawler`, `GuzzleHttp\Client as GuzzleClient`, `Http\Factory\Guzzle\RequestFactory`, `Http\Factory\Guzzle\UriFactory`):

```php
$this->app->bind(Embed::class, function () {
    $httpClient = new GuzzleClient([
        'timeout' => 5,
        'headers' => ['User-Agent' => EcoAgTubeAdapter::browserUserAgent()],
    ]);

    return new Embed(new Crawler($httpClient, new RequestFactory, new UriFactory));
});

$this->app->bind(ResolvesVideoLinks::class, VideoLinkResolver::class);
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter="GenericVideoAdapterTest|VideoLinkResolverTest"`
Expected: PASS. If a `GenericVideoAdapterTest` case fails on response ordering, inspect which requests `embed/embed` actually issued (add a Guzzle history middleware temporarily) and adjust the mock queue — the adapter code itself should not need changing for that.

- [ ] **Step 6: Run the full suite to catch regressions**

Run: `php artisan test`
Expected: PASS — nothing else touches these classes yet.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add composer.json composer.lock app/Services/VideoLink/GenericVideoAdapter.php app/Services/VideoLinkResolver.php app/Providers/AppServiceProvider.php tests/Unit/VideoLink/GenericVideoAdapterTest.php tests/Unit/VideoLink/VideoLinkResolverTest.php
git commit -m "Add generic embed/embed fallback and VideoLinkResolver service"
```

---

### Task 5: Rename `youtube_links` → `video_links` (migration, data conversion, all references)

**Files:**
- Create: `app/Support/VideoLink/LegacyYoutubeLinksConverter.php`
- Create: `database/migrations/2026_07_08_000000_rename_youtube_links_to_video_links_on_troves.php` (use the real current timestamp from `date +%Y_%m_%d_%H%M%S`)
- Modify: `app/Models/Trove.php:44` (cast), `:64` (translatable), `:462-489` (`getDownloadableLinks`)
- Modify: `database/factories/TroveFactory.php:30`
- Modify: `database/seeders/Example/ExampleDataSeeder.php:78-120`
- Modify: `app/Console/Commands/ImportTroves.php` (mechanical rename only — full multi-host import is Task 8)
- Modify: `resources/views/trove.blade.php:124-158` (minimal rename; the styled component comes in Task 6)
- Test: `tests/Unit/VideoLink/LegacyYoutubeLinksConverterTest.php`; update `tests/Unit/Models/Trove/DownloadLinksTest.php`, `tests/Feature/Http/DownloadTest.php:15`, `tests/Feature/Console/ImportTrovesCommandTest.php:78-87`

**Interfaces:**
- Consumes: `YouTubeAdapter::extractId()` (Task 2).
- Produces: the `video_links` column/attribute in the new record shape everywhere; `LegacyYoutubeLinksConverter::convertLocaleEntries(mixed $entries): array` and `LegacyYoutubeLinksConverter::convertTranslations(mixed $translations): ?array` (the migration and the importer both call `convertLocaleEntries`).

- [ ] **Step 1: Write the failing converter test**

`tests/Unit/VideoLink/LegacyYoutubeLinksConverterTest.php`:

```php
<?php

use App\Support\VideoLink\LegacyYoutubeLinksConverter;

function expectedRecord(string $videoId): array
{
    return [
        'url' => "https://www.youtube.com/watch?v={$videoId}",
        'provider' => 'youtube',
        'embed_url' => "https://www.youtube.com/embed/{$videoId}",
        'embeddable' => true,
        'title' => null,
        'resolved_url' => "https://www.youtube.com/watch?v={$videoId}",
    ];
}

it('converts a list of legacy youtube_id entries', function () {
    $converted = LegacyYoutubeLinksConverter::convertLocaleEntries([
        ['youtube_id' => 'q76bMs-NwRk'],
        ['youtube_id' => 'xNN7iTA57jM'],
    ]);

    expect($converted)->toBe([expectedRecord('q76bMs-NwRk'), expectedRecord('xNN7iTA57jM')]);
});

it('converts the legacy single-assoc shape', function () {
    expect(LegacyYoutubeLinksConverter::convertLocaleEntries(['youtube_id' => 'q76bMs-NwRk']))
        ->toBe([expectedRecord('q76bMs-NwRk')]);
});

it('passes through entries already in the new shape and drops empty ones', function () {
    $newShape = expectedRecord('q76bMs-NwRk');

    expect(LegacyYoutubeLinksConverter::convertLocaleEntries([$newShape, ['youtube_id' => ''], 'junk']))
        ->toBe([$newShape]);
});

it('converts a whole translations dictionary and drops empty locales', function () {
    $converted = LegacyYoutubeLinksConverter::convertTranslations([
        'en' => [['youtube_id' => 'q76bMs-NwRk']],
        'fr' => [],
    ]);

    expect($converted)->toBe(['en' => [expectedRecord('q76bMs-NwRk')]]);
});

it('returns null for non-array or fully-empty input', function () {
    expect(LegacyYoutubeLinksConverter::convertTranslations(null))->toBeNull()
        ->and(LegacyYoutubeLinksConverter::convertTranslations(['en' => []]))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=LegacyYoutubeLinksConverterTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the converter**

`app/Support/VideoLink/LegacyYoutubeLinksConverter.php`:

```php
<?php

namespace App\Support\VideoLink;

class LegacyYoutubeLinksConverter
{
    /**
     * Convert one locale's legacy youtube_links value — a list of ['youtube_id' => id]
     * entries or a bare single ['youtube_id' => id] — into video_links records.
     * Entries already in the new shape pass through untouched.
     */
    public static function convertLocaleEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        if (isset($entries['youtube_id'])) {
            $entries = [$entries];
        }

        $converted = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (isset($entry['url'])) {
                $converted[] = $entry;

                continue;
            }

            $videoId = $entry['youtube_id'] ?? null;
            if (! $videoId) {
                continue;
            }

            $watchUrl = "https://www.youtube.com/watch?v={$videoId}";
            $converted[] = [
                'url' => $watchUrl,
                'provider' => 'youtube',
                'embed_url' => "https://www.youtube.com/embed/{$videoId}",
                'embeddable' => true,
                'title' => null,
                'resolved_url' => $watchUrl,
            ];
        }

        return $converted;
    }

    public static function convertTranslations(mixed $translations): ?array
    {
        if (! is_array($translations)) {
            return null;
        }

        $converted = [];
        foreach ($translations as $locale => $entries) {
            $localeEntries = self::convertLocaleEntries($entries);

            if ($localeEntries) {
                $converted[$locale] = $localeEntries;
            }
        }

        return $converted ?: null;
    }
}
```

- [ ] **Step 4: Run the converter test to verify it passes**

Run: `php artisan test --filter=LegacyYoutubeLinksConverterTest`
Expected: PASS.

- [ ] **Step 5: Write the migration**

Create the migration (name via `php artisan make:migration rename_youtube_links_to_video_links_on_troves --table=troves`, then replace its contents):

```php
<?php

use App\Support\VideoLink\LegacyYoutubeLinksConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('troves', function (Blueprint $table) {
            $table->renameColumn('youtube_links', 'video_links');
        });

        DB::table('troves')
            ->whereNotNull('video_links')
            ->orderBy('id')
            ->chunkById(100, function ($troves) {
                foreach ($troves as $trove) {
                    $converted = LegacyYoutubeLinksConverter::convertTranslations(
                        json_decode($trove->video_links, true)
                    );

                    DB::table('troves')->where('id', $trove->id)->update([
                        'video_links' => $converted === null ? null : json_encode($converted),
                    ]);
                }
            });
    }
};
```

Notes: `DB::table()` deliberately bypasses model events, the `PublishedScope`, soft-delete filtering and Scout syncing, so canonical rows, shadow drafts and trashed rows all convert. `renameColumn` works natively on both MySQL and the SQLite test harness in Laravel 13.

- [ ] **Step 6: Rename every remaining `youtube_links` reference**

Run `grep -rn "youtube_links\|youtube_id" app/ database/ resources/ tests/ --include="*.php" --include="*.blade.php"` and apply this mapping (the converter and migration just written are the only legitimate remaining `youtube_id` users):

1. `app/Models/Trove.php:44` — cast `'youtube_links' => 'array'` → `'video_links' => 'array'`.
2. `app/Models/Trove.php:64` — translatable entry `'youtube_links'` → `'video_links'`.
3. `app/Models/Trove.php:462-489` — replace the YouTube block of `getDownloadableLinks()`:

```php
$videoLinks = $this->getTranslation('video_links', $locale) ?? [];

foreach ($videoLinks as $link) {
    if (empty($link['url'])) {
        continue;
    }

    $links->push([
        'title' => $link['title'] ?? 'Video',
        'url' => $link['url'],
    ]);
}
```

(Delete the old `$youtubeLinks` normalisation and push loop; keep the `external_links` handling untouched.)

4. `database/factories/TroveFactory.php:30` — `'youtube_links' => null` → `'video_links' => null`.
5. `database/seeders/Example/ExampleDataSeeder.php` — replace each `'youtube_links' => ['en' => [['youtube_id' => X]]]` with the new shape via the converter, e.g.:

```php
'video_links' => ['en' => LegacyYoutubeLinksConverter::convertLocaleEntries([['youtube_id' => 'q76bMs-NwRk']])],
```

(add `use App\Support\VideoLink\LegacyYoutubeLinksConverter;`), and line 120's `$trove->youtube_links = $data['youtube_links'] ?? null;` → `$trove->video_links = $data['video_links'] ?? null;`.

6. `app/Console/Commands/ImportTroves.php` — mechanical rename to keep the importer compiling and idempotent (full multi-host support is Task 8):
   - Line 253: `->get(['id', 'external_links', 'youtube_links'])` → `->get(['id', 'external_links', 'video_links'])`.
   - Lines 262-268: scan `video_links` and key on the URL's extracted ID (add `use App\Services\VideoLink\YouTubeAdapter;`):

```php
foreach ($trove->getTranslations('video_links') as $links) {
    foreach ($this->normaliseLinkList($links) as $link) {
        $videoId = YouTubeAdapter::extractId((string) ($link['url'] ?? ''));
        if ($videoId !== null) {
            $this->seenSourceKeys["yt:{$videoId}"] = $trove->id;
        }
    }
}
```

   - `normaliseLinkList()` (line 276-283): extend the single-assoc probe to the new shape: `return (isset($links['link_url']) || isset($links['youtube_id']) || isset($links['url'])) ? [$links] : $links;`
   - `buildPlan()` line 408-410: build the new shape offline via the converter (add `use App\Support\VideoLink\LegacyYoutubeLinksConverter;`):

```php
'video_links' => $youtubeId !== null
    ? [$primaryLocale => LegacyYoutubeLinksConverter::convertLocaleEntries([['youtube_id' => $youtubeId]])]
    : null,
```

   - In `executePlan()` (further down the file), rename the `$row['youtube_links']` usage to `$row['video_links']` (grep for it).
7. `resources/views/trove.blade.php:124-130` — minimal rename (Task 6 replaces this block again with the component):

```php
$videoLinks = collect($resource->getTranslation('video_links', app()->getLocale()) ?? [])
    ->filter(fn ($link) => is_array($link) && ! empty($link['url']))
    ->values();
```

Lines 140 and 146-158: replace `$youtubeLinks` checks with `$videoLinks->isNotEmpty()`, and the iframe loop with:

```blade
@if($videoLinks->isNotEmpty())
    <div class="mb-8 space-y-4">
        @foreach($videoLinks as $link)
            @if(($link['embeddable'] ?? false) && ! empty($link['embed_url']))
                <div class="rounded-2xl overflow-hidden shadow-sm max-w-2xl mx-auto">
                    <iframe class="w-full aspect-video" src="{{ $link['embed_url'] }}"
                        frameborder="0" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            @endif
        @endforeach
    </div>
@endif
```

- [ ] **Step 7: Update the existing tests to the new shape**

1. `tests/Unit/Models/Trove/DownloadLinksTest.php:48-61` — replace the YouTube test:

```php
it('uses stored video link urls and titles for the manifest', function () {
    $trove = makeTroveWithLinks(['video_links' => ['en' => [
        [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/abc123',
            'embeddable' => true,
            'title' => 'Named video',
            'resolved_url' => 'https://www.youtube.com/watch?v=abc123',
        ],
        [
            'url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
            'provider' => null,
            'embed_url' => null,
            'embeddable' => false,
            'title' => null,
            'resolved_url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
        ],
    ]]]);

    expect(downloadableLinks($trove)->all())->toBe([
        ['title' => 'Named video', 'url' => 'https://www.youtube.com/watch?v=abc123'],
        ['title' => 'Video', 'url' => 'https://www.accessagriculture.org/crop-rotation-legumes'],
    ]);
});
```

Also update the file's header comment (`youtube_links` → `video_links`).
2. `tests/Feature/Http/DownloadTest.php:15` — `$emptyLinks = ['external_links' => ['en' => []], 'video_links' => ['en' => []]];` (and the comment above it).
3. `tests/Feature/Console/ImportTrovesCommandTest.php:87` — the assertion becomes:

```php
->and($trove->getTranslation('video_links', 'en'))->toBe([[
    'url' => 'https://www.youtube.com/watch?v=q76bMs-NwRk',
    'provider' => 'youtube',
    'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
    'embeddable' => true,
    'title' => null,
    'resolved_url' => 'https://www.youtube.com/watch?v=q76bMs-NwRk',
]]);
```

(The CSV header in the test stays `youtube_url` for now — Task 8 renames the column and adds the alias.)

- [ ] **Step 8: Run the migration and full test suite**

Run: `php artisan migrate` (against the local MySQL dev DB — verifies `renameColumn` + conversion on real data), then `php artisan test`.
Expected: migration runs cleanly; full suite PASSES. If any test still references `youtube_links`, the grep in Step 6 missed it — fix and re-run.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "Rename youtube_links to video_links with in-place data conversion"
```

---

### Task 6: Public rendering — `<x-video-link>` component with link-card fallback

**Files:**
- Create: `resources/views/components/video-link.blade.php`
- Modify: `resources/views/trove.blade.php` (replace Task 5's interim iframe block)
- Test: `tests/Feature/Http/TroveVideoRenderingTest.php`

**Interfaces:**
- Consumes: the stored `video_links` record shape (Task 5), test helpers `usePublicContext()` / `bootPublicSite()` from `tests/Pest.php`.
- Produces: `<x-video-link :link="$array" />` — renders an iframe when `embeddable` + `embed_url`, a link card when only `url`, nothing when the array has no `url`.

- [ ] **Step 1: Write the failing feature test**

`tests/Feature/Http/TroveVideoRenderingTest.php` (check how `tests/Feature/Http/DownloadTest.php` creates published troves and boots the public site, and mirror that setup exactly):

```php
<?php

use App\Models\Trove;

usePublicContext();

beforeEach(function () {
    bootPublicSite();
});

function publishedTroveWithVideoLinks(array $links): Trove
{
    return Trove::factory()->create([
        'published_at' => now(),
        'video_links' => ['en' => $links],
    ]);
}

it('renders an iframe for an embeddable video link', function () {
    $trove = publishedTroveWithVideoLinks([[
        'url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
        'provider' => 'ecoagtube',
        'embed_url' => 'https://www.ecoagtube.org/embed/32021',
        'embeddable' => true,
        'title' => 'Biofertilizer formulation',
        'resolved_url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
    ]]);

    $this->get("/resources/{$trove->slug}")
        ->assertOk()
        ->assertSee('https://www.ecoagtube.org/embed/32021', false);
});

it('renders a link card for a non-embeddable video link', function () {
    $trove = publishedTroveWithVideoLinks([[
        'url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
        'provider' => null,
        'embed_url' => null,
        'embeddable' => false,
        'title' => 'Crop rotation with legumes',
        'resolved_url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
    ]]);

    $response = $this->get("/resources/{$trove->slug}");

    $response->assertOk()
        ->assertSee('Crop rotation with legumes')
        ->assertSee('accessagriculture.org')
        ->assertSee('https://www.accessagriculture.org/crop-rotation-legumes', false)
        ->assertDontSee('<iframe', false);
});

it('renders a mixed list with both treatments and skips urlless entries', function () {
    $trove = publishedTroveWithVideoLinks([
        [
            'url' => 'https://youtu.be/q76bMs-NwRk',
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
            'embeddable' => true,
            'title' => null,
            'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
        ],
        ['url' => '', 'embeddable' => false],
        [
            'url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
            'provider' => null,
            'embed_url' => null,
            'embeddable' => false,
            'title' => null,
            'resolved_url' => 'https://www.accessagriculture.org/crop-rotation-legumes',
        ],
    ]);

    $this->get("/resources/{$trove->slug}")
        ->assertOk()
        ->assertSee('https://www.youtube.com/embed/q76bMs-NwRk', false)
        ->assertSee('accessagriculture.org');
});
```

If `Trove::factory()->create()` needs more attributes to render the public page (e.g. a trove type), copy the factory setup from an existing passing public-page test rather than inventing one.

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=TroveVideoRenderingTest`
Expected: the link-card test FAILS (no card markup yet); the iframe test may already pass from Task 5's interim block.

- [ ] **Step 3: Create the component and swap it into the page**

`resources/views/components/video-link.blade.php`:

```blade
@props(['link'])

@php
    $url = $link['url'] ?? null;
    $embedUrl = ($link['embeddable'] ?? false) ? ($link['embed_url'] ?? null) : null;
    $host = $url ? preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)) : null;
@endphp

@if($embedUrl)
    <div class="rounded-2xl overflow-hidden shadow-sm max-w-2xl mx-auto">
        <iframe class="w-full aspect-video" src="{{ $embedUrl }}"
            frameborder="0" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
@elseif($url)
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
        class="flex items-center justify-between p-4 rounded-2xl bg-white shadow-sm border border-gray-100 hover:border-brand-primary transition group max-w-2xl mx-auto">
        <div class="flex items-center gap-3 min-w-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 shrink-0 text-gray-400 group-hover:text-brand-primary transition">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z"/>
            </svg>
            <div class="min-w-0">
                <p class="font-medium truncate">{{ $link['title'] ?? $host }}</p>
                <p class="text-sm text-gray-500 truncate">{{ $host }}</p>
            </div>
        </div>
        <span class="text-sm text-gray-500 group-hover:text-brand-primary whitespace-nowrap ml-4">{{ t('Watch on') }} {{ $host }} ↗</span>
    </a>
@endif
```

In `resources/views/trove.blade.php`, replace the interim iframe loop from Task 5 with:

```blade
@if($videoLinks->isNotEmpty())
    <div class="mb-8 space-y-4">
        @foreach($videoLinks as $link)
            <x-video-link :link="$link" />
        @endforeach
    </div>
@endif
```

(The `$videoLinks` collection prep in the `@php` block from Task 5 Step 6.7 stays as is.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=TroveVideoRenderingTest` then `php artisan test`
Expected: all PASS. Note: `assertDontSee('<iframe', false)` will fail if the page embeds iframes for other reasons — if so, scope the assertion to `assertDontSee('aspect-video', false)` instead.

- [ ] **Step 5: Visual check**

Run `npm run build` (Tailwind must pick up the new component's classes) and view a trove with a link-only video in the browser if a dev environment is running. Not blocking — the classes used all exist elsewhere in the app except the layout combination.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add resources/views/components/video-link.blade.php resources/views/trove.blade.php tests/Feature/Http/TroveVideoRenderingTest.php
git commit -m "Render video links via component with link-card fallback"
```

---

### Task 7: Filament form — paste-a-URL field with live resolution

**Files:**
- Modify: `app/Filament/Resources/TroveResource.php:186-200` (the `youtube_links` — now `video_links` — field)
- Create: `app/Filament/Resources/TroveResource/Concerns/ResolvesVideoLinkFormData.php`
- Modify: `app/Filament/Resources/TroveResource/Pages/EditTrove.php`, `app/Filament/Resources/TroveResource/Pages/CreateTrove.php`
- Test: `tests/Feature/Filament/Trove/VideoLinksFormTest.php`

**Interfaces:**
- Consumes: `ResolvesVideoLinks` contract + `VideoLinkResult` (Tasks 1/4), `TranslatableComboField` (`app/Filament/Translatable/Form/TranslatableComboField.php` — clones the child Repeater per locale with `statePath($locale)`), Filament 5 namespaces: `Filament\Schemas\Components\Utilities\Get` / `Set`, `Filament\Forms\Components\{Hidden, Placeholder}`.
- Produces: `trait ResolvesVideoLinkFormData` with `protected function resolveVideoLinkFormData(array $data): array` — used by both pages' mutate hooks.

- [ ] **Step 1: Write the failing feature test**

`tests/Feature/Filament/Trove/VideoLinksFormTest.php` (mirrors the setup style of `tests/Feature/Filament/Trove/CrudTest.php`):

```php
<?php

use App\Contracts\ResolvesVideoLinks;
use App\Filament\Resources\TroveResource\Pages\CreateTrove;
use App\Models\Trove;
use App\Models\TroveType;
use App\Support\VideoLink\VideoLinkResult;
use Livewire\Livewire;

beforeEach(function () {
    $this->me = actingAsAdmin();

    app()->instance(ResolvesVideoLinks::class, new class implements ResolvesVideoLinks
    {
        public array $resolvedUrls = [];

        public function resolve(string $url): VideoLinkResult
        {
            $this->resolvedUrls[] = $url;

            return new VideoLinkResult(
                url: $url,
                provider: 'youtube',
                embedUrl: 'https://www.youtube.com/embed/q76bMs-NwRk',
                embeddable: true,
                title: 'Resolved title',
                resolvedUrl: $url,
            );
        }
    });
});

function videoTroveFormData(TroveType $type, array $videoLinks): array
{
    return [
        'title' => ['en' => 'Video Trove'],
        'description' => ['en' => 'Has a video'],
        'trove_type_id' => $type->id,
        'source' => 0,
        'creation_date' => now()->format('Y-m-d'),
        'video_links' => ['en' => $videoLinks],
    ];
}

it('resolves unresolved video links on create', function () {
    $type = TroveType::factory()->create();

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [['url' => 'https://youtu.be/q76bMs-NwRk']]))
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = Trove::withDrafts()->firstWhere('slug', 'video-trove')->getTranslation('video_links', 'en');

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['embed_url'])->toBe('https://www.youtube.com/embed/q76bMs-NwRk')
        ->and($stored[0]['embeddable'])->toBeTrue()
        ->and($stored[0]['title'])->toBe('Resolved title');
});

it('does not re-resolve rows whose resolution already matches the url', function () {
    $type = TroveType::factory()->create();
    $resolver = app(ResolvesVideoLinks::class);

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [[
            'url' => 'https://youtu.be/q76bMs-NwRk',
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
            'embeddable' => true,
            'title' => 'Already resolved',
            'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
        ]]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect($resolver->resolvedUrls)->toBe([]);
});

it('re-resolves rows whose url changed after resolution and drops empty rows', function () {
    $type = TroveType::factory()->create();
    $resolver = app(ResolvesVideoLinks::class);

    Livewire::test(CreateTrove::class)
        ->fillForm(videoTroveFormData($type, [
            [
                'url' => 'https://youtu.be/DIFFERENT-ID',
                'provider' => 'youtube',
                'embed_url' => 'https://www.youtube.com/embed/q76bMs-NwRk',
                'embeddable' => true,
                'title' => 'Stale',
                'resolved_url' => 'https://youtu.be/q76bMs-NwRk',
            ],
            ['url' => '   '],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = Trove::withDrafts()->firstWhere('slug', 'video-trove')->getTranslation('video_links', 'en');

    expect($resolver->resolvedUrls)->toBe(['https://youtu.be/DIFFERENT-ID'])
        ->and($stored)->toHaveCount(1)
        ->and($stored[0]['title'])->toBe('Resolved title');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=VideoLinksFormTest`
Expected: FAIL — the form still has no `url` field inside `video_links` (fillForm state won't reach the mutate hook in the asserted shape) and the concern doesn't exist.

- [ ] **Step 3: Replace the form field**

In `app/Filament/Resources/TroveResource.php`, add imports:

```php
use App\Contracts\ResolvesVideoLinks;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
```

Replace the `TranslatableComboField::make('youtube_links')` block (originally lines 186-200, renamed in Task 5) with:

```php
TranslatableComboField::make('video_links')
    ->icon('heroicon-o-video-camera')
    ->iconColor('primary')
    ->extraAttributes(['class' => 'grey-box'])
    ->heading(__('Videos'))
    ->hint(__('Paste the video\'s share URL (YouTube, Vimeo, EcoAgTube, …). If the video can\'t be embedded, the public page shows a link to it instead.'))
    ->columns(3)
    ->childField(
        Forms\Components\Repeater::make('video_links')
            ->schema([
                Forms\Components\TextInput::make('url')
                    ->label(__('Video URL'))
                    ->url()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if (blank($state)) {
                            foreach (['provider', 'embed_url', 'embeddable', 'title', 'resolved_url'] as $resolvedField) {
                                $set($resolvedField, null);
                            }

                            return;
                        }

                        $result = app(ResolvesVideoLinks::class)->resolve($state);

                        $set('provider', $result->provider);
                        $set('embed_url', $result->embedUrl);
                        $set('embeddable', $result->embeddable);
                        $set('title', $result->title);
                        $set('resolved_url', $result->resolvedUrl);
                    }),
                Forms\Components\Hidden::make('provider'),
                Forms\Components\Hidden::make('embed_url'),
                Forms\Components\Hidden::make('embeddable'),
                Forms\Components\Hidden::make('title'),
                Forms\Components\Hidden::make('resolved_url'),
                Forms\Components\Placeholder::make('status')
                    ->hiddenLabel()
                    ->content(fn (Get $get): string => match (true) {
                        blank($get('url')) => '',
                        (bool) $get('embeddable') => __('Embeds on the page').($get('title') ? ' — '.$get('title') : ''),
                        filled($get('provider')) || filled($get('title')) => __('Link only — the public page will show a link card for this video.'),
                        default => __('Couldn\'t verify this URL — it will be shown as a plain link.'),
                    }),
            ])
            ->addActionLabel(__('Add a video')),
    ),
```

- [ ] **Step 4: Create the concern and wire the mutate hooks**

`app/Filament/Resources/TroveResource/Concerns/ResolvesVideoLinkFormData.php`:

```php
<?php

namespace App\Filament\Resources\TroveResource\Concerns;

use App\Contracts\ResolvesVideoLinks;

trait ResolvesVideoLinkFormData
{
    /**
     * The form resolves URLs on blur; this save-time pass covers rows where that
     * round-trip never fired (paste-then-save) or the URL changed after resolution.
     */
    protected function resolveVideoLinkFormData(array $data): array
    {
        if (empty($data['video_links'])) {
            return $data;
        }

        if (! is_array($data['video_links'])) {
            return $data;
        }

        $resolver = app(ResolvesVideoLinks::class);

        foreach ($data['video_links'] as $locale => $rows) {
            if (! is_array($rows)) {
                continue;
            }

            $resolvedRows = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '') {
                    continue;
                }

                if (($row['resolved_url'] ?? null) !== $url) {
                    $row = array_merge($row, $resolver->resolve($url)->toArray());
                }

                $resolvedRows[] = $row;
            }

            $data['video_links'][$locale] = $resolvedRows;
        }

        return $data;
    }
}
```

First check neither page nor `app/Filament/Resources/TroveResource/Concerns/HasTroveFormActions.php` already defines the mutate hooks (`grep -rn "mutateFormDataBefore" app/Filament/Resources/TroveResource/`). Assuming they don't, add to `CreateTrove`:

```php
use App\Filament\Resources\TroveResource\Concerns\ResolvesVideoLinkFormData;

// inside the class:
use ResolvesVideoLinkFormData;

protected function mutateFormDataBeforeCreate(array $data): array
{
    return $this->resolveVideoLinkFormData($data);
}
```

and to `EditTrove`:

```php
use App\Filament\Resources\TroveResource\Concerns\ResolvesVideoLinkFormData;

// inside the class:
use ResolvesVideoLinkFormData;

protected function mutateFormDataBeforeSave(array $data): array
{
    return $this->resolveVideoLinkFormData($data);
}
```

If a hook already exists, chain `$data = $this->resolveVideoLinkFormData($data);` inside it instead.

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=VideoLinksFormTest` then `php artisan test --filter="Trove"`
Expected: all PASS. Watch for `tests/Feature/Filament/Trove/CrudTest.php` and `LifecycleActionsTest.php` regressions — the new mutate hooks run on every save, but with no `video_links` in the form data they are no-ops.

**Livewire/TranslatableComboField gotcha:** `TranslatableComboField` clones the Repeater per locale with `statePath($locale)`, so the repeater item's `$set`/`$get` remain relative to the item — the `afterStateUpdated` closure needs no locale awareness. If `fillForm(['video_links' => ['en' => [...]]])` doesn't reach the field, check how `CrudTest` fills other combo fields (`title` uses the same dictionary shape) and match it.

- [ ] **Step 6: Manual smoke test**

With `npm run dev` + a local server running and `SCOUT_DRIVER=null`, open a trove in `/admin`, paste `https://www.youtube.com/watch?v=q76bMs-NwRk` into Videos, tab out, and confirm the status line updates (this exercises the real resolver against the live oEmbed endpoint). Not automatable here; skip if no dev environment is available.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Resources/TroveResource.php app/Filament/Resources/TroveResource/Concerns/ResolvesVideoLinkFormData.php app/Filament/Resources/TroveResource/Pages/EditTrove.php app/Filament/Resources/TroveResource/Pages/CreateTrove.php tests/Feature/Filament/Trove/VideoLinksFormTest.php
git commit -m "Add paste-a-URL video field with live embed resolution to the trove form"
```

---

### Task 8: CSV import — `video_url` column with multi-host resolution

**Files:**
- Modify: `app/Console/Commands/ImportTroves.php`
- Modify: `docs/import/README.md:32-33`
- Test: update `tests/Feature/Console/ImportTrovesCommandTest.php`

**Interfaces:**
- Consumes: `ResolvesVideoLinks` (injected into `handle()`), `YouTubeAdapter::extractId()` (Task 2).
- Produces: CSV column `video_url` (header `youtube_url` accepted as an alias); dedupe keys `yt:{id}` for YouTube URLs (unchanged — stays idempotent against pre-existing imports) and `vid:{lowercased url}` for other hosts.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Console/ImportTrovesCommandTest.php`, using the file's existing `importCsv(array $header, array ...$rows): string` helper and its `beforeEach` (which creates `importer@example.com`). Add the imports `use App\Contracts\ResolvesVideoLinks;` and `use App\Support\VideoLink\VideoLinkResult;` at the top, plus this helper alongside `importCsv`:

```php
/** Bind a spying fake resolver: ecoagtube URLs resolve embeddable, everything else link-only. */
function fakeVideoResolver(): ResolvesVideoLinks
{
    $fake = new class implements ResolvesVideoLinks
    {
        public array $resolvedUrls = [];

        public function resolve(string $url): VideoLinkResult
        {
            $this->resolvedUrls[] = $url;

            if (str_contains($url, 'ecoagtube')) {
                return new VideoLinkResult(
                    url: $url,
                    provider: 'ecoagtube',
                    embedUrl: 'https://www.ecoagtube.org/embed/32021',
                    embeddable: true,
                    title: 'EcoAgTube video',
                    resolvedUrl: $url,
                );
            }

            return new VideoLinkResult(url: $url, resolvedUrl: $url);
        }
    };

    app()->instance(ResolvesVideoLinks::class, $fake);

    return $fake;
}
```

New tests:

```php
it('imports a video_url row resolved through the video link resolver', function () {
    $resolver = fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'video_url'],
        ['Eco video', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    $trove = Trove::withDrafts()->firstOrFail();

    expect($resolver->resolvedUrls)->toBe(['https://www.ecoagtube.org/content/biofertilizer-formulation-1'])
        ->and($trove->getTranslation('video_links', 'en'))->toBe([[
            'url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
            'provider' => 'ecoagtube',
            'embed_url' => 'https://www.ecoagtube.org/embed/32021',
            'embeddable' => true,
            'title' => 'EcoAgTube video',
            'resolved_url' => 'https://www.ecoagtube.org/content/biofertilizer-formulation-1',
        ]]);
});

it('skips duplicate video urls within a file and against the database', function () {
    fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'video_url'],
        ['Eco video', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
        ['Eco video again', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com'])
        ->assertExitCode(0);

    expect(Trove::withDrafts()->count())->toBe(1);
});

it('does not resolve video urls during a dry run', function () {
    $resolver = fakeVideoResolver();

    $path = importCsv(
        ['title:en', 'video_url'],
        ['Eco video', 'https://www.ecoagtube.org/content/biofertilizer-formulation-1'],
    );

    $this->artisan('troves:import', ['file' => $path, '--uploader' => 'importer@example.com', '--dry-run' => true])
        ->assertExitCode(0);

    expect($resolver->resolvedUrls)->toBe([])
        ->and(Trove::withDrafts()->count())->toBe(0);
});
```

The existing `publishes imported troves with --publish` test (header `['title:en', 'youtube_url']`) becomes the legacy-alias test: keep its header as `youtube_url`, add `fakeVideoResolver();` as its first line, and change its stored-shape assertion to:

```php
->and($trove->getTranslation('video_links', 'en'))->toBe([[
    'url' => 'https://www.youtube.com/watch?v=q76bMs-NwRk',
    'provider' => null,
    'embed_url' => null,
    'embeddable' => false,
    'title' => null,
    'resolved_url' => 'https://www.youtube.com/watch?v=q76bMs-NwRk',
]]);
```

(the fake resolves non-ecoagtube URLs as link-only — the point here is the alias routing and that the resolver received the URL, not YouTube behaviour, which Task 2 covers).

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=ImportTrovesCommandTest`
Expected: new tests FAIL — `Unrecognised column "video_url"`.

- [ ] **Step 3: Update the importer**

In `app/Console/Commands/ImportTroves.php`:

1. Replace the `FIXED_COLUMNS` private const (line 40-48) with a private property (per the no-private-const rule), swapping the column name:

```php
/** @var list<string> Recognised single-value columns; title:*, description:* and tag:* are matched by pattern. */
private array $fixedColumns = [
    'trove_type',
    'creation_date',
    'link_url',
    'link_title',
    'video_url',
    'cover_image_url',
    'collections',
];
```

Update both usages (`parseHeader` line 211 and the error message line 214) from `self::FIXED_COLUMNS` to `$this->fixedColumns`.
2. In `parseHeader()`, alias the legacy header just after `$name` is computed (line 199):

```php
if ($name === 'youtube_url') {
    $name = 'video_url';
}
```

3. Inject the resolver: `public function handle(TrovePublisher $publisher, ResolvesVideoLinks $videoLinkResolver): int` — store it on a private property `$this->videoLinkResolver = $videoLinkResolver;` at the top of `handle()` (declare `private ResolvesVideoLinks $videoLinkResolver;`), and add the `use App\Contracts\ResolvesVideoLinks;` import.
4. In `buildPlan()` replace the `$youtubeId` block (lines 341-347) with URL validation + a normalisation for bare YouTube IDs (keeps old files working):

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
```

5. Replace the dedupe-key block (lines 362-365):

```php
$sourceKeys = array_values(array_filter([
    $linkUrl !== '' ? $linkUrl : null,
    $videoUrl !== '' ? $this->videoSourceKey($videoUrl) : null,
]));
```

with the new private method (offline — no HTTP during planning, so `--dry-run` stays network-free):

```php
private function videoSourceKey(string $url): string
{
    $youtubeId = YouTubeAdapter::extractId($url);

    if ($youtubeId !== null) {
        return "yt:{$youtubeId}";
    }

    return 'vid:'.mb_strtolower(rtrim($url, '/'));
}
```

6. The plan row stores the raw URL instead of a prebuilt record — replace Task 5's `'video_links' => ...` entry with `'video_url' => $videoUrl !== '' ? $videoUrl : null,`.
7. In `executePlan()`, resolve at write time (this runs only on non-dry runs), with per-row output before processing per the command-output guidelines:

```php
$videoLinks = null;
if ($row['video_url'] !== null) {
    $this->line("  Resolving video {$row['video_url']}...");
    $videoLinks = [$row['primary_locale'] => [$this->videoLinkResolver->resolve($row['video_url'])->toArray()]];
}
```

and assign `$videoLinks` where Task 5's `$row['video_links']` was used.
8. Update `buildIndexes()` (Task 5's version) to key existing DB rows with the same method: `$this->seenSourceKeys[$this->videoSourceKey($link['url'])] = $trove->id;` (guard on `! empty($link['url'])`).
9. Remove the now-unused `extractYoutubeId()` method (lines 425-443) — `YouTubeAdapter::extractId()` replaced it.

- [ ] **Step 4: Update the docs**

`docs/import/README.md` lines 32-33: replace the `youtube_url` row with:

```markdown
| `video_url` | Share URL of a video (YouTube, Vimeo, EcoAgTube, …), or a bare 11-char YouTube ID. The URL is resolved at import time: embeddable videos get an embedded player on the public page, others a link card. `youtube_url` is accepted as a legacy alias for this column. |
```

and drop the "(ecoagtube links go here, **not** in `youtube_url`)" note from the `link_url` row (EcoAgTube now belongs in `video_url`).

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=ImportTrovesCommandTest` then `php artisan test`
Expected: all PASS, including the pre-existing import tests (the `youtube_url` alias keeps them valid — but their stored-shape assertions may need the resolver fake bound; check each failure individually).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Console/Commands/ImportTroves.php docs/import/README.md tests/Feature/Console/ImportTrovesCommandTest.php
git commit -m "Import any video share URL via video_url column with resolver"
```

---

### Task 9: Documentation, change log, plan closure

**Files:**
- Create: `docs/change-logs/video-links-multi-host.md`
- Modify: `docs/plans/2026-07-08-video-links-multi-host.md` (this file — Status line)
- Modify: `CLAUDE.md` (only if a claim it makes is now wrong — check the Architecture section for references to YouTube-only video handling; currently it makes none, so likely no change)

- [ ] **Step 1: Verify everything**

Run: `php artisan test` and `vendor/bin/pint --test`
Expected: full suite PASS, Pint clean. Do not proceed on failure.

- [ ] **Step 2: Write the change log**

`docs/change-logs/video-links-multi-host.md` — follow the style of the existing files in `docs/change-logs/`. It must reference the plan file (`docs/plans/2026-07-08-video-links-multi-host.md`) and the spec (`docs/superpowers/specs/2026-07-08-video-embedding-design.md`) in the intro, then summarise: the `youtube_links` → `video_links` rename + in-place conversion, the resolver service and its three adapters, the new form UX, the link-card fallback, the `video_url` import column with `youtube_url` alias, and the new `embed/embed` dependency.

- [ ] **Step 3: Close the plan**

Update this file's `**Status:**` line to `Completed` and add: `Change log: docs/change-logs/video-links-multi-host.md`.

- [ ] **Step 4: Commit**

```bash
git add docs/change-logs/video-links-multi-host.md docs/plans/2026-07-08-video-links-multi-host.md
git commit -m "Add change log for multi-host video links"
```

---

## Self-Review Notes

- **Spec coverage:** data model + record shape (Task 1/5), resolver chain with all three adapters and URL guards (Tasks 2-4), live form resolution + save-time re-check (Task 7), iframe/link-card rendering + `getDownloadableLinks` (Tasks 5-6), offline migration incl. legacy single-assoc shape and draft rows (Task 5), CSV import with alias + dedupe + dry-run (Task 8), docs (Tasks 8-9). Out-of-scope items from the spec (Collections, staleness refresh, search indexing, thumbnails, SSRF hardening) have no tasks — by design.
- **Deviation from spec:** the stored record gains a sixth key, `resolved_url`, so the save-time re-check can detect a URL edited after resolution without re-resolving every row on every save. The spec's "re-resolves any item whose url doesn't match its resolved state" is implemented through it.
- **Known uncertainty:** `GenericVideoAdapterTest`'s Guzzle mock queue assumes `embed/embed` requests page-then-oEmbed; Task 4 Step 5 says how to adjust fixtures if its request order differs. `VideoLinksFormTest`'s `fillForm` shape for the combo-field repeater is modelled on `CrudTest`'s `title` dictionary fill; Task 7 Step 5 flags where to look if it doesn't bind.
