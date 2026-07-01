<?php

return [
    'key' => env('TRANSLATIONIO_KEY'),
    'source_locale' => array_key_first(config('branding.locales', ['en' => 'English'])),
    'target_locales' => array_keys(array_slice(config('branding.locales', ['en' => 'English']), 1)),

    /* Directories to scan for Gettext strings */
    'gettext_parse_paths' => ['app', 'resources']
];
