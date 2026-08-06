<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/** One full listing page, mirroring ShowBacklogController::PER_PAGE. */
const LISTING_PAGE = 100;

/**
 * The headers `usePoll` puts on the wire for the backlog's 15-second refresh.
 *
 * Kept in step with the `only:` list in resources/js/Pages/Backlog.vue by hand:
 * these tests assert that the props NOT named there are skipped, so a prop
 * added to one side and not the other should fail here rather than quietly
 * start costing a query again.
 *
 * The version has to come from the middleware rather than `Inertia::getVersion()`.
 * The facade only learns it once a request has passed through, so a test whose
 * first request is the partial one would otherwise be answered with a 409.
 *
 * @return array<string, string>
 */
function autoRefreshHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) (new HandleInertiaRequests)->version(request()),
        'X-Inertia-Partial-Component' => 'Logs',
        'X-Inertia-Partial-Data' => 'entries,total,pagination',
    ];
}

it('renders the backlog page for an operator holding the dashboard token', function () {
    $this->get('/logs')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Logs'));
});

it('shows an empty state', function () {
    expect(backlogIds($this->get('/logs')->assertOk()))->toBeEmpty();
});

it('lists entries with model and status', function () {
    $entry = BacklogEntry::factory()->failed()->create(['model' => 'test-model']);

    $response = $this->get('/logs')->assertOk();

    expect(backlogIds($response))->toBe([$entry->id]);

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('entries.0.model', 'test-model')
        ->where('entries.0.status.label', 'failed')
        ->where('entries.0.status.tone', 'danger'));
});

it('links to the details page instead of inlining the error', function () {
    $entry = BacklogEntry::factory()->failed('upstream exploded')->create();

    $this->get('/logs')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.0.href', route('logs.entry', $entry->id))
            ->missing('entries.0.error'));
});

it('offers a details link for every status', function (string $state) {
    $entry = BacklogEntry::factory()->{$state}()->create();

    $this->get('/logs')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.0.href', route('logs.entry', $entry->id)));
})->with(['running', 'completed', 'failed', 'cancelled']);

it('searches by the start of an entry id', function () {
    $wanted = BacklogEntry::factory()->create(['id' => 'resp_findme123']);
    BacklogEntry::factory()->create(['id' => 'resp_other456']);

    expect(backlogIds($this->get('/logs?q=resp_findme')->assertOk()))->toBe([$wanted->id]);
});

it('does not match a fragment from the middle of an id', function () {
    BacklogEntry::factory()->create(['id' => 'resp_findme123']);

    expect(backlogIds($this->get('/logs?q=findme')->assertOk()))->toBeEmpty();
});

it('finds an entry by its full id', function () {
    $entry = BacklogEntry::factory()->create();

    expect(backlogIds($this->get('/logs?q='.$entry->id)->assertOk()))->toBe([$entry->id]);
});

it('filters by model', function () {
    $wanted = BacklogEntry::factory()->create(['model' => 'wanted-model']);
    BacklogEntry::factory()->create(['model' => 'other-model']);

    expect(backlogIds($this->get('/logs?model=wanted-model')->assertOk()))->toBe([$wanted->id]);
});

it('filters by status', function () {
    $completed = BacklogEntry::factory()->completed()->create();
    BacklogEntry::factory()->failed()->create();

    expect(backlogIds($this->get('/logs?status=completed')->assertOk()))->toBe([$completed->id]);
});

it('combines search and filters', function () {
    $match = BacklogEntry::factory()->failed()->create(['id' => 'resp_match1', 'model' => 'model-a']);
    BacklogEntry::factory()->failed()->create(['id' => 'resp_match2', 'model' => 'model-b']);
    BacklogEntry::factory()->completed()->create(['id' => 'resp_match3', 'model' => 'model-a']);

    expect(backlogIds($this->get('/logs?q=resp_match&model=model-a&status=failed')->assertOk()))
        ->toBe([$match->id]);
});

it('ignores unknown status filters', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    expect(backlogIds($this->get('/logs?status=nonsense')->assertOk()))->toBe([$entry->id]);
});

