<?php

declare(strict_types=1);

use App\Models\Gateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('answers synchronously with the matching model from the gateway list', function () {
    Http::fake(['http://upstream.invalid/v1/models' => Http::response([
        'object' => 'list',
        'data' => [
            ['id' => 'gpt-4', 'object' => 'model', 'created' => 1686935002, 'owned_by' => 'openai'],
            ['id' => 'gpt-4o', 'object' => 'model', 'created' => 1715367049, 'owned_by' => 'openai'],
        ],
    ])]);

    $this->getJson('/v1/models/gpt-4o', gatewayAuth())
        ->assertOk()
        ->assertExactJson(['id' => 'gpt-4o', 'object' => 'model', 'created' => 1715367049, 'owned_by' => 'openai']);
});

it('reports a model the gateway does not list as not found', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->getJson('/v1/models/no-such-model', gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'model_not_found');
});

/* OpenRouter-style ids commonly carry a slash, e.g. "anthropic/claude-3.5-sonnet". */
it('matches an id that contains a slash', function () {
    Http::fake(['*' => Http::response([
        'object' => 'list',
        'data' => [['id' => 'anthropic/claude-3.5-sonnet', 'object' => 'model', 'created' => 1, 'owned_by' => 'openrouter']],
    ])]);

    $this->getJson('/v1/models/anthropic/claude-3.5-sonnet', gatewayAuth())
        ->assertOk()
        ->assertJsonPath('id', 'anthropic/claude-3.5-sonnet');
});

it('answers under a gateway prefix too', function () {
    $gateway = Gateway::factory()->create(['base_url' => 'http://litellm.test']);
    Http::fake(['http://litellm.test/v1/models' => Http::response([
        'object' => 'list',
        'data' => [['id' => 'm', 'object' => 'model', 'created' => 1, 'owned_by' => 'openai']],
    ])]);

    $this->getJson("/{$gateway->id}/v1/models/m", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('id', 'm');
});

/*
 | One model is read out of the listing this gateway's caller already has, so
 | asking for two of them costs the upstream one request, not two — and never
 | a request made under someone else's key.
 */
it('shares the cached listing with the models endpoint', function () {
    Http::fake(['*' => Http::response([
        'object' => 'list',
        'data' => [
            ['id' => 'gpt-4', 'object' => 'model', 'created' => 1686935002, 'owned_by' => 'openai'],
            ['id' => 'gpt-4o', 'object' => 'model', 'created' => 1715367049, 'owned_by' => 'openai'],
        ],
    ])]);

    $this->getJson('/v1/models', gatewayAuth())->assertOk();
    $this->getJson('/v1/models/gpt-4o', gatewayAuth())->assertOk()->assertJsonPath('id', 'gpt-4o');
    $this->getJson('/v1/models/gpt-4', gatewayAuth())->assertOk()->assertJsonPath('id', 'gpt-4');

    Http::assertSentCount(1);
});

it('never answers one caller out of another caller s cached listing', function () {
    Http::fake(fn (Request $request) => Http::response([
        'object' => 'list',
        'data' => $request->hasHeader('Authorization', 'Bearer sk-one')
            ? [['id' => 'sk-one-only', 'object' => 'model', 'created' => 1, 'owned_by' => 'openai']]
            : [],
    ]));

    $this->getJson('/v1/models/sk-one-only', ['Authorization' => 'Bearer sk-one'])->assertOk();
    $this->getJson('/v1/models/sk-one-only', ['Authorization' => 'Bearer sk-two'])->assertNotFound();

    Http::assertSentCount(2);
});

it('passes an upstream error straight through rather than reporting not found', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    $this->getJson('/v1/models/gpt-4', gatewayAuth())
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'upstream_error');
});
