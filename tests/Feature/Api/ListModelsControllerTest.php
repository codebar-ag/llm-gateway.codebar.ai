<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('answers the default gateway synchronously, not through the backlog', function () {
    Http::fake(['http://upstream.invalid/v1/models' => Http::response([
        'object' => 'list',
        'data' => [['id' => 'gpt-4', 'object' => 'model', 'created' => 1686935002, 'owned_by' => 'openai']],
    ])]);

    $this->getJson('/v1/models', gatewayAuth())
        ->assertOk()
        ->assertExactJson([
            'object' => 'list',
            'data' => [['id' => 'gpt-4', 'object' => 'model', 'created' => 1686935002, 'owned_by' => 'openai']],
        ]);

    expect(BacklogEntry::count())->toBe(0);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'http://upstream.invalid/v1/models'
        && $request->hasHeader('Authorization', 'Bearer sk-test-caller'));
});

it('answers under a gateway prefix too', function () {
    $gateway = Gateway::factory()->create(['base_url' => 'http://litellm.test']);
    Http::fake(['http://litellm.test/v1/models' => Http::response(['object' => 'list', 'data' => []])]);

    $this->getJson("/{$gateway->id}/v1/models", gatewayAuth())->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://litellm.test/v1/models');
});

it('normalises an anthropic gateway into an OpenAI models list', function () {
    $gateway = Gateway::factory()->anthropic()->create();
    Http::fake(['https://anthropic.test/v1/models' => Http::response([
        'data' => [
            ['type' => 'model', 'id' => 'claude-sonnet-4-5', 'display_name' => 'Claude Sonnet 4.5', 'created_at' => '2025-09-29T00:00:00Z'],
        ],
    ])]);

    $this->getJson("/{$gateway->id}/v1/models", gatewayAuth())
        ->assertOk()
        ->assertExactJson([
            'object' => 'list',
            'data' => [[
                'id' => 'claude-sonnet-4-5',
                'object' => 'model',
                'created' => strtotime('2025-09-29T00:00:00Z'),
                'owned_by' => 'anthropic',
            ]],
        ]);
});

it('moves the caller bearer token into x-api-key for an anthropic gateway', function () {
    $gateway = Gateway::factory()->anthropic()->create();
    Http::fake(['*' => Http::response(['data' => []])]);

    $this->getJson("/{$gateway->id}/v1/models", gatewayAuth());

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'sk-test-caller')
        && $request->header('Authorization') === []);
});

it('normalises an ollama gateway into an OpenAI models list', function () {
    $gateway = Gateway::factory()->ollama()->create();
    Http::fake(['http://ollama.test:11434/api/tags' => Http::response([
        'models' => [
            ['name' => 'llama3.2:3b', 'model' => 'llama3.2:3b', 'modified_at' => '2026-07-29T05:00:00Z'],
        ],
    ])]);

    $this->getJson("/{$gateway->id}/v1/models", gatewayAuth())
        ->assertOk()
        ->assertExactJson([
            'object' => 'list',
            'data' => [[
                'id' => 'llama3.2:3b',
                'object' => 'model',
                'created' => strtotime('2026-07-29T05:00:00Z'),
                'owned_by' => 'ollama',
            ]],
        ]);
});

it('passes an upstream error through with its own status', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    $this->getJson('/v1/models', gatewayAuth())
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'upstream_error')
        ->assertJsonPath('error.body.error.message', 'bad key');
});

it('answers 502 when the upstream cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->getJson('/v1/models', gatewayAuth())
        ->assertStatus(502)
        ->assertJsonPath('error.type', 'upstream_unreachable');
});

/*
 | Caching. These routes are deliberately outside `throttle:queue`, reachable
 | through the tunnel and keyless by default, so the cache is what stands
 | between a burst of public reads and the same burst hitting the upstream.
 */

it('asks the upstream once for repeated listings from the same caller', function () {
    Http::fake(['*' => Http::response([
        'object' => 'list',
        'data' => [['id' => 'gpt-4', 'object' => 'model', 'created' => 1, 'owned_by' => 'openai']],
    ])]);

    $first = $this->getJson('/v1/models', gatewayAuth())->assertOk();
    $second = $this->getJson('/v1/models', gatewayAuth())->assertOk();

    expect($second->json())->toBe($first->json());
    Http::assertSentCount(1);
});

/*
 | The one that matters: a caller's key is replayed upstream and LiteLLM
 | answers a different model list per virtual key, so a cache keyed on the
 | gateway alone would hand one caller another caller's models.
 */
it('never serves one caller the model list of another', function () {
    Http::fake(fn (Request $request) => Http::response([
        'object' => 'list',
        'data' => [[
            'id' => 'model-for-'.Str::after((string) $request->header('Authorization')[0], 'Bearer '),
            'object' => 'model',
            'created' => 1,
            'owned_by' => 'openai',
        ]],
    ]));

    $first = $this->getJson('/v1/models', ['Authorization' => 'Bearer sk-one'])->assertOk();
    $second = $this->getJson('/v1/models', ['Authorization' => 'Bearer sk-two'])->assertOk();

    expect($first->json('data.0.id'))->toBe('model-for-sk-one')
        ->and($second->json('data.0.id'))->toBe('model-for-sk-two')
        ->and($second->json())->not->toBe($first->json());

    Http::assertSentCount(2);
});

it('caches keyless callers together, the bucket they already share', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->getJson('/v1/models')->assertOk();
    $this->getJson('/v1/models')->assertOk();

    Http::assertSentCount(1);
});

it('keys the cache per gateway, not per app', function () {
    $other = Gateway::factory()->create(['base_url' => 'http://litellm.test']);
    Http::fake([
        'http://upstream.invalid/*' => Http::response(['object' => 'list', 'data' => []]),
        'http://litellm.test/*' => Http::response(['object' => 'list', 'data' => []]),
    ]);

    $this->getJson('/v1/models', gatewayAuth())->assertOk();
    $this->getJson("/{$other->id}/v1/models", gatewayAuth())->assertOk();

    Http::assertSentCount(2);
});

it('does not cache an upstream error', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    $this->getJson('/v1/models', gatewayAuth())->assertStatus(401);
    $this->getJson('/v1/models', gatewayAuth())->assertStatus(401);

    Http::assertSentCount(2);
});

/* A connection that never happened records nothing, so the attempts are counted by hand. */
it('does not cache an unreachable upstream', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection refused');
    });

    $this->getJson('/v1/models', gatewayAuth())->assertStatus(502);
    $this->getJson('/v1/models', gatewayAuth())->assertStatus(502);

    expect($attempts)->toBe(2);
});

it('asks the upstream every time when the cache is switched off', function () {
    config()->set('gateway.api.models_cache_ttl', 0);
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->getJson('/v1/models', gatewayAuth())->assertOk();
    $this->getJson('/v1/models', gatewayAuth())->assertOk();

    Http::assertSentCount(2);
});

it('reports an unknown gateway the same way every other endpoint does', function () {
    $this->getJson('/'.Str::uuid7()->toString().'/v1/models', gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'gateway_not_found');
});
