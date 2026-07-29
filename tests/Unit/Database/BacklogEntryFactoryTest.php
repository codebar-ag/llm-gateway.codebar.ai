<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;

it('creates a queued entry by default', function () {
    $entry = BacklogEntry::factory()->create();

    expect($entry->status)->toBe(BacklogStatus::Queued)
        ->and($entry->id)->toStartWith('resp_')
        ->and($entry->attempts)->toBe(0)
        ->and($entry->max_attempts)->toBe(2)
        ->and($entry->request['messages'])->toHaveCount(1)
        ->and($entry->started_at)->toBeNull()
        ->and($entry->finished_at)->toBeNull();
});

it('creates running entries', function () {
    $entry = BacklogEntry::factory()->running()->create();

    expect($entry->status)->toBe(BacklogStatus::Running)
        ->and($entry->attempts)->toBe(1)
        ->and($entry->started_at)->not->toBeNull();
});

it('creates stale entries older than the recovery window', function () {
    $entry = BacklogEntry::factory()->stale()->create();

    expect($entry->status)->toBe(BacklogStatus::Running)
        ->and($entry->started_at->isBefore(now()->subMinutes(30)))->toBeTrue();
});

it('creates completed entries with output text', function () {
    $entry = BacklogEntry::factory()->completed('custom answer')->create();

    expect($entry->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output[0]['content'][0]['text'])->toBe('custom answer')
        ->and($entry->usage['total_tokens'])->toBe(15)
        ->and($entry->finished_at)->not->toBeNull();
});

it('creates failed entries with an error', function () {
    $entry = BacklogEntry::factory()->failed('boom')->create();

    expect($entry->status)->toBe(BacklogStatus::Failed)
        ->and($entry->error['message'])->toBe('boom')
        ->and($entry->attempts)->toBe(2);
});

it('creates cancelled and exhausted entries', function () {
    expect(BacklogEntry::factory()->cancelled()->create()->status)->toBe(BacklogStatus::Cancelled)
        ->and(BacklogEntry::factory()->exhausted()->create()->canRetry())->toBeFalse();
});

it('creates many unique entries', function () {
    $entries = BacklogEntry::factory()->count(5)->create();

    expect($entries->pluck('id')->unique())->toHaveCount(5);
});
