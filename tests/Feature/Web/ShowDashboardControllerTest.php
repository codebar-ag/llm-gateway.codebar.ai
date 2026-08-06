<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use Inertia\Testing\AssertableInertia;

/**
 * The headers the dashboard's own 15-second refresh puts on the wire.
 *
 * Kept in step with the `only:` list in resources/js/Pages/Dashboard.vue by
 * hand — the test below asserts the usage props are NOT resolved for it, so a
 * prop added to one side and not the other fails here rather than quietly
 * costing four GROUP BYs a minute.
 *
 * @return array<string, string>
 */
function dashboardRefreshHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) (new HandleInertiaRequests)->version(request()),
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'statuses,oldestQueued',
    ];
}

it('opens on the dashboard rather than on the logs', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
});

it('counts every status, whatever the range is', function () {
    BacklogEntry::factory()->count(2)->failed()->create();
    BacklogEntry::factory()->count(3)->completed()->create();
    // Older than the shortest window on the page: a request stuck since last
    // week is exactly what the tiles must not hide.
    BacklogEntry::factory()->create(['created_at' => now()->subDays(9)]);

    $counts = collect(inertiaProps($this->get('/?range=24h')->assertOk())['statuses'])
        ->pluck('count', 'value');

    expect($counts['failed'])->toBe(2)
        ->and($counts['completed'])->toBe(3)
        ->and($counts['queued'])->toBe(1);
});

it('sends a status tile into the logs already filtered', function () {
    $tiles = collect(inertiaProps($this->get('/')->assertOk())['statuses'])->keyBy('value');

    expect($tiles['failed']['href'])->toBe(route('logs', ['status' => 'failed']));
});

it('warns about the oldest queued entry', function () {
    BacklogEntry::factory()->create(['created_at' => now()->subMinutes(30)]);

    // `diffForHumans()` is localised, so assert on the number rather than on
    // the wording around it.
    expect(inertiaProps($this->get('/')->assertOk())['oldestQueued'])->toContain('30');
});

it('shows no oldest-queued warning when nothing is queued', function () {
    BacklogEntry::factory()->completed()->create();

    expect(inertiaProps($this->get('/')->assertOk())['oldestQueued'])->toBeNull();
});

it('adds up the tokens the upstream reported', function () {
    BacklogEntry::factory()->count(2)->completed()->create();

    $totals = inertiaProps($this->get('/')->assertOk())['totals'];

    // 12 in and 3 out per completed entry, from the factory.
    expect($totals['inputTokens'])->toBe(24)
        ->and($totals['outputTokens'])->toBe(6)
        ->and($totals['totalTokens'])->toBe(30);
});

it('counts an entry with no usage block as a request and as no tokens', function () {
    BacklogEntry::factory()->create();

    $totals = inertiaProps($this->get('/')->assertOk())['totals'];

    expect($totals['requests'])->toBe(1)
        ->and($totals['totalTokens'])->toBe(0);
});

it('rates failures against the entries that reached an upstream', function () {
    BacklogEntry::factory()->completed()->create();
    BacklogEntry::factory()->count(3)->failed()->create();
    // Still queued, so it has not succeeded or failed at anything yet and must
    // not flatter the rate.
    BacklogEntry::factory()->count(10)->create();

    expect(inertiaProps($this->get('/')->assertOk())['totals']['failureRate'])->toBe(75.0);
});

it('has no failure rate before anything has been attempted', function () {
    BacklogEntry::factory()->create();

    expect(inertiaProps($this->get('/')->assertOk())['totals']['failureRate'])->toBeNull();
});

it('measures how long answers took and how long they waited', function () {
    BacklogEntry::factory()->completed()->create([
        'created_at' => now()->subSeconds(30),
        'started_at' => now()->subSeconds(20),
        'finished_at' => now(),
    ]);

    $totals = inertiaProps($this->get('/')->assertOk())['totals'];

    expect($totals['avgDuration'])->toBe(20)
        ->and($totals['maxDuration'])->toBe(20)
        ->and($totals['avgWait'])->toBe(10);
});

