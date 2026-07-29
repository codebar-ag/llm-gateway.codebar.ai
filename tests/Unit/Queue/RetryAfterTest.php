<?php

declare(strict_types=1);

use App\Http\Requests\GatewayRequest;
use App\Jobs\ProcessBacklogEntry;

/*
 | The queue hands a reserved job to another worker once `retry_after` has
 | passed, whether or not the first worker is still busy with it. If that
 | window is shorter than the job's own timeout, every LLM call slower than
 | the window gets answered twice. This is the only thing standing between
 | the gateway and duplicate upstream calls, so it is asserted rather than
 | left to a comment.
 */

function retryAfter(): int
{
    return (int) config('queue.connections.database.retry_after');
}

function jobTimeout(): int
{
    return (new ProcessBacklogEntry('resp_x'))->timeout();
}

it('waits longer than a job can run before reclaiming it', function () {
    expect(retryAfter())->toBeGreaterThan(jobTimeout());
});

it('keeps the margin when the upstream timeout is raised', function (int $seconds) {
    config()->set('gateway.upstream.max_timeout', $seconds);

    expect(intval($seconds) + 120)->toBeGreaterThan(jobTimeout());
})->with([60, 900, 3600]);

/*
 | Per-gateway timeouts are what make the ceiling load-bearing: retry_after is
 | one value for the whole queue connection, so a gateway allowed past the
 | ceiling would put the margin above back below zero.
 */
it('refuses a gateway slower than the ceiling', function () {
    $rules = (new GatewayRequest)->rules();

    expect($rules['timeout'])->toContain('max:'.config('gateway.upstream.max_timeout'));
});

it('leaves the rest of the database connection intact', function () {
    expect(config('queue.connections.database.driver'))->toBe('database')
        ->and(config('queue.connections.database.table'))->toBe('jobs');
});

it('does not disturb the other connections the framework ships', function () {
    expect(array_keys(config('queue.connections')))
        ->toContain('sync')
        ->toContain('database')
        ->toContain('redis');
});
