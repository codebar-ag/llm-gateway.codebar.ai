<?php

declare(strict_types=1);

use App\Enums\BacklogKind;
use App\Enums\BacklogStatus;
use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Database\Factories\BacklogEntryFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 | Embeddings queue like everything else that costs the upstream compute. An
 | indexing run posts tens of thousands of batches; answering them inline is
 | what let every one of them reach the backend at once, which is the pile-up
 | this app exists to absorb.
 */

it('queues the request and hands back an id to poll', function () {
    Queue::fake();

    $response = $this->postJson('/v1/embeddings', [
        'model' => 'nomic-embed-text',
        'input' => ['first', 'second'],
    ], gatewayAuth())->assertStatus(202);

    $entry = BacklogEntry::sole();

    $response
        ->assertJsonPath('id', $entry->id)
        ->assertJsonPath('object', 'embedding.queued')
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('model', 'nomic-embed-text')
        ->assertJsonPath('poll_url', "/v1/embeddings/{$entry->id}")
        ->assertJsonPath('queue.position', 1);

    Queue::assertPushed(ProcessBacklogEntry::class);
});

it('writes the entry when the request is accepted, before anything goes upstream', function () {
    Queue::fake();

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())->assertStatus(202);

    // The row exists while the upstream has not been called at all. That is
    // what makes a backend that never answers visible rather than silent.
    expect(BacklogEntry::sole())
        ->status->toBe(BacklogStatus::Queued)
        ->kind->toBe(BacklogKind::Embeddings)
        ->request->toBe(['model' => 'm', 'input' => 'hi'])
        ->api_key_hash->toBe(hash('sha256', BacklogEntryFactory::CALLER_KEY));

    Http::assertNothingSent();
});

it('polls back under the gateway prefix it was queued through', function () {
    Queue::fake();
    $gateway = Gateway::factory()->create();

    $response = $this->postJson("/{$gateway->id}/v1/embeddings", ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202);

    $id = BacklogEntry::sole()->id;

    $response->assertJsonPath('poll_url', "/{$gateway->id}/v1/embeddings/{$id}");
});

it('is answered by a worker, which stores the vectors for collection', function () {
    Http::fake(['http://upstream.invalid/v1/embeddings' => Http::response([
        'object' => 'list',
        'data' => [['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0]],
        'model' => 'text-embedding-3-small',
        'usage' => ['prompt_tokens' => 2, 'total_tokens' => 2],
    ])]);

    // QUEUE_CONNECTION=sync, so the worker runs before the response returns.
    $this->postJson('/v1/embeddings', ['model' => 'text-embedding-3-small', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202);

    $entry = BacklogEntry::sole();

    expect($entry->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output['data'][0]['embedding'])->toBe([0.1, 0.2])
        ->and($entry->usage)->toBe(['input_tokens' => 2, 'output_tokens' => null, 'total_tokens' => 2]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://upstream.invalid/v1/embeddings'
        && $request['input'] === 'hi'
        && $request->hasHeader('Authorization', 'Bearer sk-test-caller'));
});

it('normalises an ollama gateway into an OpenAI embeddings list', function () {
    $gateway = Gateway::factory()->ollama()->create();
    Http::fake(['http://ollama.test:11434/api/embed' => Http::response([
        'model' => 'nomic-embed-text',
        'embeddings' => [[0.1, 0.2]],
        'prompt_eval_count' => 3,
    ])]);

    $this->postJson("/{$gateway->id}/v1/embeddings", ['model' => 'nomic-embed-text', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202);

    expect(BacklogEntry::sole()->output)->toBe([
        'object' => 'list',
        'data' => [['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0]],
        'model' => 'nomic-embed-text',
        'usage' => ['prompt_tokens' => 3, 'total_tokens' => 3],
    ]);
});

/*
 | The failure paths live in tests/Unit/Jobs/ProcessBacklogEntryTest.php, where
 | the worker runs them. They cannot be driven through the endpoint: under
 | QUEUE_CONNECTION=sync a throwing job rethrows into the request that queued
 | it, which is the one thing a real worker never does to a caller.
 */

it('shares the queueing rate limit', function () {
    Queue::fake();
    config(['gateway.backlog.rate_limit' => 1]);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())->assertStatus(202);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'rate_limited');
});

it('lists the models it has seen, so the dashboard can filter by them', function () {
    Queue::fake();

    $this->postJson('/v1/embeddings', ['model' => 'nomic-embed-text', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202);

    expect(BacklogEntry::distinctModels()->all())->toBe(['nomic-embed-text']);
});

it('names the gateway-s own model when the caller left it out', function () {
    Queue::fake();
    $gateway = Gateway::factory()->create(['default_model' => 'nomic-embed-text']);

    $this->postJson("/{$gateway->id}/v1/embeddings", ['input' => 'hi'], gatewayAuth())->assertStatus(202);

    // The same rule a chat completion follows: a box that serves one model can
    // name it, so a caller talking to it does not have to.
    expect(BacklogEntry::sole())
        ->model->toBe('nomic-embed-text')
        ->request->toBe(['model' => 'nomic-embed-text', 'input' => 'hi']);
});

it('leaves a model the caller did name alone', function () {
    Queue::fake();
    $gateway = Gateway::factory()->create(['default_model' => 'other-model']);

    $this->postJson("/{$gateway->id}/v1/embeddings", ['model' => 'mine', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202);

    expect(BacklogEntry::sole()->model)->toBe('mine');
});

it('queues an embedding with no model at all rather than refusing it', function () {
    Queue::fake();

    // Nothing here validates a payload — the upstream decides. A row with no
    // model is still a row, and the dashboard reads it as "—".
    $this->postJson('/v1/embeddings', ['input' => 'hi'], gatewayAuth())->assertStatus(202);

    expect(BacklogEntry::sole()->model)->toBeNull();
});

it('takes its place behind whatever is already in the queue', function () {
    Queue::fake();
    BacklogEntry::factory()->count(3)->create();

    // One queue for every endpoint: an embedding waits behind the chat
    // completions in front of it, which is what serialising the backend means.
    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202)
        ->assertJsonPath('queue.position', 4)
        ->assertJsonPath('queue.depth', 4);
});

it('keeps one caller-s batch out of another caller-s rate limit', function () {
    Queue::fake();
    config(['gateway.backlog.rate_limit' => 1]);

    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], gatewayAuth())->assertStatus(202);

    // Counted per key, so an indexing run cannot lock everyone else out.
    $this->postJson('/v1/embeddings', ['model' => 'm', 'input' => 'hi'], ['Authorization' => 'Bearer sk-other'])
        ->assertStatus(202);
});