it('leaves the timings empty rather than zero when nothing has been answered', function () {
    BacklogEntry::factory()->create();

    $totals = inertiaProps($this->get('/')->assertOk())['totals'];

    expect($totals['avgDuration'])->toBeNull()
        ->and($totals['maxDuration'])->toBeNull()
        ->and($totals['avgWait'])->toBeNull();
});

it('measures only the chosen window', function () {
    BacklogEntry::factory()->completed()->create();
    BacklogEntry::factory()->completed()->create(['created_at' => now()->subDays(3)]);

    $day = inertiaProps($this->get('/?range=24h')->assertOk())['totals'];
    $week = inertiaProps($this->get('/?range=7d')->assertOk())['totals'];

    expect($day['requests'])->toBe(1)
        ->and($week['requests'])->toBe(2);
});

it('falls back to the week for a range it does not know', function () {
    $props = inertiaProps($this->get('/?range=nonsense')->assertOk());

    expect($props['range']['value'])->toBe('7d');
});

it('breaks the window down per model, busiest first', function () {
    BacklogEntry::factory()->count(3)->completed()->create(['model' => 'busy-model']);
    BacklogEntry::factory()->completed()->create(['model' => 'quiet-model']);

    $perModel = inertiaProps($this->get('/')->assertOk())['perModel'];

    expect(array_column($perModel, 'model'))->toBe(['busy-model', 'quiet-model'])
        ->and($perModel[0]['requests'])->toBe(3)
        ->and($perModel[0]['totalTokens'])->toBe(45)
        ->and($perModel[0]['avgDuration'])->toBe(5);
});

it('breaks the window down per caller, naming the ones that have a label', function () {
    ApiKeyLabel::factory()->forKey('sk-ops')->create(['name' => 'Ops Team']);

    BacklogEntry::factory()->count(2)->completed()->create([
        'api_key_hash' => hash('sha256', 'sk-ops'),
    ]);
    BacklogEntry::factory()->completed()->create(['api_key_hash' => null]);

    $perCaller = collect(inertiaProps($this->get('/')->assertOk())['perCaller'])->keyBy('caller');

    expect($perCaller['Ops Team']['requests'])->toBe(2)
        ->and($perCaller['Ops Team']['named'])->toBeTrue()
        ->and($perCaller['Ops Team']['totalTokens'])->toBe(30)
        ->and($perCaller['anonymous']['requests'])->toBe(1)
        ->and($perCaller['anonymous']['named'])->toBeFalse();
});

it('buckets the volume by day, oldest first', function () {
    BacklogEntry::factory()->count(2)->create();
    BacklogEntry::factory()->create(['created_at' => now()->subDays(2)]);

    $volume = inertiaProps($this->get('/?range=7d')->assertOk())['volume'];

    expect($volume)->toHaveCount(2)
        ->and($volume[0]['bucket'])->toBe(now()->subDays(2)->format('Y-m-d'))
        ->and($volume[0]['requests'])->toBe(1)
        ->and($volume[1]['bucket'])->toBe(now()->format('Y-m-d'))
        ->and($volume[1]['requests'])->toBe(2);
});

it('buckets the shortest window by hour instead', function () {
    BacklogEntry::factory()->create();

    $volume = inertiaProps($this->get('/?range=24h')->assertOk())['volume'];

    expect($volume[0]['bucket'])->toBe(now()->format('Y-m-d H:00'));
});

it('leaves a soft deleted entry out of every number', function () {
    BacklogEntry::factory()->completed()->create()->delete();

    $totals = inertiaProps($this->get('/')->assertOk())['totals'];

    expect($totals['requests'])->toBe(0)
        ->and($totals['totalTokens'])->toBe(0);
});

it('does not run the usage aggregates on the auto-refresh reload', function () {
    BacklogEntry::factory()->count(5)->completed()->create();

    $props = $this->get('/', dashboardRefreshHeaders())->assertOk()->json('props');

    expect($props)->toHaveKeys(['statuses', 'oldestQueued'])
        ->and($props)->not->toHaveKey('totals')
        ->and($props)->not->toHaveKey('perModel')
        ->and($props)->not->toHaveKey('perCaller')
        ->and($props)->not->toHaveKey('volume');
});
