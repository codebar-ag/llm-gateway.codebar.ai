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
    | The dashboard shows whole prompts and answers, so it is guarded twice.
    |
    | An operator account is always required — created with
    | `php artisan user:register` and recovered with
    | `php artisan user:reset-password`, both server-side, since there is no
    | registration route and no web reset flow. This token is a second, shared
    | secret in front of that login, and it is optional: leave it empty and the
    | login alone guards the dashboard; set it and a visitor must present it
    | before they even reach the login form.
    |
    | Neither is a caller key. A caller key buys a view of that caller's own
    | entries through /v1; these two together grant a view of everyone's.
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

        /*
        | How long a `/v1/models` listing is reused, in seconds.
        |
        | Those two routes sit outside the queue rate limit on purpose — a
        | model listing is a cheap, idempotent read that no client should have
        | to poll for — which leaves them reachable through the tunnel, keyless
        | by default, and one upstream request per caller request. The set of
        | models a gateway serves does not change between two of them.
        |
        | Cached per gateway *and* per caller key: the caller's key is replayed
        | upstream and LiteLLM answers a different list per virtual key, so the
        | caller is part of the identity of the answer. Only a successful
        | listing is stored; an upstream error is about this moment, not about
        | the gateway. Set 0 to always ask the upstream.
        */

        'models_cache_ttl' => intval(env('API_MODELS_CACHE_TTL', 30)),
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
        'queue' => strval(env('BACKLOG_QUEUE', 'default')),
        'workers' => intval(env('BACKLOG_WORKERS', 2)),
        'max_attempts' => intval(env('BACKLOG_MAX_ATTEMPTS', 2)),
        'retry_delay' => intval(env('BACKLOG_RETRY_DELAY', 10)),

        /*
        | How many days a finished entry keeps its request and response
        | bodies before `backlog:prune` empties them. The row itself stays —
        | status, model, caller, usage, error and every timestamp — so the
        | dashboard's history never shrinks; only the payload cargo goes.
        | Almost all of it is embedding vectors already collected by their
        | caller and base64 images inside OCR requests. Zero disables pruning.
        */
        'prune_after_days' => intval(env('BACKLOG_PRUNE_DAYS', 7)),

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
        | `ttl` covers everything that is the same answer for every caller: the
        | measurement, the number of entries running, and the depth of the
        | queue. Polling is unthrottled by design, so those are counted once a
        | window and shared rather than recounted per poll.
        |
        | A position in line is not among them. It is cheap to count, it is the
        | one number that actually moves, and it is worthless once stale — so it
        | is read live on every poll.
        */

        'estimate' => [
            'sample' => intval(env('BACKLOG_ESTIMATE_SAMPLE', 20)),
            'default_seconds' => intval(env('BACKLOG_ESTIMATE_DEFAULT_SECONDS', 30)),
            'ttl' => intval(env('BACKLOG_ESTIMATE_TTL', 5)),
        ],
    ],

];
