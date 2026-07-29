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
    | Swiss German throughout, including the locale Faker uses when generating
    | test data.
    |
    */

    'locale' => env('APP_LOCALE', 'de_CH'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'de_CH'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'de_CH'),

];