it('offers every used model in the filter dropdown', function () {
    BacklogEntry::factory()->create(['model' => 'alpha-model']);
    BacklogEntry::factory()->create(['model' => 'beta-model']);

    $models = inertiaProps($this->get('/logs')->assertOk())['models'];

    expect(array_column($models, 'value'))->toBe(['alpha-model', 'beta-model']);
});

it('paginates after one hundred entries', function () {
    BacklogEntry::factory()->count(101)->create();

    $response = $this->get('/logs')->assertOk();

    expect(backlogIds($response))->toHaveCount(100)
        ->and(inertiaProps($response)['total'])->toBe(101)
        ->and(inertiaProps($response)['pagination']['next'])->not->toBeNull();
});

it('walks the whole backlog across cursor pages without losing a row', function () {
    BacklogEntry::factory()->count(101)->create(['created_at' => now()]);

    $seen = [];
    $url = '/logs';

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

    $props = inertiaProps($this->get('/logs')->assertOk());

    expect($props['total'])->toBe(3)
        ->and($props['pagination']['next'])->toBeNull()
        ->and($props['pagination']['prev'])->toBeNull();
});

it('keeps filters while paging', function () {
    BacklogEntry::factory()->count(101)->failed()->create();

    $next = inertiaProps($this->get('/logs?status=failed')->assertOk())['pagination']['next'];

    expect($next)->toContain('status=failed');
});

it('counts only the filtered entries', function () {
    BacklogEntry::factory()->count(3)->failed()->create();
    BacklogEntry::factory()->count(5)->completed()->create();

    expect(inertiaProps($this->get('/logs?status=failed')->assertOk())['total'])->toBe(3);
});

it('offers every status in the filter, including ones nothing is in', function () {
    BacklogEntry::factory()->completed()->create();

    expect(array_column(inertiaProps($this->get('/logs')->assertOk())['statuses'], 'value'))
        ->toBe(['queued', 'in_progress', 'completed', 'failed', 'cancelled']);
});

/*
 | A vision call carries its image into `request` as base64, so the payload
 | columns are the difference between a page that fits in memory and a blank
 | 500. The listing renders none of them — it shows an id, a status, a model and
 | a time — so it must not read them either, however many rows are on the page.
 */
it('never reads an entry payload into memory to draw the listing', function () {
    $image = str_repeat('A', 200_000);

    BacklogEntry::factory()->completed()->create([
        'request' => ['model' => 'ocr-model', 'messages' => [['role' => 'user', 'content' => $image]]],
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get('/logs')->assertOk();

    $reads = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'from "backlog_entries"'));

    DB::disableQueryLog();

    $listing = $reads->filter(fn (string $query): bool => str_contains($query, 'order by'));

    /*
     | `select *` has to be rejected explicitly. Naming the payload columns and
     | checking they are absent is not enough — a star selects them without
     | mentioning them, which is exactly the query this test exists to catch,
     | and it passed that way while the page was still falling over.
     */
    expect($listing)->not->toBeEmpty()
        ->and($listing->filter(fn (string $query): bool => str_contains($query, 'select *')))->toBeEmpty()
        ->and($listing->filter(fn (string $query): bool => str_contains($query, '"request"')))->toBeEmpty()
        ->and($listing->filter(fn (string $query): bool => str_contains($query, '"output"')))->toBeEmpty()
        ->and($listing->filter(fn (string $query): bool => str_contains($query, '"headers"')))->toBeEmpty();
});

/*
 | The real thing, not a shape: a full page of entries carrying payloads the
 | size an OCR call actually queues. Hydrating those was a fatal — and a fatal
 | is a blank 500, which the browser shows as an empty error box with nothing in
 | it to read. This asserts the page renders *and* that it never paid for the
 | payloads, by measuring what the request actually allocated.
 */
