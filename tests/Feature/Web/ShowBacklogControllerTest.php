<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

it('renders the backlog page for an operator holding the dashboard token', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Backlog'));
});

it('shows an empty state', function () {
    expect(backlogIds($this->get('/')->assertOk()))->toBeEmpty();
});

it('lists entries with model and status', function () {
    $entry = BacklogEntry::factory()->failed()->create(['model' => 'test-model']);

    $response = $this->get('/')->assertOk();

    expect(backlogIds($response))->toBe([$entry->id]);

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('entries.0.model', 'test-model')
        ->where('entries.0.status.label', 'failed')
        ->where('entries.0.status.tone', 'danger'));
});

it('links to the details page instead of inlining the error', function () {
    $entry = BacklogEntry::factory()->failed('upstream exploded')->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.0.href', route('backlog.entry', $entry->id))
            ->missing('entries.0.error'));
});

it('offers a details link for every status', function (string $state) {
    $entry = BacklogEntry::factory()->{$state}()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.0.href', route('backlog.entry', $entry->id)));
})->with(['running', 'completed', 'failed', 'cancelled']);

it('searches by the start of an entry id', function () {
    $wanted = BacklogEntry::factory()->create(['id' => 'resp_findme123']);
    BacklogEntry::factory()->create(['id' => 'resp_other456']);

    expect(backlogIds($this->get('/?q=resp_findme')->assertOk()))->toBe([$wanted->id]);
});

it('does not match a fragment from the middle of an id', function () {
    BacklogEntry::factory()->create(['id' => 'resp_findme123']);

    expect(backlogIds($this->get('/?q=findme')->assertOk()))->toBeEmpty();
});

it('finds an entry by its full id', function () {
    $entry = BacklogEntry::factory()->create();

    expect(backlogIds($this->get('/?q='.$entry->id)->assertOk()))->toBe([$entry->id]);
});

it('filters by model', function () {
    $wanted = BacklogEntry::factory()->create(['model' => 'wanted-model']);
    BacklogEntry::factory()->create(['model' => 'other-model']);

    expect(backlogIds($this->get('/?model=wanted-model')->assertOk()))->toBe([$wanted->id]);
});

it('filters by status', function () {
    $completed = BacklogEntry::factory()->completed()->create();
    BacklogEntry::factory()->failed()->create();

    expect(backlogIds($this->get('/?status=completed')->assertOk()))->toBe([$completed->id]);
});

it('combines search and filters', function () {
    $match = BacklogEntry::factory()->failed()->create(['id' => 'resp_match1', 'model' => 'model-a']);
    BacklogEntry::factory()->failed()->create(['id' => 'resp_match2', 'model' => 'model-b']);
    BacklogEntry::factory()->completed()->create(['id' => 'resp_match3', 'model' => 'model-a']);

    expect(backlogIds($this->get('/?q=resp_match&model=model-a&status=failed')->assertOk()))
        ->toBe([$match->id]);
});

it('ignores unknown status filters', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    expect(backlogIds($this->get('/?status=nonsense')->assertOk()))->toBe([$entry->id]);
});

it('offers every used model in the filter dropdown', function () {
    BacklogEntry::factory()->create(['model' => 'alpha-model']);
    BacklogEntry::factory()->create(['model' => 'beta-model']);

    $models = inertiaProps($this->get('/')->assertOk())['models'];

    expect(array_column($models, 'value'))->toBe(['alpha-model', 'beta-model']);
});

it('paginates after one hundred entries', function () {
    BacklogEntry::factory()->count(101)->create();

    $response = $this->get('/')->assertOk();

    expect(backlogIds($response))->toHaveCount(100)
        ->and(inertiaProps($response)['total'])->toBe(101)
        ->and(inertiaProps($response)['pagination']['next'])->not->toBeNull();
});

it('walks the whole backlog across cursor pages without losing a row', function () {
    BacklogEntry::factory()->count(101)->create(['created_at' => now()]);

    $seen = [];
    $url = '/';

    while ($url !== null) {
        $response = $this->get($url)->assertOk();

        $seen = [...$seen, ...backlogIds($response)];
        $url = inertiaProps($response)['pagination']['next'];
    }

    expect($seen)->toHaveCount(101)
        ->and(array_unique($seen))->toHaveCount(101);
});

it('does not paginate a short list', function () {
    BacklogEntry::factory()->count(3)->create();

    $props = inertiaProps($this->get('/')->assertOk());

    expect($props['total'])->toBe(3)
        ->and($props['pagination']['next'])->toBeNull()
        ->and($props['pagination']['prev'])->toBeNull();
});

it('keeps filters while paging', function () {
    BacklogEntry::factory()->count(101)->failed()->create();

    $next = inertiaProps($this->get('/?status=failed')->assertOk())['pagination']['next'];

    expect($next)->toContain('status=failed');
});

it('counts only the filtered entries', function () {
    BacklogEntry::factory()->count(3)->failed()->create();
    BacklogEntry::factory()->count(5)->completed()->create();

    expect(inertiaProps($this->get('/?status=failed')->assertOk())['total'])->toBe(3);
});

it('warns about the oldest queued entry', function () {
    BacklogEntry::factory()->create(['created_at' => now()->subMinutes(30)]);

    // `diffForHumans()` is localised (this app runs in German), so assert on
    // the number rather than on the wording around it.
    expect(inertiaProps($this->get('/')->assertOk())['oldestQueued'])->toContain('30');
});

it('shows no oldest-queued warning when nothing is queued', function () {
    BacklogEntry::factory()->completed()->create();

    expect(inertiaProps($this->get('/')->assertOk())['oldestQueued'])->toBeNull();
});

it('shows the correct count per status tile', function () {
    BacklogEntry::factory()->count(2)->failed()->create();
    BacklogEntry::factory()->count(3)->completed()->create();

    $counts = collect(inertiaProps($this->get('/')->assertOk())['statuses'])
        ->pluck('count', 'value');

    expect($counts['failed'])->toBe(2)
        ->and($counts['completed'])->toBe(3)
        ->and($counts['queued'])->toBe(0);
});

it('marks the active status tile and lets it toggle itself off', function () {
    $tiles = collect(inertiaProps($this->get('/?status=failed')->assertOk())['statuses'])
        ->keyBy('value');

    expect($tiles['failed']['active'])->toBeTrue()
        ->and($tiles['completed']['active'])->toBeFalse()
        // Toggling off drops the filter rather than setting it again.
        ->and($tiles['failed']['href'])->not->toContain('status=failed')
        ->and($tiles['completed']['href'])->toContain('status=completed');
});

it('reports whether any filter is set, which is what shows the reset link', function () {
    expect(inertiaProps($this->get('/')->assertOk())['filters']['areEmpty'])->toBeTrue()
        ->and(inertiaProps($this->get('/?status=failed')->assertOk())['filters']['areEmpty'])->toBeFalse();
});

it('shows the tunnel indicator', function (string $body, int $status, string $expected) {
    config(['gateway.tunnel.metrics_url' => 'http://127.0.0.1:20241']);
    Http::fake(['*' => Http::response($body, $status)]);

    expect(inertiaProps($this->get('/')->assertOk())['tunnel']['label'])->toBe($expected);
})->with([
    ['OK', 200, 'tunnel up'],
    ['', 503, 'tunnel down'],
]);

it('shows an unknown tunnel when none is configured', function () {
    config(['gateway.tunnel.metrics_url' => '']);

    expect(inertiaProps($this->get('/')->assertOk())['tunnel']['label'])->toBe('tunnel unknown');
});
