<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organisation Branding
    |--------------------------------------------------------------------------
    |
    | Set these values in your .env file to brand this site for your
    | organisation. These values are used in the header, footer, and
    | throughout the site.
    |
    */

    /*
     * Logo
     * ----
     * Place your logo file at:  public/images/logo.png
     * Recommended:              PNG with transparent background, roughly 200×60px.
     * It is displayed at h-10 (40px tall) in the header; width scales automatically.
     * Colours (CSS variables) are set in:  resources/css/app.css  under :root {}
     */

    // Full organisation name
    'org_name' => env('BRAND_ORG_NAME', 'Your Organisation'),

    // URL of your main organisation website (used in the header "Home" button and footer copyright link)
    'home_url' => env('BRAND_HOME_URL', '/'),

    // Social media URLs shown in the footer (leave blank to hide)
    'linkedin_url' => env('BRAND_LINKEDIN_URL', ''),
    'youtube_url'  => env('BRAND_YOUTUBE_URL', ''),

    /*
     * Static fallback for the configured locales. AppServiceProvider::boot() overrides this
     * from the database (Site Options) once migrations have run; this default keeps the public
     * site (and translation.io locale config) working on a fresh install or if the DB is briefly
     * unreachable.
     */
    'locales' => ['en' => 'English'],

    /*
     * Supported Languages
     * -------------------
     * Languages are managed via the admin panel (Site Options) and stored in
     * the database. English is the default on a fresh install. To add or remove
     * languages, use the admin panel.
     *
     * Translation strings are managed via Translation.io (https://translation.io).
     * If you want translated UI strings you will need to create your own
     * Translation.io project, add your TRANSLATIONIO_KEY to .env, and update
     * config/translation.php to match the locales configured in the admin panel.
     * Run `php artisan translation:sync` to push/pull strings.
     * For a single-language (English-only) site you can ignore Translation.io
     * entirely - no key or sync is required.
     */

    /*
     * Colours & Font
     * --------------
     * Edit the CSS variables in:  resources/css/app.css  under :root {}
     *   --brand-primary     Main accent colour (buttons, links, dividers)
     *   --brand-secondary   Second accent colour (card icons, view buttons)
     *   --brand-bg          Page background colour
     *   --brand-footer-bg   Footer background colour
     *   --brand-footer-text Footer text colour
     *   --brand-font        Font family - also update the @import URL at the top of app.css
     *                       Browse fonts at https://fonts.google.com
     *
     * Logo
     * ----
     * Place your logo file at:  public/images/logo.png
     * Recommended:              PNG with transparent background, roughly 200×60px.
     * Logo is hidden automatically if the file does not exist.
     *
     * Banner image (browse library page)
     * -----------------------------------
     * Place your banner image at:  public/images/banner.png
     * Recommended:                 Wide landscape photo, at least 1400px wide.
     * Banner is hidden automatically if the file does not exist.
     */

];
