<?php

declare(strict_types=1);

use App\Console\Commands\RealignTimestampsCommand;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Support\Facades\DB;

/*
 | The zone the timestamps are pretended to have been misread as. Two hours
 | ahead of Europe/Zurich in summer and three in winter, which is the point:
 | the correction has to come out of the zones rather than out of a fixed
 | offset, or rows either side of a daylight saving change land differently.
 */
const MISREAD_AS = 'Europe/Moscow';

it('moves a timestamp back onto the instant the application meant', function () {
    $wallClock = now()->subHours(3)->startOfSecond();

    $entry = BacklogEntry::factory()->create(['created_at' => $wallClock]);

    $this->artisan('db:realign-timestamps', ['--from' => MISREAD_AS])->assertSuccessful();

    /*
     | Read the stored instant in the zone it was misread as to recover the wall
     | clock the application wrote, then take that wall clock as the application
     | timezone. `setTimezone` moves the instant to a new zone; `shiftTimezone`
     | keeps the digits and relabels them, which is the half Postgres does when
     | the repaired value is written back bare.
     */
    $expected = $wallClock->copy()->setTimezone(MISREAD_AS)->shiftTimezone(config('app.timezone'));

    expect($entry->fresh()->created_at->toDateTimeString())->toBe($expected->toDateTimeString());
});

it('realigns every timestamp column, not just created_at', function () {
    $entry = BacklogEntry::factory()->completed()->create([
        'created_at' => now()->subMinutes(30),
        'started_at' => now()->subMinutes(20),
        'finished_at' => now()->subMinutes(10),
    ]);

    $before = $entry->fresh();

    $this->artisan('db:realign-timestamps', ['--from' => MISREAD_AS])->assertSuccessful();

    $after = $entry->fresh();

    expect($after->started_at->toDateTimeString())->not->toBe($before->started_at->toDateTimeString())
        ->and($after->finished_at->toDateTimeString())->not->toBe($before->finished_at->toDateTimeString())
        // The gaps the entry page measures a wait and a duration from survive.
        ->and($after->created_at->diffInSeconds($after->started_at))->toBe($before->created_at->diffInSeconds($before->started_at))
        ->and($after->started_at->diffInSeconds($after->finished_at))->toBe($before->started_at->diffInSeconds($before->finished_at));
});

it('corrects soft deleted rows too', function () {
    $gateway = Gateway::factory()->create();
    $gateway->delete();

    $before = Gateway::withTrashed()->find($gateway->id)->deleted_at;

    $this->artisan('db:realign-timestamps', ['--from' => MISREAD_AS])->assertSuccessful();

    expect(Gateway::withTrashed()->find($gateway->id)->deleted_at->toDateTimeString())
        ->not->toBe($before->toDateTimeString());
});

it('does not stamp the repair over the value being repaired', function () {
    $entry = BacklogEntry::factory()->create(['updated_at' => now()->subDays(2)]);

    $before = $entry->fresh()->updated_at;

    $this->artisan('db:realign-timestamps', ['--from' => MISREAD_AS])->assertSuccessful();

    // Shifted like everything else, not replaced with the time of the run.
    expect($entry->fresh()->updated_at->diffInHours(now()))->toBeGreaterThan(24);
});

it('writes nothing when pretending', function () {
    $entry = BacklogEntry::factory()->create();

    $before = $entry->fresh()->created_at->toDateTimeString();

    $this->artisan('db:realign-timestamps', ['--from' => MISREAD_AS, '--pretend' => true])->assertSuccessful();

    expect($entry->fresh()->created_at->toDateTimeString())->toBe($before);
});

it('leaves the rows alone when they were written against the application timezone', function () {
    $entry = BacklogEntry::factory()->create();

    $before = $entry->fresh()->created_at->toDateTimeString();

    $this->artisan('db:realign-timestamps', ['--from' => config('app.timezone')])
        ->expectsOutputToContain('Nothing to realign')
        ->assertSuccessful();

    expect($entry->fresh()->created_at->toDateTimeString())->toBe($before);
});

it('refuses a zone it does not recognise', function () {
    $entry = BacklogEntry::factory()->create();

    $before = $entry->fresh()->created_at->toDateTimeString();

    $this->artisan('db:realign-timestamps', ['--from' => 'Europe/Nowhere'])->assertFailed();

    expect($entry->fresh()->created_at->toDateTimeString())->toBe($before);
});

it('asks for the zone when it cannot read one off the server', function () {
    // The test suite runs on sqlite, which has no server timezone to read.
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    $this->artisan('db:realign-timestamps')->assertFailed();
});

it('leaves a row alone when none of its timestamps are set', function () {
    // `users.created_at` is nullable, and a row whose every listed column is
    // null must be skipped rather than counted as shifted.
    DB::table('users')->insert([
        'name' => 'Ghost',
        'email' => 'ghost@example.com',
        'password' => 'x',
        'created_at' => null,
        'updated_at' => null,
    ]);

    $this->artisan('db:realign-timestamps', ['--from' => MISREAD_AS])->assertSuccessful();

    expect(DB::table('users')->where('email', 'ghost@example.com')->value('created_at'))->toBeNull();
});

it('reads the server timezone off postgres', function () {
    // The suite runs on sqlite, which the command answers with null before
    // ever querying pg_settings — so the Postgres read is pinned and called
    // directly, the same way the sqlite refusal is asserted above.
    DB::shouldReceive('connection->getDriverName')->andReturn('pgsql');
    DB::shouldReceive('selectOne')->andReturn((object) ['reset_val' => 'Europe/Zurich'], null);

    $serverTimezone = new ReflectionMethod(RealignTimestampsCommand::class, 'serverTimezone');
    $command = app(RealignTimestampsCommand::class);

    expect($serverTimezone->invoke($command))->toBe('Europe/Zurich')
        // A server with no readable zone answers null rather than throwing.
        ->and($serverTimezone->invoke($command))->toBeNull();
});
