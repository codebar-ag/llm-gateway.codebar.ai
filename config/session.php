<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
 | Partial override — all other session options come from the framework
 | defaults (vendor/laravel/framework/config/session.php).
 |
 | The API is stateless and never touches the session. The dashboard does: it
 | carries the operator's login and the password fingerprint `auth.session`
 | compares on every request, which is what makes `user:reset-password` sign
 | live browsers out. Only the two options whose framework default would be
 | wrong here are set; `SESSION_SECURE_COOKIE` is pinned in .env.example
 | rather than here, because whether TLS is terminated in front of the app is
 | a deployment question rather than an application one.
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
