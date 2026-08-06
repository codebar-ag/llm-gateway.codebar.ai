<?php

declare(strict_types=1);

/*
 | Partial override — all other database options, and every other connection,
 | come from the framework defaults (vendor/laravel/framework/config/database.php).
 |
 | `connections` is merged one level deep, so the block below replaces the
 | framework's `pgsql` entry outright and must stay complete. The other
 | connections (sqlite for the test suite, mysql, sqlsrv) are inherited as-is.
 */

return [

    'connections' => [

        /*
        |----------------------------------------------------------------------
        | PostgreSQL
        |----------------------------------------------------------------------
        |
        | Split read/write hosts so a replica can be pointed at later without
        | touching this file, and `postgres` as the username default because
        | that is the role a stock local Postgres ships with.
        |
        | `timezone` is not optional here. Eloquent writes and reads datetimes
        | as bare `Y-m-d H:i:s` strings, and Postgres resolves a bare string
        | against the *session* timezone — which, unset, is whatever the server
        | happens to default to. A server one hour off Europe/Zurich then stores
        | every `timestamptz` an hour away from the instant it meant, and the
        | dashboard reads it back as an hour in the future. Pinning the session
        | to the application timezone makes the round trip exact on any host.
        |
        */

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'read' => [
                'host' => env('DB_READ_HOST', env('DB_HOST', '127.0.0.1')),
            ],
            'write' => [
                'host' => env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1')),
            ],
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'timezone' => env('DB_TIMEZONE', config('app.timezone')),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

    ],

];
