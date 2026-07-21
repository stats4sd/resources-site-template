<?php

namespace App\Providers;

use App\Contracts\ResolvesVideoLinks;
use App\Contracts\SearchesLibrary;
use App\Models\SiteSetting;
use App\Services\Search\DatabaseLibrarySearch;
use App\Services\Search\MeilisearchLibrarySearch;
use App\Services\VideoLink\EcoAgTubeAdapter;
use App\Services\VideoLinkResolver;
use Embed\Embed;
use Embed\Http\Crawler;
use GuzzleHttp\Client as GuzzleClient;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider as PackageTelescopeServiceProvider;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            if (class_exists(PackageTelescopeServiceProvider::class)) {
                $this->app->register(PackageTelescopeServiceProvider::class);
                $this->app->register(TelescopeServiceProvider::class);
            }
        }

        $this->app->bind(Embed::class, function () {
            $httpClient = new GuzzleClient([
                'timeout' => 5,
                'headers' => ['User-Agent' => EcoAgTubeAdapter::browserUserAgent()],
            ]);

            return new Embed(new Crawler($httpClient, new RequestFactory, new UriFactory));
        });

        $this->app->bind(ResolvesVideoLinks::class, VideoLinkResolver::class);

        $this->app->bind(SearchesLibrary::class, function () {
            if (config('scout.driver') === 'meilisearch') {
                return $this->app->make(MeilisearchLibrarySearch::class);
            }

            return $this->app->make(DatabaseLibrarySearch::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        // allow the use of any translation if the fallback is not found
        Translatable::fallback(
            fallbackAny: true
        );

        try {
            $settings = SiteSetting::instance();
            $locales = $settings->localesAsConfig();
            if (! empty($locales)) {
                config(['branding.locales' => $locales]);
                config(['app.locales' => $locales]);

                // translation.io's SetLocaleMiddleware only honours ?locale= links whose code is a
                // known target locale. config/translation.php computes this before boot, when
                // branding.locales is still the static default, so admin-added locales are hydrated
                // here. Target locales exclude the source (first) locale, matching the tio package.
                config(['translation.source_locale' => array_key_first($locales)]);
                config(['translation.target_locales' => array_keys(array_slice($locales, 1))]);
            }
            config([
                'branding.features.show_language_filter' => $settings->show_language_filter,
                'branding.features.show_trove_type_filter' => $settings->show_trove_type_filter,
            ]);
        } catch (\Exception) {
            // DB not ready (fresh install) - branding.php defaults remain
        }
    }
}
