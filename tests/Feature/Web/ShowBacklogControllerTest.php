<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use Illuminate\Support\Facades\Http;

it('renders for an operator holding the dashboard token', function () {
    $this->get('/')->assertOk()->assertSee('LLM Gateway');
});

it('shows an empty state', function () {
    $this->get('/')->assertOk()->assertSee('No entries match this filter.');
});

it('lists entries with model and status', function () {
    $entry = BacklogEntry::factory()->failed()->create(['model' => 'test-model']);

    $this->get('/')
        ->assertOk()
        ->assertSee($entry->id)
        ->assertSee('test-model')
        ->assertSee('failed');
});

it('links to the details page instead of inlining the error', function () {
    $entry = BacklogEntry::factory()->failed('upstream exploded')->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('Details')
        ->assertSee(route('backlog.entry', $entry->id), escape: false)
        ->assertDontSee('upstream exploded');
});

it('offers a details link for every status', function (string $state) {
    $entry = BacklogEntry::factory()->{$state}()->create();

    $this->get('/')->assertOk()->assertSee(route('backlog.entry', $entry->id), escape: false);
})->with(['running', 'completed', 'failed', 'cancelled']);

it('searches by the start of an entry id', function () {
    $wanted = BacklogEntry::factory()->create(['id' => 'resp_findme123']);
    $other = BacklogEntry::factory()->create(['id' => 'resp_other456']);

    $this->get('/?q=resp_findme')
        ->assertOk()
        ->assertSee($wanted->id)
        ->assertDontSee($other->id);
});

it('does not match a fragment from the middle of an id', function () {
    $entry = BacklogEntry::factory()->create(['id' => 'resp_findme123']);

    $this->get('/?q=findme')->assertOk()->assertDontSee($entry->id);
});

it('finds an entry by its full id', function () {
    $entry = BacklogEntry::factory()->create();

    $this->get('/?q='.$entry->id)->assertOk()->assertSee($entry->id);
});

it('filters by model', function () {
    $wanted = BacklogEntry::factory()->create(['model' => 'wanted-model']);
    $other = BacklogEntry::factory()->create(['model' => 'other-model']);

    $this->get('/?model=wanted-model')
        ->assertOk()
        ->assertSee($wanted->id)
        ->assertDontSee($other->id);
});

it('filters by status', function () {
    $completed = BacklogEntry::factory()->completed()->create();
    $failed = BacklogEntry::factory()->failed()->create();

    $this->get('/?status=completed')
        ->assertOk()
        ->assertSee($completed->id)
        ->assertDontSee($failed->id);
});

it('combines search and filters', function () {
    $match = BacklogEntry::factory()->failed()->create(['id' => 'resp_match1', 'model' => 'model-a']);
    BacklogEntry::factory()->failed()->create(['id' => 'resp_match2', 'model' => 'model-b']);
    BacklogEntry::factory()->completed()->create(['id' => 'resp_match3', 'model' => 'model-a']);

    $this->get('/?q=resp_match&model=model-a&status=failed')
        ->assertOk()
        ->assertSee($match->id)
        ->assertDontSee('resp_match2')
        ->assertDontSee('resp_match3');
});

it('ignores unknown status filters', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get('/?status=nonsense')->assertOk()->assertSee($entry->id);
});

it('offers every used model in the filter dropdown', function () {
    BacklogEntry::factory()->create(['model' => 'alpha-model']);
    BacklogEntry::factory()->create(['model' => 'beta-model']);

    $this->get('/')
        ->assertOk()
        ->assertSee('alpha-model')
        ->assertSee('beta-model')
        ->assertSee('All models');
});

it('paginates after one hundred entries', function () {
    BacklogEntry::factory()->count(101)->create();

    $response = $this->get('/')->assertOk();

    expect($response->viewData('entries')->count())->toBe(100)
        ->and($response->viewData('total'))->toBe(101);

    $response->assertSee('Showing 100 of 101 entries')->assertSee('Next');
});

it('walks the whole backlog across cursor pages without losing a row', function () {
    BacklogEntry::factory()->count(101)->create(['created_at' => now()]);

    $seen = [];
    $url = '/';

    while ($url !== null) {
        $page = $this->get($url)->assertOk()->viewData('entries');

        $seen = [...$seen, ...$page->pluck('id')->all()];
        $url = $page->nextCursor() ? '/?cursor='.$page->nextCursor()->encode() : null;
    }

    expect($seen)->toHaveCount(101)
        ->and(array_unique($seen))->toHaveCount(101);
});

it('does not paginate a short list', function () {
    BacklogEntry::factory()->count(3)->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('Showing 3 of 3 entries')
        ->assertDontSee('Next');
});

it('keeps filters while paging', function () {
    BacklogEntry::factory()->count(101)->failed()->create();

    $response = $this->get('/?status=failed')->assertOk();

    expect($response->viewData('entries')->nextPageUrl())->toContain('status=failed');
});

it('counts only the filtered entries', function () {
    BacklogEntry::factory()->count(3)->failed()->create();
    BacklogEntry::factory()->count(5)->completed()->create();

    expect($this->get('/?status=failed')->assertOk()->viewData('total'))->toBe(3);
});

it('warns about the oldest queued entry', function () {
    BacklogEntry::factory()->create(['created_at' => now()->subMinutes(30)]);

    $this->get('/')->assertOk()->assertSee('waiting since');
});

it('shows no oldest-queued warning when nothing is queued', function () {
    BacklogEntry::factory()->completed()->create();

    $this->get('/')->assertOk()->assertDontSee('waiting since');
});

it('shows the correct count per status badge', function () {
    BacklogEntry::factory()->count(2)->failed()->create();
    BacklogEntry::factory()->count(3)->completed()->create();

    $counts = $this->get('/')->assertOk()->viewData('counts');

    expect((int) $counts['failed'])->toBe(2)
        ->and((int) $counts['completed'])->toBe(3)
        ->and((int) ($counts['queued'] ?? 0))->toBe(0);
});

it('shows a reset link only when filtering', function () {
    $this->get('/')->assertOk()->assertDontSee('Reset');
    $this->get('/?status=failed')->assertOk()->assertSee('Reset');
});

it('shows the tunnel indicator', function (string $body, int $status, string $expected) {
    config(['gateway.tunnel.metrics_url' => 'http://127.0.0.1:20241']);
    Http::fake(['*' => Http::response($body, $status)]);

    $this->get('/')->assertOk()->assertSee($expected);
})->with([
    ['OK', 200, 'tunnel up'],
    ['', 503, 'tunnel down'],
]);

it('shows an unknown tunnel when none is configured', function () {
    config(['gateway.tunnel.metrics_url' => '']);

    $this->get('/')->assertOk()->assertSee('tunnel unknown');
});
