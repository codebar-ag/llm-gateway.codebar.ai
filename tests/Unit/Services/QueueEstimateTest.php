<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use App\Services\QueueEstimate;

/*
 | Pinned rather than taken from config/gateway.php: these tests assert exact
 | second counts, and a changed default should not silently rewrite what they
 | claim. Cache isolation is handled globally — see tests/Pest.php.
 */

beforeEach(function () {
    config()->set('gateway.backlog.workers', 1);
    config()->set('gateway.backlog.estimate.default_seconds', 30);
    config()->set('gateway.backlog.estimate.sample', 20);
});

function estimate(): QueueEstimate
{
    return app(QueueEstimate::class);
}

/** An entry that has already been answered, having taken exactly $seconds. */
function answeredIn(int $seconds, string $model): BacklogEntry
{
    return BacklogEntry::factory()->completed()->create([
        'model' => $model,
        'started_at' => now()->subSeconds($seconds),
        'finished_at' => now(),
    ]);
}

it('counts the entries in front of it', function () {
    $first = BacklogEntry::factory()->create(['created_at' => now()->subMinutes(3)]);
    BacklogEntry::factory()->create(['created_at' => now()->subMinutes(2)]);
    $third = BacklogEntry::factory()->create(['created_at' => now()->subMinute()]);

    expect(estimate()->for($first)->position)->toBe(1)
        ->and(estimate()->for($third)->position)->toBe(3);
});

it('breaks a shared timestamp by id rather than reporting one position twice', function () {
    $at = now()->subMinute();

    $earlier = BacklogEntry::factory()->create(['id' => 'resp_aaa', 'created_at' => $at]);
    $later = BacklogEntry::factory()->create(['id' => 'resp_bbb', 'created_at' => $at]);

    expect(estimate()->for($earlier)->position)->toBe(1)
        ->and(estimate()->for($later)->position)->toBe(2);
});

it('reports the depth of the whole backlog, not just what is ahead', function () {
    $mine = BacklogEntry::factory()->create(['created_at' => now()->subMinute()]);
    BacklogEntry::factory()->count(4)->create();

    expect(estimate()->for($mine))
        ->position->toBe(1)
        ->depth->toBe(5);
});

it('leaves entries a worker is already running out of the count in front', function () {
    BacklogEntry::factory()->running()->create();
    $mine = BacklogEntry::factory()->create();

    expect(estimate()->for($mine))
        ->position->toBe(1)
        ->depth->toBe(1);
});

it('still waits for a running entry, because it holds a worker slot', function () {
    BacklogEntry::factory()->running()->create();
    $mine = BacklogEntry::factory()->create();

    // One worker, one entry mid-flight: this one only starts once that ends,
    // so it is two default-length answers away rather than one.
    expect(estimate()->for($mine)->estimatedSeconds)->toBe(60);
});

it('divides the line by the number of workers', function () {
    config()->set('gateway.backlog.workers', 2);

    BacklogEntry::factory()->count(4)->create(['created_at' => now()->subMinute()]);
    $mine = BacklogEntry::factory()->create();

    // Fifth in line across two workers is the third round of answers.
    expect(estimate()->for($mine))
        ->position->toBe(5)
        ->workers->toBe(2)
        ->estimatedSeconds->toBe(90);
});

it('never divides by zero when no workers are configured', function () {
    config()->set('gateway.backlog.workers', 0);

    $mine = BacklogEntry::factory()->create();

    expect(estimate()->for($mine))
        ->workers->toBe(1)
        ->estimatedSeconds->toBe(30);
});

it('measures how long recent answers for the same model took', function () {
    answeredIn(10, 'measured');
    answeredIn(20, 'measured');
    answeredIn(60, 'measured');

    $mine = BacklogEntry::factory()->create(['model' => 'measured']);

    // The median, not the mean: the one slow answer would otherwise pull
    // every estimate behind it up to 30.
    expect(estimate()->for($mine)->estimatedSeconds)->toBe(20);
});

