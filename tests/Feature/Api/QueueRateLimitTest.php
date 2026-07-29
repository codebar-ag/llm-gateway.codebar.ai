<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/*
 | Only how often a request arrives is capped — never what is in it. Nothing
 | here asserts anything about the payload, because the gateway validates none.
 */

beforeEach(function () {
    Queue::fake();
    config()->set('gateway.backlog.rate_limit', 3);
});

function queueOnce(array $headers = []): TestResponse
{
    return test()->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], $headers);
}

it('accepts requests up to the configured limit', function () {
    foreach (range(1, 3) as $attempt) {
        queueOnce(gatewayAuth())->assertStatus(202);
    }

    expect(BacklogEntry::count())->toBe(3);
});

it('refuses the request past the limit', function () {
    foreach (range(1, 3) as $attempt) {
        queueOnce(gatewayAuth());
    }

    $response = queueOnce(gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'rate_limited')
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))
        ->toBeGreaterThan(0)
        ->toBeLessThanOrEqual(60);
});

it('does not create an entry for a throttled request', function () {
    foreach (range(1, 5) as $attempt) {
        queueOnce(gatewayAuth());
    }

    expect(BacklogEntry::count())->toBe(3);
});

it('counts chat completions against the same budget', function () {
    foreach (range(1, 3) as $attempt) {
        queueOnce(gatewayAuth());
    }

    $this->postJson('/v1/chat/completions', ['model' => 'm'], gatewayAuth())
        ->assertStatus(429);
});

it('gives every caller key its own budget', function () {
    foreach (range(1, 3) as $attempt) {
        queueOnce(['Authorization' => 'Bearer sk-alice']);
    }

    queueOnce(['Authorization' => 'Bearer sk-alice'])->assertStatus(429);
    queueOnce(['Authorization' => 'Bearer sk-bob'])->assertStatus(202);
});

/*
 | A keyless caller is bucketed by IP, which is only as trustworthy as
 | TRUSTED_PROXIES. With `*` there, every spoofed X-Forwarded-For was a fresh
 | bucket and the limit did not exist for anyone who bothered to rotate one.
 */

it('does not let a keyless caller buy a new budget with X-Forwarded-For', function () {
    $fromTheInternet = ['REMOTE_ADDR' => '203.0.113.9'];

    foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $spoofed) {
        test()->withServerVariables($fromTheInternet)
            ->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], ['X-Forwarded-For' => $spoofed])
            ->assertStatus(202);
    }

    test()->withServerVariables($fromTheInternet)
        ->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], ['X-Forwarded-For' => '4.4.4.4'])
        ->assertStatus(429);
});

it('lifts the limit when it is configured to zero', function () {
    config()->set('gateway.backlog.rate_limit', 0);

    foreach (range(1, 12) as $attempt) {
        queueOnce(gatewayAuth())->assertStatus(202);
    }

    expect(BacklogEntry::count())->toBe(12);
});

it('never throttles reading an answer back', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    foreach (range(1, 10) as $attempt) {
        $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())->assertOk();
    }

    $this->getJson('/v1/responses', gatewayAuth())->assertOk();
});

it('still queues a body the gateway does not understand', function () {
    $this->postJson('/v1/responses', ['nonsense' => true], gatewayAuth())->assertStatus(202);

    expect(BacklogEntry::count())->toBe(1);
});
