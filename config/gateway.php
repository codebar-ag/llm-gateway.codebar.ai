<?php

declare(strict_types=1);

/*
 | Values are normalised here so the whole application can read them through
 | the strict Config::string() / Config::integer() accessors.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Herd, Valet, nginx, Caddy and cloudflared all terminate TLS on the same
    | host, so the loopback addresses are the default. Behind a load balancer
    | on another host, set its IPs or CIDRs as a comma separated list.
    |
    | Do not put `*` here on a reachable deployment. It trusts whatever
    | `X-Forwarded-For` the immediate client sends, and the queue rate limit
    | buckets keyless callers by `$request->ip()` — a spoofed header is then a
    | fresh bucket per request, which lifts the limit entirely.
    |
    */

    'trusted_proxies' => strval(env('TRUSTED_PROXIES', '127.0.0.1,::1')),

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | The dashboard shows whole prompts and answers, so it is guarded by one
    | shared operator token rather than left open. This is not a caller key:
    | it grants a view of every entry. Leave it empty and the dashboard is
    | switched off entirely instead of being served unprotected.
    |
    */

    'dashboard' => [
        'token' => strval(env('DASHBOARD_TOKEN', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | An entry belongs to the key that queued it, so callers only ever see
    | their own. Callers sending no key all land in the same bucket and can
    | read, cancel and delete one another's entries — fine on a trusted LAN,
    | and the reason `require_caller_key` exists for anywhere else.
    |
    | Off by default: switching it on rejects existing keyless callers, which
    | is an operator's decision rather than something an upgrade should do.
    |
    */

    'api' => [
        'require_caller_key' => boolval(env('API_REQUIRE_CALLER_KEY', false)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tunnel
    |--------------------------------------------------------------------------
    |
    | cloudflared exposes a metrics server whose /ready endpoint reports
    | whether the tunnel is connected to Cloudflare's edge. Point this at it
    | and the dashboard will show whether the way out is open.
    | Leave it empty and the indicator simply reads "unknown".
    |
    */

    'tunnel' => [
        'metrics_url' => strval(env('TUNNEL_METRICS_URL', '')),
        'timeout' => intval(env('TUNNEL_TIMEOUT', 2)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upstream
    |--------------------------------------------------------------------------
    |
    | Where a request actually goes is a `gateways` row, managed from the
    | dashboard, so URLs and per-gateway timeouts are not configured here.
    | This app still holds no credentials of its own: every caller brings
    | their own key, which is replayed upstream.
    |
    | `default_url` only seeds the first gateway on a fresh migrate, so an
    | existing deployment keeps working without touching its .env.
    |
    | `max_timeout` is a ceiling, not a timeout. The queue's `retry_after` is
    | per connection and cannot vary per gateway, so no gateway may be given
    | a timeout above this — see config/queue.php.
    |
    | The older LITELLM_* names are still honoured as fallbacks.
    |
    */

    'upstream' => [
        'default_url' => strval(env('GATEWAY_SEED_URL', env('LITELLM_URL', 'http://127.0.0.1:4000'))),
        'max_timeout' => intval(env('GATEWAY_MAX_TIMEOUT', env('LITELLM_TIMEOUT', 900))),
        'connect_timeout' => intval(env('GATEWAY_CONNECT_TIMEOUT', env('LITELLM_CONNECT_TIMEOUT', 10))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backlog
    |--------------------------------------------------------------------------
    |
    | Queue name, the number of workers expected to run, and how often a single
    | entry is retried before it is marked failed. Keep `workers` in sync with
    | OLLAMA_NUM_PARALLEL — extra workers only fill the backend's own queue.
    |
    */

    'backlog' => [
        'queue' => strval(env('BACKLOG_QUEUE', 'llm')),
        'workers' => intval(env('BACKLOG_WORKERS', 2)),
        'max_attempts' => intval(env('BACKLOG_MAX_ATTEMPTS', 2)),
        'retry_delay' => intval(env('BACKLOG_RETRY_DELAY', 10)),

        /*
        | Queue requests accepted per minute, counted per caller key (or per
        | IP for keyless callers) rather than for the app as a whole, so one
        | client cannot fill the backlog everyone else is waiting behind.
        | Nothing about the payload is inspected. Set 0 to lift the limit.
        */

        'rate_limit' => intval(env('BACKLOG_RATE_LIMIT', 60)),

        /*
        | What a caller is told about the wait when an entry is queued.
        |
        | The estimate is a projection from how long recent entries for the
        | same model actually took, not a promise: `sample` is how many of
        | them are measured, and `default_seconds` stands in until that many
        | have been answered at all.
        |
        | Only the measurement is cached, for `ttl` seconds — a position is
        | cheap to count and worthless once stale, so it is always read live.
        */

        'estimate' => [
            'sample' => intval(env('BACKLOG_ESTIMATE_SAMPLE', 20)),
            'default_seconds' => intval(env('BACKLOG_ESTIMATE_DEFAULT_SECONDS', 30)),
            'ttl' => intval(env('BACKLOG_ESTIMATE_TTL', 5)),
        ],
    ],

];
