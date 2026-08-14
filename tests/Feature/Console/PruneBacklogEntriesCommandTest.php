<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use Illuminate\Console\Scheduling\Schedule;

it('empties the payloads of entries finished longer ago than the window', function () {
    $entry = BacklogEntry::factory()->completed('the answer is 42')->create([
        'finished_at' => now()->subDays(8),
    ]);

    $before = $entry->fresh()->updated_at->toDateTimeString();

    // One substring, not one per claim: the whole report is a single console
    // write, and PendingCommand lets each write satisfy only one expectation.
    $this->artisan('backlog:prune')
        ->expectsOutputToContain('Pruned 1 entry finished before')
        ->assertSuccessful();

    $pruned = $entry->fresh();

    expect($pruned->request)->toBeNull()
        ->and($pruned->output)->toBeNull()
        ->and($pruned->pruned_at)->not->toBeNull()
        // The log itself survives: what it was, what it cost, how it ended.
        ->and($pruned->status->value)->toBe('completed')
        ->and($pruned->usage)->not->toBeNull()
        // Housekeeping is not part of the entry's own history.
        ->and($pruned->updated_at->toDateTimeString())->toBe($before);
});

it('leaves recently finished and still-running entries alone', function () {
    $fresh = BacklogEntry::factory()->completed()->create(['finished_at' => now()->subDays(6)]);
    // Old but never finished: its request is the call a worker has yet to
    // make, so age alone must not cost it the payload.
    $stuck = BacklogEntry::factory()->create(['created_at' => now()->subDays(30)]);

    $this->artisan('backlog:prune')
        ->expectsOutputToContain('Pruned 0 entries')
        ->assertSuccessful();

    expect($fresh->fresh()->request)->not->toBeNull()
        ->and($stuck->fresh()->request)->not->toBeNull();
});

it('prunes a soft deleted entry, which nobody will ever read again', function () {
    $entry = BacklogEntry::factory()->completed()->create(['finished_at' => now()->subDays(8)]);
    $entry->delete();

    $this->artisan('backlog:prune')->assertSuccessful();

    expect(BacklogEntry::withTrashed()->findOrFail($entry->id)->request)->toBeNull();
});

it('reports without writing under --pretend', function () {
    $entry = BacklogEntry::factory()->completed()->create(['finished_at' => now()->subDays(8)]);

    $this->artisan('backlog:prune', ['--pretend' => true])
        ->expectsOutputToContain('Would prune 1 entry')
        ->assertSuccessful();

    expect($entry->fresh()->request)->not->toBeNull()
        ->and($entry->fresh()->pruned_at)->toBeNull();
});

it('does nothing when pruning is disabled', function () {
    config()->set('gateway.backlog.prune_after_days', 0);

    $entry = BacklogEntry::factory()->completed()->create(['finished_at' => now()->subYear()]);

    $this->artisan('backlog:prune')
        ->expectsOutputToContain('Pruning is off')
        ->assertSuccessful();

    expect($entry->fresh()->request)->not->toBeNull();
});

it('is idempotent: a second run finds nothing left to prune', function () {
    BacklogEntry::factory()->completed()->create(['finished_at' => now()->subDays(8)]);

    $this->artisan('backlog:prune')->assertSuccessful();
    $this->artisan('backlog:prune')
        ->expectsOutputToContain('Pruned 0 entries')
        ->assertSuccessful();
});

it('is scheduled to run nightly', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->filter(fn ($event): bool => str_contains((string) $event->command, 'backlog:prune')))
        ->toHaveCount(1);
});

it('reports how much payload the run frees', function () {
    // A hand-sized payload, so the byte count in the report is exact:
    // {"k":"v"} is nine bytes, and there is no output to add to it.
    BacklogEntry::factory()->create([
        'status' => 'completed',
        'finished_at' => now()->subDays(8),
        'request' => ['k' => 'v'],
        'output' => null,
    ]);

    $this->artisan('backlog:prune')
        ->expectsOutputToContain('freeing 9 B of payload')
        ->assertSuccessful();
});