it('renders a full page of entries carrying real OCR-sized payloads', function () {
    // ~200 KB each, the base64 of a modest scan. Twenty megabytes over a page.
    $image = str_repeat('QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVph', 5_800);

    $entries = BacklogEntry::factory()->count(LISTING_PAGE)->completed()->create([
        'model' => 'paddle-ocr-vl',
        'request' => ['model' => 'paddle-ocr-vl', 'messages' => [['role' => 'user', 'content' => $image]]],
    ]);

    $queued = $entries->count() * strlen($image);

    // Nothing of the fixture may still be held when the measurement starts, or
    // the baseline carries the very bytes the page is being trusted not to read.
    unset($entries, $image);
    gc_collect_cycles();

    memory_reset_peak_usage();
    $before = memory_get_usage();

    $response = $this->get('/logs')->assertOk();

    $spent = memory_get_peak_usage() - $before;

    expect(backlogIds($response))->toHaveCount(LISTING_PAGE)
        /*
         | Measured against the payloads, not against the PHP limit. The page
         | costs a few megabytes of its own whatever is in the table — a hundred
         | rows of props, encoded to JSON — and that cost is fixed. What must
         | never happen is it growing with the size of the images, so the bar is
         | half of what is sitting in those columns: reading them would cost all
         | of it and then the arrays `json_decode` builds on top.
         */
        ->and($spent)->toBeLessThan((int) ($queued / 2));
});

it('still shows everything a row is made of without them', function () {
    $gateway = Gateway::factory()->create(['name' => 'Mac Ollama']);

    $entry = BacklogEntry::factory()->embeddings()->create([
        'model' => 'nomic-embed-text',
        'gateway_id' => $gateway->id,
        'api_key_hash' => hash('sha256', 'sk-someone'),
    ]);

    $row = inertiaProps($this->get('/logs')->assertOk())['entries'][0];

    // Every column the narrowed select has to keep serving.
    expect($row['id'])->toBe($entry->id)
        ->and($row['status']['label'])->toBe('queued')
        ->and($row['kind'])->toBe('embeddings')
        ->and($row['model'])->toBe('nomic-embed-text')
        ->and($row['gateway'])->toBe('Mac Ollama')
        ->and($row['caller']['hash'])->toBe($entry->api_key_hash)
        ->and($row['created']['stamp'])->toBe($entry->created_at->format('d.m.Y H:i:s'));
});

it('leaves the live counters to the dashboard', function () {
    BacklogEntry::factory()->create();

    // The tiles used to sit above this listing and counted the whole backlog
    // rather than the filter, so they belong where the rest of the numbers are.
    $props = inertiaProps($this->get('/logs')->assertOk());

    expect($props)->not->toHaveKey('oldestQueued')
        ->and($props)->not->toHaveKey('summary');
});

it('reports whether any filter is set, which is what shows the reset link', function () {
    expect(inertiaProps($this->get('/logs')->assertOk())['filters']['areEmpty'])->toBeTrue()
        ->and(inertiaProps($this->get('/logs?status=failed')->assertOk())['filters']['areEmpty'])->toBeFalse();
});

it('shows the tunnel indicator', function (string $body, int $status, string $expected) {
    config(['gateway.tunnel.metrics_url' => 'http://127.0.0.1:20241']);
    Http::fake(['*' => Http::response($body, $status)]);

    expect(inertiaProps($this->get('/logs')->assertOk())['tunnel']['label'])->toBe($expected);
})->with([
    ['OK', 200, 'tunnel up'],
    ['', 503, 'tunnel down'],
]);

it('shows an unknown tunnel when none is configured', function () {
    config(['gateway.tunnel.metrics_url' => '']);

    expect(inertiaProps($this->get('/logs')->assertOk())['tunnel']['label'])->toBe('tunnel unknown');
});

it('does not rebuild the filter dropdowns on the auto-refresh reload', function () {
    BacklogEntry::factory()->count(5)->create();

    $props = $this->get('/logs', autoRefreshHeaders())->assertOk()->json('props');

    expect($props)->toHaveKeys(['entries', 'total', 'pagination'])
        ->and($props)->not->toHaveKey('statuses')
        ->and($props)->not->toHaveKey('kinds')
        ->and($props)->not->toHaveKey('models')
        ->and($props)->not->toHaveKey('gateways')
        ->and($props)->not->toHaveKey('callers');
});

