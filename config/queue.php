<?php

declare(strict_types=1);

/*
 | Partial override — all other queue options come from the framework
 | defaults (vendor/laravel/framework/config/queue.php). `connections` is
 | merged one level deep, so the database connection is restated in full.
 */

return [

    'connections' => [

        /*
        |----------------------------------------------------------------------
        | Database
        |----------------------------------------------------------------------
        |
        | `retry_after` is how long the queue waits before deciding a reserved
        | job died with its worker and handing it to another one. It MUST
        | exceed the job's own timeout, or the queue hands a still-running LLM
        | call to a second worker and the model answers the same prompt twice.
        |
        | ProcessBacklogEntry::timeout() is GATEWAY_MAX_TIMEOUT + 60, so this
        | sits a further minute beyond that. Raising GATEWAY_MAX_TIMEOUT moves
        | both. Individual gateways may be slower than each other but never
        | slower than the ceiling, which is what keeps this margin true.
        |
        */

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => strval(env('DB_QUEUE_TABLE', 'jobs')),
            'queue' => strval(env('DB_QUEUE', 'default')),
            /*
            | The env chain is repeated from config/gateway.php rather than read
            | back out of it. Reading `config('gateway.…')` here would work only
            | because config files happen to load alphabetically, and the value
            | arrives as `mixed`. The duplication is the safer of the two, and it
            | is not unguarded: tests/Unit/Queue/RetryAfterTest.php fails the
            | moment this margin and the ceiling drift apart.
            */
            'retry_after' => intval(env('GATEWAY_MAX_TIMEOUT', env('LITELLM_TIMEOUT', 900))) + 120,
            'after_commit' => false,
        ],

    ],

];
