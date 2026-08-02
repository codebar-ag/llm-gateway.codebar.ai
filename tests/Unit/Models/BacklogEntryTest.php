<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('generates prefixed ids', function () {
    expect(BacklogEntry::newId())->toStartWith('resp_')
        ->and(BacklogEntry::newId())->not->toBe(BacklogEntry::newId());
});

it('casts columns to their php types', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $fresh = $entry->fresh();

    expect($fresh->status)->toBeInstanceOf(BacklogStatus::class)
        ->and($fresh->request)->toBeArray()
        ->and($fresh->headers)->toBeArray()
        ->and($fresh->attempts)->toBe(1)
        ->and($fresh->started_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->created_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('reports no wait or duration before a worker has started', function () {
    $entry = BacklogEntry::factory()->create();

    expect($entry->waitedSeconds())->toBeNull()
        ->and($entry->durationSeconds())->toBeNull();
});

it('reports no duration for a running entry that has not finished', function () {
    $entry = BacklogEntry::factory()->running()->create();

    expect($entry->waitedSeconds())->not->toBeNull()
        ->and($entry->durationSeconds())->toBeNull();
});

it('measures how long an entry waited before a worker picked it up', function () {
    $created = CarbonImmutable::parse('2026-01-01 00:00:00');
    $entry = BacklogEntry::factory()->create(['created_at' => $created]);

    $entry->update(['started_at' => $created->addSeconds(10)]);

    expect($entry->waitedSeconds())->toBe(10);
});

it('measures how long the upstream call took', function () {
    $started = CarbonImmutable::parse('2026-01-01 00:00:00');
    $entry = BacklogEntry::factory()->create();

    $entry->update(['started_at' => $started, 'finished_at' => $started->addSeconds(4)]);

    expect($entry->durationSeconds())->toBe(4);
});

it('can retry while attempts remain', function () {
    expect(BacklogEntry::factory()->create(['attempts' => 0, 'max_attempts' => 2])->canRetry())->toBeTrue()
        ->and(BacklogEntry::factory()->create(['attempts' => 1, 'max_attempts' => 2])->canRetry())->toBeTrue()
        ->and(BacklogEntry::factory()->exhausted()->create()->canRetry())->toBeFalse();
});

it('claims a queued entry and counts the attempt', function () {
    $entry = BacklogEntry::factory()->create();

    expect($entry->claim())->toBeTrue()
        ->and($entry->status)->toBe(BacklogStatus::Running)
        ->and($entry->attempts)->toBe(1)
        ->and($entry->started_at)->not->toBeNull();
});

it('marks itself completed with output and usage', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markCompleted('PONG', [
        'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 2, 'total_tokens' => 10],
    ]);

    expect($entry->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output[0]['content'][0]['text'])->toBe('PONG')
        ->and($entry->output[0]['role'])->toBe('assistant')
        ->and($entry->usage)->toBe(['input_tokens' => 8, 'output_tokens' => 2, 'total_tokens' => 10])
        ->and($entry->error)->toBeNull()
        ->and($entry->finished_at)->not->toBeNull();
});

/*
 | A completion that asked for a tool. The calls are output items of their own
 | beside the message, which is what a caller polls for and answers on the
 | next turn — reducing the completion to its text is what used to drop them.
 */

it('keeps the tool calls a completion asked for', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markCompleted('einen Moment', ['choices' => [['message' => [
        'content' => 'einen Moment',
        'tool_calls' => [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
        ]],
    ]]]]);

    expect($entry->output)->toHaveCount(2)
        ->and($entry->output[0]['type'])->toBe('message')
        ->and($entry->output[1])->toMatchArray([
            'type' => 'function_call',
            'call_id' => 'call_1',
            'name' => 'count_invoices',
            'arguments' => '{"archive":"main"}',
        ])
        ->and($entry->answerText())->toBe('einen Moment');
});

/*
 | A model that only asked for a tool wrote no text, and an empty message item
 | beside the call would read as an assistant that answered with nothing.
 */
it('states no message item when the turn was only a tool call', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markCompleted('', ['choices' => [['message' => ['tool_calls' => [[
        'id' => 'call_1',
        'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
    ]]]]]]);

    expect($entry->output)->toHaveCount(1)
        ->and($entry->output[0]['type'])->toBe('function_call')
        ->and($entry->answerText())->toBe('count_invoices({"archive":"main"})');
});

it('still states an empty message when the upstream answered with nothing at all', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markCompleted('', []);

    expect($entry->output)->toHaveCount(1)
        ->and($entry->output[0]['type'])->toBe('message')
        ->and($entry->answerText())->toBe('');
});

