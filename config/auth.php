<?php

declare(strict_types=1);
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| One session guard over one Eloquent provider, which is the whole of it.
|
| There is deliberately no `passwords` block: this app has no password reset,
| no reset-token table and no mail configuration to send one with. Accounts
| are created by `php artisan user:register` on the server, and a forgotten
| password is replaced the same way. Adding a reset flow would add an
| unauthenticated, mail-bearing route to an app whose dashboard shows every
| prompt and answer it has ever handled.
|
*/

return [

    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    /*
    | How long `password.confirm` remains satisfied. Unused today — kept
    | because the framework reads it whenever that middleware is applied.
    */
    'password_timeout' => 10800,

];
