<?php

namespace App\Providers;

use App\Models\SiteSetting;
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
            if (!empty($locales)) {
                config(['branding.locales' => $locales]);
                config(['app.locales' => $locales]);

                // translation.io's SetLocaleMiddleware only honours ?locale= links whose code is a
                // known target locale. config/translation.php computes this before boot, when
                // branding.locales is still the static default, so admin-added locales are hydrated
                // here. Target locales exclude the source (first) locale, matching the tio package.
                config(['translation.target_locales' => array_keys(array_slice($locales, 1))]);
            }
            config([
                'branding.features.show_language_filter' => $settings->show_language_filter,
            ]);
        } catch (\Exception) {
            // DB not ready (fresh install) - branding.php defaults remain
        }
    }
}
