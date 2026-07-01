<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
            }
            config([
                'branding.features.show_language_filter' => $settings->show_language_filter,
            ]);
        } catch (\Exception) {
            // DB not ready (fresh install) - branding.php defaults remain
        }
    }
}
