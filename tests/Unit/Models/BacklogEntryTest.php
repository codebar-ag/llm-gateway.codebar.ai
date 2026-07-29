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
