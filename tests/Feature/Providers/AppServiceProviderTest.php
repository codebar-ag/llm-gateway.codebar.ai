<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

/*
 | TrustProxies::at(config('gateway.trusted_proxies')) (default '*') is set
 | once at boot and has no public getter, so it is proven here through its
 | only externally visible effect: a keyless caller's rate-limit bucket is
 | keyed on $request->ip(), which only reflects X-Forwarded-For once the
 | sender is a trusted proxy. If the boot() call regressed, every keyless
 | request would share one bucket regardless of the header below.
 */

it('trusts a forwarded IP for the rate limiter used by keyless callers', function () {
    Queue::fake();
    config(['gateway.backlog.rate_limit' => 1]);

    $this->postJson('/v1/responses', ['model' => 'm'], ['X-Forwarded-For' => '203.0.113.10'])
        ->assertStatus(202);

    $this->postJson('/v1/responses', ['model' => 'm'], ['X-Forwarded-For' => '203.0.113.10'])
        ->assertStatus(429);

    $this->postJson('/v1/responses', ['model' => 'm'], ['X-Forwarded-For' => '198.51.100.20'])
        ->assertStatus(202);
});
