<?php

use App\Models\Trove;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case bindings
|--------------------------------------------------------------------------
|
| Every Feature and Unit test runs against Tests\TestCase (the full Laravel
| application) with a freshly migrated database. The suite targets SQLite
| :memory: (see phpunit.xml), while the app runs on MySQL in production.
|
| SQLite caveats (see docs/plans/test-suite-buildout.md):
|   - Trove::findBySlugOrRedirect() uses whereJsonContains() on previous_slugs.
|     This works on SQLite via its JSON1 functions, but string-vs-numeric
|     matching can differ subtly from MySQL; the tests pin the SQLite behaviour.
|   - EditTrove::isDuplicateDraftViolation() keys on MySQL errno 1062; SQLite
|     raises a different code, so the concurrent-first-fork race path is not
|     faithfully testable here (happy path only).
|   - JSON-translatable ordering/whitespace can differ; assert on decoded
|     values, never raw JSON strings.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->beforeEach(function () {
    // The array cache driver persists spatie's role/permission cache across tests in the
    // same process; forget it so each test resolves roles against its own migrated DB state.
    app(PermissionRegistrar::class)->forgetCachedPermissions();
})->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Shared helpers
|--------------------------------------------------------------------------
*/

/**
 * Authenticate as an admin (the historical behaviour: full access to everything). Passing
 * an existing user authenticates it as-is without touching its roles.
 */
function actingAsAdmin(?User $user = null): User
{
    $user = $user ?? User::factory()->admin()->create();
    test()->actingAs($user);

    return $user;
}

/**
 * Authenticate as an editor — the "regular user" with full content CRUD but no access to
 * user management or site settings.
 */
function actingAsEditor(?User $user = null): User
{
    $user = $user ?? User::factory()->editor()->create();
    test()->actingAs($user);

    return $user;
}

/**
 * Authenticate as a read-only viewer (what open registration grants).
 */
function actingAsViewer(?User $user = null): User
{
    $user = $user ?? User::factory()->viewer()->create();
    test()->actingAs($user);

    return $user;
}

/**
 * Create a published canonical Trove. Search syncing is skipped so tests don't
 * touch the (collection-driver) index unless they explicitly exercise it.
 */
function publishedTrove(array $attributes = []): Trove
{
    return Trove::withoutSyncingToSearch(
        fn () => Trove::factory()->published()->create($attributes)
    );
}

/**
 * Create an unpublished (never-published) canonical Trove.
 */
function draftTrove(array $attributes = []): Trove
{
    return Trove::withoutSyncingToSearch(
        fn () => Trove::factory()->create($attributes)
    );
}

/**
 * Simulate a non-panel (public/web/console) request context so PublishedScope applies.
 *
 * PublishedScope self-disables whenever a Filament panel is the current request context.
 * In the test harness the default 'admin' panel is registered as the current panel during
 * boot, so by default the scope is OFF (as it is inside the admin panel). Call this to
 * clear the current panel and exercise the public visibility rules.
 */
function usePublicContext(): void
{
    Filament::setCurrentPanel(null);
}

/**
 * Arrange a public web request: a non-panel context (so PublishedScope applies) plus the
 * locale config the public layout/header expect. In production AppServiceProvider hydrates
 * branding.locales from SiteSetting at boot; under RefreshDatabase the table doesn't exist
 * yet at boot, so branding.php's (locale-less) defaults remain — set them here.
 */
function bootPublicSite(array $locales = ['en' => 'English']): void
{
    config(['branding.locales' => $locales, 'app.locales' => $locales]);
    usePublicContext();
}
