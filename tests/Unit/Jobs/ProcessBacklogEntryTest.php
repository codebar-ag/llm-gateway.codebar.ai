<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Services\Upstream\Upstreams;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

function process(BacklogEntry $entry): void
{
    (new ProcessBacklogEntry($entry->id))->handle(new Upstreams);
}

it('completes an entry with output and usage', function () {
    Http::fake(['*/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => 'PONG']]],
        'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 1, 'total_tokens' => 4],
    ])]);

    $entry = BacklogEntry::factory()->create();

    process($entry);

    $entry->refresh();

    expect($entry->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output[0]['content'][0]['text'])->toBe('PONG')
        ->and($entry->usage)->toBe(['input_tokens' => 3, 'output_tokens' => 1, 'total_tokens' => 4])
        ->and($entry->attempts)->toBe(1)
        ->and($entry->started_at)->not->toBeNull()
        ->and($entry->finished_at)->not->toBeNull();
});

it('sends the stored request payload upstream', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'custom', 'messages' => [['role' => 'user', 'content' => 'ping']]],
    ]);

    process($entry);

    Http::assertSent(fn ($request) => $request['model'] === 'custom'
        && $request['messages'][0]['content'] === 'ping');
});

it('hands back a non json answer untouched', function () {
    Http::fake(['*/v1/chat/completions' => Http::response('plain text, not json', 200)]);

    $entry = BacklogEntry::factory()->create();

    process($entry);

    $entry->refresh();

    expect($entry->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output[0]['content'][0]['text'])->toBe('plain text, not json')
        ->and($entry->usage)->toBe([
            'input_tokens' => null,
            'output_tokens' => null,
            'total_tokens' => null,
        ]);
});

it('stores a streamed answer as the raw stream', function () {
    $stream = "data: {\"choices\":[{\"delta\":{\"content\":\"hi\"}}]}\n\ndata: [DONE]\n\n";
    Http::fake(['*/v1/chat/completions' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream'])]);

    $entry = BacklogEntry::factory()->create(['request' => ['model' => 'm', 'stream' => true]]);

    process($entry);

    expect($entry->refresh()->output[0]['content'][0]['text'])->toBe($stream);
});

it('completes with an empty answer for an empty body', function () {
    Http::fake(['*/v1/chat/completions' => Http::response('', 200)]);

    $entry = BacklogEntry::factory()->create();

    process($entry);

    expect($entry->refresh()->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output[0]['content'][0]['text'])->toBe('');
});

it('uses the reasoning fallback for empty answers', function () {
    Http::fake(['*/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => '', 'reasoning_content' => 'thought result']]],
    ])]);

    $entry = BacklogEntry::factory()->create();

    process($entry);

    expect($entry->refresh()->output[0]['content'][0]['text'])->toBe('thought result');
});

it('skips entries that already reached a final status', function (string $state) {
    Http::fake();

    $entry = BacklogEntry::factory()->{$state}()->create();

    process($entry);

    Http::assertNothingSent();
    expect($entry->refresh()->attempts)->toBe($entry->attempts);
})->with(['completed', 'failed', 'cancelled']);

it('skips entries that no longer exist', function () {
    Http::fake();

    (new ProcessBacklogEntry('resp_gone'))->handle(new Upstreams);

    Http::assertNothingSent();
});

it('throws upstream errors so the queue marks the job failed', function () {
    Http::fake(['*/v1/chat/completions' => Http::response('upstream exploded', 500)]);

    $entry = BacklogEntry::factory()->create();

    expect(fn () => process($entry))->toThrow(RequestException::class);
    expect($entry->refresh()->status)->toBe(BacklogStatus::Running);
});

it('requeues with a growing delay while attempts remain', function () {
    Queue::fake();

    $entry = BacklogEntry::factory()->running()->create(['attempts' => 1, 'max_attempts' => 3]);

    (new ProcessBacklogEntry($entry->id))->failed(new RuntimeException('boom'));

    $entry->refresh();

    expect($entry->status)->toBe(BacklogStatus::Queued)
        ->and($entry->started_at)->toBeNull();

    Queue::assertPushed(ProcessBacklogEntry::class, fn (ProcessBacklogEntry $job) => $job->entryId === $entry->id
        && (int) round(now()->diffInSeconds($job->delay)) === 10);
});

it('fails permanently once attempts are exhausted', function () {
    Queue::fake();

    $entry = BacklogEntry::factory()->running()->exhausted()->create();

    (new ProcessBacklogEntry($entry->id))->failed(new RuntimeException('boom'));

    $entry->refresh();

    expect($entry->status)->toBe(BacklogStatus::Failed)
        ->and($entry->error['message'])->toContain('boom')
        ->and($entry->attempts)->toBe(2)
        ->and($entry->finished_at)->not->toBeNull();

    Queue::assertNothingPushed();
});