/*
 | The whole point of the closures. The dropdowns are three unbounded aggregates
 | over a table that only grows — a DISTINCT scan, every gateway, and a GROUP BY
 | with min/max over every row — and the page asked for all three every fifteen
 | seconds only to have Inertia throw the answers away. This asserts on the gap
 | rather than on an exact count, so adding a query to either path is allowed
 | but collapsing the gap is not.
 */
it('runs fewer queries on the auto-refresh reload than on a full page load', function () {
    BacklogEntry::factory()->count(5)->create();

    // Warm-up: the first request of a test pays one-off costs that would land
    // on whichever of the two ran first.
    $this->get('/logs')->assertOk();

    $full = queriesDuring(fn () => $this->get('/logs')->assertOk());
    $partial = queriesDuring(fn () => $this->get('/logs', autoRefreshHeaders())->assertOk());

    expect($partial)->toBeLessThan($full);
});

it('still picks up a newly seen model on a full page load', function () {
    BacklogEntry::factory()->create(['model' => 'alpha-model']);

    $before = inertiaProps($this->get('/logs')->assertOk())['models'];

    BacklogEntry::factory()->create(['model' => 'beta-model']);

    $after = inertiaProps($this->get('/logs')->assertOk())['models'];

    expect(array_column($before, 'value'))->toBe(['alpha-model'])
        ->and(array_column($after, 'value'))->toBe(['alpha-model', 'beta-model']);
});

it('names a caller in the filter with the label the listing rows use', function () {
    $entry = BacklogEntry::factory()->create(['api_key_hash' => str_repeat('a', 64)]);

    ApiKeyLabel::factory()->create([
        'api_key_hash' => $entry->api_key_hash,
        'name' => 'billing-service',
    ]);

    $props = inertiaProps($this->get('/logs')->assertOk());

    expect(array_column($props['callers'], 'label'))->toContain('billing-service')
        ->and($props['entries'][0]['caller']['label'])->toBe('billing-service');
});

it('lists an embedding beside everything else', function () {
    $embedding = BacklogEntry::factory()->embeddings()->create();

    $props = inertiaProps($this->get('/logs')->assertOk());

    // The endpoint is named only where it is not the chat completion the rest
    // of the listing is, so an embedding row cannot be mistaken for one.
    expect($props['entries'][0]['id'])->toBe($embedding->id)
        ->and($props['entries'][0]['kind'])->toBe('embeddings')
        ->and($props['entries'][0]['model'])->toBe('nomic-embed-text');
});

it('dates a row by the clock as well as by its age', function () {
    $entry = BacklogEntry::factory()->create(['created_at' => now()->subMinutes(25)]);

    $created = inertiaProps($this->get('/logs')->assertOk())['entries'][0]['created'];

    // The stamp is the application timezone's wall clock, not UTC: an operator
    // reads this listing next to the upstream's own logs.
    expect($created['stamp'])->toBe($entry->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i:s'))
        ->and($created['exact'])->toBe($entry->created_at->toDateTimeString())
        ->and($created['short'])->toContain('25');
});

it('leaves the endpoint off a queued chat row', function () {
    BacklogEntry::factory()->create();

    expect(inertiaProps($this->get('/logs')->assertOk())['entries'][0]['kind'])->toBeNull();
});

it('filters by endpoint', function () {
    $embedding = BacklogEntry::factory()->embeddings()->create();
    BacklogEntry::factory()->count(2)->create();

    $props = inertiaProps($this->get('/logs?kind=embeddings')->assertOk());

    expect($props['entries'])->toHaveCount(1)
        ->and($props['entries'][0]['id'])->toBe($embedding->id)
        ->and($props['total'])->toBe(1);
});

it('offers every endpoint in the filter, including ones nothing has used yet', function () {
    expect(array_column(inertiaProps($this->get('/logs')->assertOk())['kinds'], 'value'))
        ->toBe(['chat', 'embeddings', 'moderations']);
});

it('reads an unknown endpoint as no filter rather than as an empty listing', function () {
    BacklogEntry::factory()->count(2)->create();

    $props = inertiaProps($this->get('/logs?kind=nonsense')->assertOk());

    expect($props['entries'])->toHaveCount(2)
        ->and($props['filters']['areEmpty'])->toBeTrue();
});