it('averages the middle two when the sample is even', function () {
    answeredIn(10, 'measured');
    answeredIn(20, 'measured');
    answeredIn(30, 'measured');
    answeredIn(60, 'measured');

    $mine = BacklogEntry::factory()->create(['model' => 'measured']);

    expect(estimate()->for($mine)->estimatedSeconds)->toBe(25);
});

it('borrows the global median for a model nothing has answered yet', function () {
    answeredIn(40, 'someone-elses-model');

    $mine = BacklogEntry::factory()->create(['model' => 'never-seen']);

    expect(estimate()->for($mine)->estimatedSeconds)->toBe(40);
});

it('falls back to the configured default when nothing has been answered at all', function () {
    $mine = BacklogEntry::factory()->create(['model' => 'never-seen']);

    expect(estimate()->for($mine)->estimatedSeconds)->toBe(30);
});

it('ignores failures when measuring, since giving up is not answering', function () {
    BacklogEntry::factory()->failed()->create([
        'model' => 'measured',
        'started_at' => now()->subSeconds(600),
        'finished_at' => now(),
    ]);

    $mine = BacklogEntry::factory()->create(['model' => 'measured']);

    expect(estimate()->for($mine)->estimatedSeconds)->toBe(30);
});

it('projects a completion time from the wait', function () {
    $mine = BacklogEntry::factory()->create();

    $place = estimate()->for($mine);

    expect($place->estimatedCompletionAt)->toBe(now()->addSeconds(30)->getTimestamp());
});

it('pays for the queue-wide counts once per run of polls, not once per poll', function () {
    BacklogEntry::factory()->count(2)->create(['created_at' => now()->subMinute()]);
    $mine = BacklogEntry::factory()->create();

    // A cold poll pays for everything: position, running count, depth and the
    // two reads behind the measurement.
    $cold = queriesDuring(fn () => estimate()->for($mine));

    cache()->flush();

    $twice = queriesDuring(function () use ($mine) {
        estimate()->for($mine);
        estimate()->for($mine);
    });

    // The second poll re-reads only its own position; every global count the
    // first poll made is served back to it.
    expect($twice)->toBe($cold + 1)
        ->and($twice)->toBeLessThan($cold * 2);
});

it('recounts the queue-wide totals once the cache window has passed', function () {
    config()->set('gateway.backlog.estimate.ttl', 5);

    $mine = BacklogEntry::factory()->create(['created_at' => now()->subMinute()]);

    expect(estimate()->for($mine)->depth)->toBe(1);

    BacklogEntry::factory()->create();

    // Inside the window the second poll is handed the count the first paid
    // for, which is the whole point of caching it. Depth is how busy the
    // backlog is, not where this caller stands, so a few seconds behind is
    // still the number they read it for.
    expect(estimate()->for($mine))
        ->depth->toBe(1)
        ->position->toBe(1);

    $this->travel(6)->seconds();

    expect(estimate()->for($mine)->depth)->toBe(2);
});

it('never lets a cached running count freeze the place in line', function () {
    config()->set('gateway.backlog.estimate.ttl', 5);

    $ahead = BacklogEntry::factory()->create(['created_at' => now()->subMinute()]);
    $mine = BacklogEntry::factory()->create();

    expect(estimate()->for($mine)->position)->toBe(2);

    $ahead->markCompleted('done', []);

    // Same tick, so the global counts are still cached — the position is not,
    // and a caller who has moved up must be told so on their very next poll.
    expect(estimate()->for($mine)->position)->toBe(1);
});

it('reports no place for an entry that is no longer waiting', function (string $state) {
    $entry = BacklogEntry::factory()->{$state}()->create();

    expect(estimate()->for($entry))->toBeNull();
})->with(['running', 'completed', 'failed', 'cancelled']);
