<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
 | Partial override — all other session options come from the framework
 | defaults (vendor/laravel/framework/config/session.php).
 |
 | Nothing in this app stores anything in the session: the dashboard is
 | read-only and the API is stateless. Only the two options whose framework
 | default would be wrong here are set.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Slug rather than snake case, so an app name with adjacent capitals stays
    | readable ("LLM Gateway" → llm_gateway_session, not l_l_m_gateway_session).
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | An empty SESSION_DOMAIN in .env must mean "no domain", not the empty
    | string.
    |
    */

    'domain' => ($sessionDomain = env('SESSION_DOMAIN')) !== null && $sessionDomain !== ''
        ? $sessionDomain
        : null,

];
