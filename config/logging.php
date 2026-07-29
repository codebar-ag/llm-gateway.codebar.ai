<?php

declare(strict_types=1);

/*
 | Partial override — all other logging options come from the framework
 | defaults (vendor/laravel/framework/config/logging.php). `channels` is
 | merged one level deep, so the daily channel is restated in full.
 |
 | The default `single` channel is one file that grows forever on one machine.
 | A worker failing every request is exactly the thing you want to still be
 | able to read a week later, so this rotates instead.
 |
 | Exceptions thrown by a job already pass through the framework's exception
 | handler, so pointing an error reporter at this app is a package install and
 | a DSN — no code change here.
 */

return [

    'default' => strval(env('LOG_CHANNEL', 'daily')),

    'channels' => [

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => strval(env('LOG_LEVEL', 'info')),
            'days' => intval(env('LOG_DAILY_DAYS', 30)),
            'replace_placeholders' => true,
        ],

    ],

];