it('tolerates completions without usage', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markCompleted('hi', []);

    expect($entry->usage)->toBe([
        'input_tokens' => null,
        'output_tokens' => null,
        'total_tokens' => null,
    ]);
});

it('marks itself failed and keeps the full error message', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markFailed(new RuntimeException(str_repeat('x', 2000)));

    expect($entry->status)->toBe(BacklogStatus::Failed)
        ->and($entry->error['type'])->toBe('exception')
        ->and($entry->error['message'])->toStartWith('RuntimeException: ')
        ->and($entry->error['message'])->toHaveLength(2000 + strlen('RuntimeException: '))
        ->and($entry->attempts)->toBe(1)
        ->and($entry->finished_at)->not->toBeNull();
});

it('stores the upstream answer verbatim when the backend rejects a request', function () {
    Http::fake(['*/v1/chat/completions' => Http::response([
        'error' => ['message' => 'Invalid model name passed in model=nope:1b', 'code' => '400'],
    ], 400)]);

    $entry = BacklogEntry::factory()->running()->create();

    $entry->markFailed(new RequestException(Http::post('http://upstream.test/v1/chat/completions')));

    expect($entry->error['type'])->toBe('upstream')
        ->and($entry->error['status'])->toBe(400)
        ->and($entry->error['body']['error']['message'])->toBe('Invalid model name passed in model=nope:1b');
});

it('renders message and upstream body for display', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'nope']], 400)]);

    $entry = BacklogEntry::factory()->running()->create();
    $entry->markFailed(new RequestException(Http::get('http://upstream.test')));

    expect($entry->errorDetails())
        ->toContain('Upstream responded with HTTP 400')
        ->toContain('"message": "nope"');
});

it('renders a non json upstream body as raw text', function () {
    Http::fake(['*' => Http::response('<html>502 Bad Gateway</html>', 502)]);

    $entry = BacklogEntry::factory()->running()->create();
    $entry->markFailed(new RequestException(Http::get('http://upstream.test')));

    expect($entry->errorDetails())
        ->toContain('Upstream responded with HTTP 502')
        ->toContain('<html>502 Bad Gateway</html>');
});

it('renders only the message when there is no upstream body', function () {
    $entry = BacklogEntry::factory()->running()->create();
    $entry->markFailed(new RuntimeException('boom'));

    expect($entry->errorDetails())->toBe('RuntimeException: boom');
});

it('requeues itself and clears the start time', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->requeue();

    expect($entry->status)->toBe(BacklogStatus::Queued)
        ->and($entry->started_at)->toBeNull()
        ->and($entry->attempts)->toBe(1);
});

it('cancels itself while it is still queued', function () {
    $entry = BacklogEntry::factory()->create();

    expect($entry->cancelIfQueued())->toBeTrue()
        ->and($entry->status)->toBe(BacklogStatus::Cancelled)
        ->and($entry->finished_at)->not->toBeNull();
});

it('refuses to cancel an entry a worker has already claimed', function () {
    $entry = BacklogEntry::factory()->create();

    $racing = BacklogEntry::find($entry->id);
    $entry->claim();

    expect($racing->cancelIfQueued())->toBeFalse()
        ->and($racing->refresh()->status)->toBe(BacklogStatus::Running);
});

it('lets only one of two racing workers claim the same entry', function () {
    $entry = BacklogEntry::factory()->create();

    $first = BacklogEntry::find($entry->id);
    $second = BacklogEntry::find($entry->id);

    expect($first->claim())->toBeTrue()
        ->and($second->claim())->toBeFalse()
        ->and($entry->refresh()->attempts)->toBe(1);
});

it('refuses to claim an entry that is already running', function () {
    expect(BacklogEntry::factory()->running()->create()->claim())->toBeFalse();
});

it('refuses to claim a finished entry', function (string $state) {
    expect(BacklogEntry::factory()->{$state}()->create()->claim())->toBeFalse();
})->with(['completed', 'failed', 'cancelled']);

it('can be claimed again once it has been requeued', function () {
    $entry = BacklogEntry::factory()->create();

    $entry->claim();
    $entry->requeue();

    expect($entry->claim())->toBeTrue()
        ->and($entry->attempts)->toBe(2);
});

it('counts what each caller has queued', function () {
    BacklogEntry::factory()->count(3)->create(['api_key_hash' => hash('sha256', 'sk-one')]);
    BacklogEntry::factory()->create(['api_key_hash' => hash('sha256', 'sk-two')]);

    $activity = BacklogEntry::callerActivity()->keyBy('api_key_hash');

    expect($activity)->toHaveCount(2)
        ->and($activity[hash('sha256', 'sk-one')]['entries'])->toBe(3)
        ->and($activity[hash('sha256', 'sk-two')]['entries'])->toBe(1);
});

