<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('answers the default gateway synchronously, not through the backlog', function () {
    Http::fake(['http://upstream.invalid/v1/moderations' => Http::response([
        'id' => 'modr-1',
        'model' => 'omni-moderation-latest',
        'results' => [['flagged' => false]],
    ])]);

    $this->postJson('/v1/moderations', ['input' => 'hello'], gatewayAuth())
        ->assertOk()
        ->assertJsonPath('results.0.flagged', false);

    expect(BacklogEntry::count())->toBe(0);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://upstream.invalid/v1/moderations'
        && $request['input'] === 'hello'
        && $request->hasHeader('Authorization', 'Bearer sk-test-caller'));
});

it('answers under a gateway prefix too', function () {
    $gateway = Gateway::factory()->create(['base_url' => 'http://litellm.test']);
    Http::fake(['http://litellm.test/v1/moderations' => Http::response(['results' => []])]);

    $this->postJson("/{$gateway->id}/v1/moderations", ['input' => 'hi'], gatewayAuth())->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://litellm.test/v1/moderations');
});

/*
 | Neither Anthropic nor Ollama has ever implemented this endpoint. The
 | request still reaches the upstream and comes back as whatever it answers —
 | this app does not pretend to know better.
 */
it('passes an upstream error through with its own status for a protocol that has no moderations endpoint', function () {
    $gateway = Gateway::factory()->ollama()->create();
    Http::fake(['*' => Http::response('404 page not found', 404)]);

    $this->postJson("/{$gateway->id}/v1/moderations", ['input' => 'hi'], gatewayAuth())
        ->assertStatus(404)
        ->assertJsonPath('error.type', 'upstream_error')
        ->assertJsonPath('error.body', '404 page not found');
});

it('answers 502 when the upstream cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->postJson('/v1/moderations', ['input' => 'hi'], gatewayAuth())
        ->assertStatus(502)
        ->assertJsonPath('error.type', 'upstream_unreachable');
});

it('shares the queueing rate limit rather than staying unthrottled like a plain read', function () {
    config(['gateway.backlog.rate_limit' => 1]);
    Http::fake(['*' => Http::response(['results' => []])]);

    $this->postJson('/v1/moderations', ['input' => 'hi'], gatewayAuth())->assertOk();

    $this->postJson('/v1/moderations', ['input' => 'hi'], gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'rate_limited');
});
