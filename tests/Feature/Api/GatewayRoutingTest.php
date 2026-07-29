<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

it('sends a request without a prefix to the default gateway', function () {
    $response = $this->postJson('/v1/chat/completions', ['model' => 'm', 'messages' => []], gatewayAuth());

    $response->assertStatus(202);

    expect(BacklogEntry::sole()->gateway_id)->toBe(Gateway::default()->id);
});

it('sends a prefixed request to the gateway it names', function () {
    $gateway = Gateway::factory()->create();

    $this->postJson("/{$gateway->id}/v1/chat/completions", ['model' => 'm'], gatewayAuth())
        ->assertStatus(202);

    expect(BacklogEntry::sole()->gateway_id)->toBe($gateway->id);
});

it('points poll_url back under the prefix the caller used', function () {
    $gateway = Gateway::factory()->create();

    $id = $this->postJson("/{$gateway->id}/v1/chat/completions", ['model' => 'm'], gatewayAuth())
        ->json('id');

    $this->postJson("/{$gateway->id}/v1/chat/completions", ['model' => 'm'], gatewayAuth())
        ->assertJsonPath('poll_url', fn (string $url) => str_starts_with($url, "/{$gateway->id}/v1/responses/"));

    expect($id)->toStartWith('resp_');
});

it('leaves poll_url at the root for a request without a prefix', function () {
    $this->postJson('/v1/chat/completions', ['model' => 'm'], gatewayAuth())
        ->assertJsonPath('poll_url', fn (string $url) => str_starts_with($url, '/v1/responses/'));
});

it('routes the responses endpoint by prefix too', function () {
    $gateway = Gateway::factory()->create();

    $this->postJson("/{$gateway->id}/v1/responses", ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202);

    expect(BacklogEntry::sole()->gateway_id)->toBe($gateway->id);
});

/*
 | An SDK is given one base_url — https://host/<id>/v1 — and polls off the same
 | root, so every read has to answer under the prefix as well as without it.
 */
it('lets a caller poll under the same prefix they queued with', function () {
    $gateway = Gateway::factory()->create();
    $entry = BacklogEntry::factory()->completed()->create(['gateway_id' => $gateway->id]);

    $this->getJson("/{$gateway->id}/v1/responses/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('id', $entry->id);

    $this->getJson("/{$gateway->id}/v1/responses", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('data.0.id', $entry->id);
});

it('reports an unknown gateway as a not found in the api envelope', function () {
    $this->postJson('/'.Str::uuid7()->toString().'/v1/chat/completions', ['model' => 'm'], gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'gateway_not_found');
});

it('hides a deactivated gateway from callers', function () {
    $gateway = Gateway::factory()->inactive()->create();

    $this->postJson("/{$gateway->id}/v1/chat/completions", ['model' => 'm'], gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'gateway_not_found');
});

it('says so plainly when no gateway is the default', function () {
    Gateway::query()->update(['is_default' => false]);

    $this->postJson('/v1/chat/completions', ['model' => 'm'], gatewayAuth())
        ->assertStatus(503)
        ->assertJsonPath('error.type', 'no_default_gateway');
});

it('stops taking work once the default itself is deactivated', function () {
    Gateway::query()->update(['is_active' => false]);

    $this->postJson('/v1/chat/completions', ['model' => 'm'], gatewayAuth())
        ->assertStatus(503)
        ->assertJsonPath('error.type', 'no_default_gateway');

    expect(BacklogEntry::query()->count())->toBe(0);
});

it('does not treat a non uuid segment as a gateway', function () {
    $this->postJson('/not-a-uuid/v1/chat/completions', ['model' => 'm'], gatewayAuth())
        ->assertNotFound();

    expect(BacklogEntry::count())->toBe(0);
});

/*
 | The exception handler decides between the JSON envelope and an HTML error
 | page by matching the path. A gateway id in front of /v1 changes that path,
 | which is easy to get wrong and invisible until a caller sees a stack trace.
 */
it('keeps the json envelope for prefixed requests', function () {
    $gateway = Gateway::factory()->create();

    $this->getJson("/{$gateway->id}/v1/responses/resp_missing", gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'not_found');
});

it('keeps the rate limit envelope for prefixed requests', function () {
    config(['gateway.backlog.rate_limit' => 1]);

    $gateway = Gateway::factory()->create();

    $this->postJson("/{$gateway->id}/v1/chat/completions", ['model' => 'm'], gatewayAuth())->assertStatus(202);

    $this->postJson("/{$gateway->id}/v1/chat/completions", ['model' => 'm'], gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'rate_limited');
});

it('still refuses unknown prefixed endpoints as async only', function () {
    $gateway = Gateway::factory()->create();

    $this->postJson("/{$gateway->id}/v1/embeddings", [], gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'async_only');
});

it('leaves the health check reachable', function () {
    $this->get('/up')->assertOk();
});