it('groups entries queued without a key into one keyless caller', function () {
    BacklogEntry::factory()->count(2)->create(['api_key_hash' => null]);
    BacklogEntry::factory()->create();

    $keyless = BacklogEntry::callerActivity()->firstWhere('api_key_hash', null);

    expect(BacklogEntry::callerActivity())->toHaveCount(2)
        ->and($keyless['entries'])->toBe(2);
});

it('leaves deleted entries out of a caller count', function () {
    $hash = hash('sha256', 'sk-one');
    BacklogEntry::factory()->count(2)->create(['api_key_hash' => $hash]);
    BacklogEntry::factory()->create(['api_key_hash' => $hash])->delete();

    expect(BacklogEntry::callerActivity()->firstWhere('api_key_hash', $hash)['entries'])->toBe(2);
});

it('drops a caller whose every entry has been deleted', function () {
    BacklogEntry::factory()->create(['api_key_hash' => hash('sha256', 'sk-gone')])->delete();

    expect(BacklogEntry::callerActivity())->toBeEmpty();
});

it('reports when a caller was first and last seen', function () {
    $hash = hash('sha256', 'sk-one');
    $first = CarbonImmutable::parse('2026-01-01 08:00:00');

    BacklogEntry::factory()->create(['api_key_hash' => $hash, 'created_at' => $first]);
    BacklogEntry::factory()->create(['api_key_hash' => $hash, 'created_at' => $first->addHours(5)]);
    BacklogEntry::factory()->create(['api_key_hash' => $hash, 'created_at' => $first->addHour()]);

    $caller = BacklogEntry::callerActivity()->firstWhere('api_key_hash', $hash);

    expect($caller['first_seen'])->toBeInstanceOf(CarbonImmutable::class)
        ->and($caller['first_seen']->toDateTimeString())->toBe('2026-01-01 08:00:00')
        ->and($caller['last_seen']->toDateTimeString())->toBe('2026-01-01 13:00:00')
        ->and($caller['first_seen']->timezoneName)->toBe(config('app.timezone'));
});

it('reports one moment for a caller with a single entry', function () {
    $entry = BacklogEntry::factory()->create(['created_at' => CarbonImmutable::parse('2026-02-03 10:11:12')]);

    $caller = BacklogEntry::callerActivity()->firstWhere('api_key_hash', $entry->api_key_hash);

    expect($caller['first_seen']->toDateTimeString())->toBe('2026-02-03 10:11:12')
        ->and($caller['last_seen']->toDateTimeString())->toBe('2026-02-03 10:11:12');
});

it('keeps several tool calls in the order the model asked for them', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markCompleted('', ['choices' => [['message' => ['tool_calls' => [
        ['id' => 'call_1', 'function' => ['name' => 'count_invoices', 'arguments' => '{}']],
        ['id' => 'call_2', 'function' => ['name' => 'list_archives', 'arguments' => '{}']],
    ]]]]]);

    expect($entry->output)->toHaveCount(2)
        ->and($entry->output[0]['call_id'])->toBe('call_1')
        ->and($entry->output[1]['call_id'])->toBe('call_2')
        ->and($entry->output[0]['id'])->not->toBe($entry->output[1]['id']);
});

/*
 | A call nothing can dispatch is dropped on the way back, so what is left is
 | an entry that answered with text alone — and it has to still say so.
 */
it('falls back to the message when every call was unusable', function () {
    $entry = BacklogEntry::factory()->running()->create();

    $entry->markCompleted('hier ist die Antwort', ['choices' => [['message' => [
        'content' => 'hier ist die Antwort',
        'tool_calls' => [['id' => 'call_1', 'function' => ['arguments' => '{}']]],
    ]]]]);

    expect($entry->output)->toHaveCount(1)
        ->and($entry->output[0]['type'])->toBe('message')
        ->and($entry->answerText())->toBe('hier ist die Antwort');
});

it('reports no answer text for an entry still in the queue', function () {
    expect(BacklogEntry::factory()->create()->answerText())->toBeNull();
});

it('finds the message text even when a call was listed first', function () {
    $entry = BacklogEntry::factory()->running()->create(['output' => [
        ['type' => 'function_call', 'name' => 'now', 'arguments' => '{}'],
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'nachher']]],
    ]]);

    expect($entry->answerText())->toBe('nachher');
});
