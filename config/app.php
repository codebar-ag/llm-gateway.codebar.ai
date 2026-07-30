<?php

declare(strict_types=1);

/*
 | Partial override — all other application options come from the framework
 | defaults (vendor/laravel/framework/config/app.php).
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Default timezone for PHP date and date-time functions. Set here instead
    | of APP_TIMEZONE in .env so every environment shares the same default.
    |
    */

    'timezone' => 'Europe/Zurich',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | Swiss English throughout — every string in the UI is English, so
    | validation and other framework-generated text stays in the same
    | language rather than falling back to untranslated keys. Also the
    | locale Faker uses when generating test data.
    |
    */

    'locale' => env('APP_LOCALE', 'en_CH'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en_CH'),

    // Faker has no en_CH provider set; en_US is the closest real match and
    // what Faker would silently fall back to anyway.
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

];
