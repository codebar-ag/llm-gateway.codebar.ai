<?php

declare(strict_types=1);

use App\Enums\BacklogKind;
use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Database\Factories\BacklogEntryFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('answers the default gateway synchronously, without waiting for a worker', function () {
    Http::fake(['http://upstream.invalid/v1/embeddings' => Http::response([
        'object' => 'list',
        'data' => [['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0]],
        'model' => 'text-embedding-3-small',
        'usage' => ['prompt_tokens' => 2, 'total_tokens' => 2],
    ])]);

    $this->postJson('/v1/embeddings', ['model' => 'text-embedding-3-small', 'input' => 'hi'], gatewayAuth())
        ->assertOk()
        ->assertJsonPath('data.0.embedding', [0.1, 0.2]);

    // Answered without a worker, but not unlogged: the entry is written in the
    // ending it already reached, which is what puts it in the dashboard.
    expect(BacklogEntry::sole())
        ->status->toBe(BacklogStatus::Completed)
        ->kind->toBe(BacklogKind::Embeddings)
        ->model->toBe('text-embedding-3-small')
        ->attempts->toBe(1)
        ->max_attempts->toBe(1);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://upstream.invalid/v1/embeddings'
        && $request['input'] === 'hi'
        && $request->hasHeader('Authorization', 'Bearer sk-test-caller'));
});

it('logs what was asked, what it cost and how long it took', function () {
    Http::fake(['*' => Http::response([
        'object' => 'list',
        'data' => [
            ['object' => 'embedding', 'embedding' => [0.1, 0.2, 0.3], 'index' => 0],
            ['object' => 'embedding', 'embedding' => [0.4, 0.5, 0.6], 'index' => 1],
        ],
        'model' => 'text-embedding-3-small',
        'usage' => ['prompt_tokens' => 7, 'total_tokens' => 7],
    ])]);

    $this->postJson('/v1/embeddings', [
        'model' => 'text-embedding-3-small',
        'input' => ['first', 'second'],
    ], gatewayAuth())->assertOk();

    $entry = BacklogEntry::sole();

    expect($entry->request)->toBe(['model' => 'text-embedding-3-small', 'input' => ['first', 'second']])
        ->and($entry->usage)->toBe(['input_tokens' => 7, 'output_tokens' => null, 'total_tokens' => 7])
        ->and($entry->started_at)->not->toBeNull()
        ->and($entry->finished_at)->not->toBeNull()
        // The caller has the vectors; storing megabytes of floats to say so
        // again is what the entry keeps their shape instead of.
        ->and(data_get($entry->output, '0.content.0.text'))->toBe('2 embeddings × 3 dimensions');
});

it('records the caller so an embedding is attributable to a key', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())->assertOk();

    expect(BacklogEntry::sole())
        ->api_key_hash->toBe(hash('sha256', BacklogEntryFactory::CALLER_KEY))
        // Cleared like every other ending's: they exist so a worker can replay
        // the request, and this one has already been answered.
        ->headers->toBeNull();
});

it('logs the models it has seen, so the dashboard can filter by them', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->postJson('/v1/embeddings', ['model' => 'nomic-embed-text', 'input' => 'hi'], gatewayAuth())->assertOk();

    expect(BacklogEntry::distinctModels()->all())->toBe(['nomic-embed-text']);
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

it('passes an upstream error through with its own status, and logs the failure', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'upstream_error');

    $entry = BacklogEntry::sole();

    expect($entry->status)->toBe(BacklogStatus::Failed)
        ->and($entry->error['status'])->toBe(401)
        ->and($entry->error['type'])->toBe('upstream_error')
        // What the entry says went wrong is what the caller was told, read
        // back out of their own answer rather than described a second time.
        ->and($entry->error['body'])->toBe(['error' => ['message' => 'bad key']])
        ->and($entry->output)->toBeNull();
});

it('answers 502 when the upstream cannot be reached, and logs that too', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(502)
        ->assertJsonPath('error.type', 'upstream_unreachable');

    expect(BacklogEntry::sole())
        ->status->toBe(BacklogStatus::Failed)
        ->and(BacklogEntry::sole()->error['type'])->toBe('upstream_unreachable');
});

it('shares the queueing rate limit rather than staying unthrottled like a plain read', function () {
    config(['gateway.backlog.rate_limit' => 1]);
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())->assertOk();

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'rate_limited');
});
