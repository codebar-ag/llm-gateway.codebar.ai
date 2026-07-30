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
| There is deliberately no `passwords` block: resetting is a server command,
| `php artisan user:reset-password`, not an emailed link — so there is no
| reset-token table and no mail configuration to send one with. Accounts are
| created the same way, with `php artisan user:register`.
|
| A web reset flow would add an unauthenticated route that can take over the
| account able to read every prompt and answer this app has handled. Requiring
| a shell on the server instead means no such route exists.
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
