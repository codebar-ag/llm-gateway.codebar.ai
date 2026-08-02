<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('answers the default gateway synchronously, not through the backlog', function () {
    Http::fake(['http://upstream.invalid/v1/embeddings' => Http::response([
        'object' => 'list',
        'data' => [['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0]],
        'model' => 'text-embedding-3-small',
        'usage' => ['prompt_tokens' => 2, 'total_tokens' => 2],
    ])]);

    $this->postJson('/v1/embeddings', ['model' => 'text-embedding-3-small', 'input' => 'hi'], gatewayAuth())
        ->assertOk()
        ->assertJsonPath('data.0.embedding', [0.1, 0.2]);

    expect(BacklogEntry::count())->toBe(0);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://upstream.invalid/v1/embeddings'
        && $request['input'] === 'hi'
        && $request->hasHeader('Authorization', 'Bearer sk-test-caller'));
});

it('answers under a gateway prefix too', function () {
    $gateway = Gateway::factory()->create(['base_url' => 'http://litellm.test']);
    Http::fake(['http://litellm.test/v1/embeddings' => Http::response(['object' => 'list', 'data' => []])]);

    $this->postJson("/{$gateway->id}/v1/embeddings", ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://litellm.test/v1/embeddings');
});

it('normalises an ollama gateway into an OpenAI embeddings list', function () {
    $gateway = Gateway::factory()->ollama()->create();
    Http::fake(['http://ollama.test:11434/api/embed' => Http::response([
        'model' => 'nomic-embed-text',
        'embeddings' => [[0.1, 0.2]],
        'prompt_eval_count' => 3,
    ])]);

    $this->postJson("/{$gateway->id}/v1/embeddings", ['model' => 'nomic-embed-text', 'input' => 'hi'], gatewayAuth())
        ->assertOk()
        ->assertExactJson([
            'object' => 'list',
            'data' => [['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0]],
            'model' => 'nomic-embed-text',
            'usage' => ['prompt_tokens' => 3, 'total_tokens' => 3],
        ]);
});

it('passes an upstream error through with its own status', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'upstream_error');
});

it('answers 502 when the upstream cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(502)
        ->assertJsonPath('error.type', 'upstream_unreachable');
});

it('shares the queueing rate limit rather than staying unthrottled like a plain read', function () {
    config(['gateway.backlog.rate_limit' => 1]);
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())->assertOk();

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'rate_limited');
});