it('hands the upstream rejection through to the entry', function () {
    Queue::fake();
    Http::fake(['*/v1/chat/completions' => Http::response([
        'error' => ['message' => 'context window exceeded', 'code' => '400'],
    ], 400)]);

    $entry = BacklogEntry::factory()->exhausted()->create();

    $job = new ProcessBacklogEntry($entry->id);

    try {
        $job->handle(new Upstreams);
    } catch (RequestException $exception) {
        $job->failed($exception);
    }

    $entry->refresh();

    expect($entry->status)->toBe(BacklogStatus::Failed)
        ->and($entry->error['status'])->toBe(400)
        ->and($entry->error['body']['error']['message'])->toBe('context window exceeded');
});

it('ignores failures for deleted entries', function () {
    Queue::fake();

    (new ProcessBacklogEntry('resp_gone'))->failed(new RuntimeException('boom'));

    Queue::assertNothingPushed();
});

it('keeps a timeout margin above the http timeout', function () {
    config(['gateway.upstream.max_timeout' => 900]);

    expect((new ProcessBacklogEntry('resp_x'))->timeout())->toBe(960);
});

/*
 | Which upstream an entry goes to is decided once, when it is queued. The
 | worker reads that decision back rather than making it again, so moving the
 | default cannot redirect an answer someone is already waiting for.
 */

it('sends the entry to its own gateway rather than the default', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $entry = BacklogEntry::factory()->create([
        'gateway_id' => Gateway::factory()->create(['base_url' => 'http://elsewhere.test'])->id,
    ]);

    process($entry);

    Http::assertSent(fn ($request) => $request->url() === 'http://elsewhere.test/v1/chat/completions');
});

it('translates for a gateway that does not speak openai', function () {
    Http::fake(['*' => Http::response([
        'id' => 'msg_1',
        'content' => [['type' => 'text', 'text' => 'translated']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
    ])]);

    $entry = BacklogEntry::factory()->create([
        'gateway_id' => Gateway::factory()->anthropic()->create()->id,
        'request' => ['model' => 'claude-sonnet-4-5', 'messages' => [['role' => 'user', 'content' => 'hi']]],
    ]);

    process($entry);

    $entry->refresh();

    expect($entry->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output[0]['content'][0]['text'])->toBe('translated')
        ->and($entry->usage)->toBe(['input_tokens' => 5, 'output_tokens' => 2, 'total_tokens' => 7]);

    Http::assertSent(fn ($request) => $request->url() === 'https://anthropic.test/v1/messages');
});

it('falls back to the default for an entry queued before gateways existed', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $entry = BacklogEntry::factory()->create(['gateway_id' => null]);

    process($entry);

    expect($entry->refresh()->status)->toBe(BacklogStatus::Completed);

    Http::assertSent(fn ($request) => $request->url() === Gateway::default()->endpoint());
});

it('still answers an entry queued for a gateway that has since been deactivated', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $gateway = Gateway::factory()->inactive()->create(['base_url' => 'http://retired.test']);
    $entry = BacklogEntry::factory()->create(['gateway_id' => $gateway->id]);

    process($entry);

    expect($entry->refresh()->status)->toBe(BacklogStatus::Completed);

    Http::assertSent(fn ($request) => $request->url() === 'http://retired.test/v1/chat/completions');
});

it('does not queue a second copy of an entry still waiting to be processed', function () {
    config(['queue.default' => 'database']);

    $entry = BacklogEntry::factory()->create();

    ProcessBacklogEntry::dispatch($entry->id);
    ProcessBacklogEntry::dispatch($entry->id);

    expect(DB::table('jobs')->count())->toBe(1);
});

/*
 | Defensive rather than reachable through the dashboard: a gateway is recorded
 | at queue time and only ever nulled by a hard delete. If it does happen the
 | job has to fail loudly, because silently picking some other upstream would
 | answer the caller's prompt from a box they never addressed.
 */

it('refuses to run an entry with no gateway and no default to fall back on', function () {
    $entry = BacklogEntry::factory()->create();
    $entry->forceFill(['gateway_id' => null])->save();
    Gateway::query()->forceDelete();

    expect(fn () => process($entry))->toThrow(RuntimeException::class, 'has no gateway');

    expect($entry->refresh()->status)->toBe(BacklogStatus::Running);
});

it('logs the caller trace id alongside a permanent failure', function () {
    Queue::fake();
    Log::spy();

    $entry = BacklogEntry::factory()->exhausted()->create([
        'headers' => ['authorization' => 'Bearer sk-x', 'x-request-id' => 'trace-abc'],
    ]);

    (new ProcessBacklogEntry($entry->id))->failed(new RuntimeException('boom'));

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context) => $context['request_id'] === 'trace-abc'
            && $context['entry'] === $entry->id)
        ->once();
});
