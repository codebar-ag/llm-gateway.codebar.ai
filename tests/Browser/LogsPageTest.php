<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use App\Models\Gateway;

/*
 | Signing in lands on the dashboard, so every test here navigates to the
 | listing first. Status is one of the five dropdowns on this page now; the
 | tiles that used to sit above the table are on the dashboard.
 */

it('renders the logs page with its search box and filter row', function () {
    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertSee('Logs')
        ->assertSee('Entry ID')
        ->assertSee('Status')
        ->assertSee('Model')
        ->assertSee('Gateway')
        ->assertSee('Caller')
        ->assertAttribute('input[name="q"]', 'type', 'search')
        ->assertAttribute('input[name="q"]', 'placeholder', 'resp_…')
        ->assertPresent('select[name="status"]');
});

it('filters entries by typing into the entry id search box', function () {
    $wanted = BacklogEntry::factory()->create(['id' => 'resp_findme0001']);
    BacklogEntry::factory()->create(['id' => 'resp_other0002']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->fill('q', 'resp_findme')
        ->click('button[type="submit"]')
        ->assertQueryStringHas('q', 'resp_findme')
        ->assertSee('#'.$wanted->id)
        ->assertDontSee('#resp_other0002');
});

it('filters entries by selecting a model from the dropdown', function () {
    $wanted = BacklogEntry::factory()->create(['id' => 'resp_modelwanted', 'model' => 'wanted-model']);
    BacklogEntry::factory()->create(['id' => 'resp_modelother', 'model' => 'other-model']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->select('model', 'wanted-model')
        ->click('button[type="submit"]')
        ->assertQueryStringHas('model', 'wanted-model')
        ->assertSee('#'.$wanted->id)
        ->assertDontSee('#resp_modelother');
});

it('filters entries by selecting a gateway from the dropdown', function () {
    $gatewayA = Gateway::factory()->create(['name' => 'Gateway Alpha']);
    $gatewayB = Gateway::factory()->create(['name' => 'Gateway Beta']);

    $wanted = BacklogEntry::factory()->create(['id' => 'resp_gwwanted', 'gateway_id' => $gatewayA->id]);
    BacklogEntry::factory()->create(['id' => 'resp_gwother', 'gateway_id' => $gatewayB->id]);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->select('gateway', $gatewayA->id)
        ->click('button[type="submit"]')
        ->assertQueryStringHas('gateway', $gatewayA->id)
        ->assertSee('#'.$wanted->id)
        ->assertDontSee('#resp_gwother');
});

it('filters entries by selecting a named caller from the dropdown', function () {
    ApiKeyLabel::factory()->forKey('sk-caller-two')->create(['name' => 'Ops Team']);

    $wanted = BacklogEntry::factory()->create([
        'id' => 'resp_callerwanted',
        'api_key_hash' => hash('sha256', 'sk-caller-two'),
    ]);
    BacklogEntry::factory()->create(['id' => 'resp_callerother']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->select('caller', hash('sha256', 'sk-caller-two'))
        ->click('button[type="submit"]')
        ->assertQueryStringHas('caller', hash('sha256', 'sk-caller-two'))
        ->assertSee('#'.$wanted->id)
        ->assertDontSee('#resp_callerother')
        ->assertSeeIn('tbody tr td:nth-child(4)', 'Ops Team');
});

it('filters by the anonymous caller sentinel and shows the no-bearer-token tooltip', function () {
    $anonymous = BacklogEntry::factory()->create(['id' => 'resp_anon0001', 'api_key_hash' => null]);
    BacklogEntry::factory()->create(['id' => 'resp_named0002']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->select('caller', 'anonymous')
        ->click('button[type="submit"]')
        ->assertQueryStringHas('caller', 'anonymous')
        ->assertSee('#'.$anonymous->id)
        ->assertDontSee('#resp_named0002')
        ->assertSeeIn('tbody tr td:nth-child(4)', 'anonymous')
        ->assertAttributeContains('tbody tr td:nth-child(4) span', 'title', 'queued without a bearer token');
});

it('treats a literal percent sign as a character typed into the search box, not a wildcard', function () {
    BacklogEntry::factory()->create(['id' => 'resp_real0001']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->fill('q', '%')
        ->click('button[type="submit"]')
        ->assertValue('q', '%')
        ->assertSee('No entries')
        ->assertSee('No entries match this filter.');
});

it('filters entries by selecting a status from the dropdown', function () {
    BacklogEntry::factory()->completed()->create(['id' => 'resp_completedone']);
    BacklogEntry::factory()->failed()->create(['id' => 'resp_failedone']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->select('status', 'completed')
        ->click('button[type="submit"]')
        ->assertQueryStringHas('status', 'completed')
        ->assertSee('#resp_completedone')
        ->assertDontSee('#resp_failedone');
});

it('keeps a status arrived at by URL selected in the dropdown', function () {
    BacklogEntry::factory()->failed()->create();

    [$page] = signIn();

    // How a dashboard tile arrives here — the dropdown has to agree with the
    // listing it is looking at, or the next search silently drops the filter.
    $page->navigate(route('logs', ['status' => 'failed']))
        ->assertValue('status', 'failed');
});

it('only shows the reset link once a filter is active, and clears it on click', function () {
    BacklogEntry::factory()->failed()->create();

    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertDontSee('Reset');

    $page->navigate(route('logs', ['status' => 'failed']))
        ->assertSee('Reset')
        ->click('a:has-text("Reset")')
        ->assertQueryStringMissing('status')
        ->assertPathIs('/logs')
        ->assertDontSee('Reset');
});

it('shows the empty state when a filter matches nothing', function () {
    BacklogEntry::factory()->create(['id' => 'resp_exists0001']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->fill('q', 'resp_doesnotexist')
        ->click('button[type="submit"]')
        ->assertSee('No entries')
        ->assertSee('No entries match this filter.');
});

it('paginates with previous/next buttons that only appear when needed', function () {
    BacklogEntry::factory()->count(101)->create(['created_at' => now()]);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertSee('Showing 100 of 101 entries')
        ->assertMissing('a:has-text("Previous")')
        ->assertPresent('a:has-text("Next")')
        ->click('a:has-text("Next")')
        ->assertQueryStringHas('cursor')
        ->assertSee('Showing 1 of 101 entries')
        ->assertPresent('a:has-text("Previous")')
        ->assertMissing('a:has-text("Next")')
        ->click('a:has-text("Previous")')
        ->assertSee('Showing 100 of 101 entries')
        ->assertMissing('a:has-text("Previous")')
        ->assertPresent('a:has-text("Next")');
});

it('drops the pagination cursor and returns to page one when a filter changes mid-pagination', function () {
    // Newer, so it fills page one; the lone "special" entry is older and
    // only reachable by paging, unless the filter change below correctly
    // resets to page one instead of reusing a cursor from the unfiltered set.
    BacklogEntry::factory()->count(100)->create(['model' => 'noise-model', 'created_at' => now()]);
    $special = BacklogEntry::factory()->create([
        'id' => 'resp_special0001',
        'model' => 'special-model',
        'created_at' => now()->subMinutes(10),
    ]);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->click('a:has-text("Next")')
        ->assertQueryStringHas('cursor')
        ->assertSee('#'.$special->id)
        ->select('model', 'special-model')
        ->click('button[type="submit"]')
        ->assertQueryStringMissing('cursor')
        ->assertQueryStringHas('model', 'special-model')
        ->assertSee('Showing 1 of 1 entry')
        ->assertSee('#'.$special->id)
        ->assertMissing('a:has-text("Next")')
        ->assertMissing('a:has-text("Previous")');
});

it('prefixes every entry id in the table with a literal hash', function () {
    $entry = BacklogEntry::factory()->create(['id' => 'resp_hashcheck0001']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertSee('#'.$entry->id)
        ->assertSeeIn('tbody tr td:nth-child(1)', '#'.$entry->id);
});

it('shows the clock time an entry was created, with the exact stamp as a tooltip', function () {
    $entry = BacklogEntry::factory()->create([
        'id' => 'resp_tooltipcheck',
        'status' => BacklogStatus::Completed,
        'created_at' => now()->subSeconds(400),
        'started_at' => now()->subSeconds(150),
        'finished_at' => now()->subSeconds(20),
    ]);

    [$page] = signIn();

    $page->navigate('/logs');

    // Entry, status, gateway/model, caller, created.
    $page
        ->assertSeeIn('tbody tr td:nth-child(5)', $entry->created_at->format('d.m.Y H:i:s'))
        ->assertAttribute('tbody tr td:nth-child(5) > div', 'title', $entry->created_at->toDateTimeString());
});

it('pairs the gateway with its model in one column', function () {
    $gateway = Gateway::factory()->create(['name' => 'Mac Ollama']);

    BacklogEntry::factory()->create([
        'id' => 'resp_pairedcell',
        'gateway_id' => $gateway->id,
        'model' => 'qwen3-vl:30b-a3b',
    ]);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertSeeIn('tbody tr td:nth-child(3)', 'Mac Ollama')
        ->assertSeeIn('tbody tr td:nth-child(3)', 'qwen3-vl:30b-a3b');
});

it('leaves attempts, waited and duration to the entry page', function () {
    BacklogEntry::factory()->create([
        'id' => 'resp_timingcheck',
        'status' => BacklogStatus::Completed,
        'created_at' => now()->subSeconds(400),
        'started_at' => now()->subSeconds(150),
        'finished_at' => now()->subSeconds(20),
    ]);

    [$page] = signIn();

    $page->navigate('/logs');

    // waited = 250s = "4m 10s", duration = 130s = "2m 10s". Both belong to the
    // entry page now, so neither the headings nor the values appear out here.
    $page->assertDontSee('Waited')
        ->assertDontSee('Duration')
        ->assertDontSee('Attempts')
        ->assertDontSee('4m 10s')
        ->assertDontSee('2m 10s');
});

it('warns about the oldest queued entry', function () {
    BacklogEntry::factory()->create(['created_at' => now()->subMinutes(45)]);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertSee('Oldest queued entry is waiting since')
        ->assertSee('45');
});

it('hides the oldest-queued warning when nothing is queued', function () {
    BacklogEntry::factory()->completed()->create();

    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertDontSee('Oldest queued entry is waiting since');
});

it('advertises the auto-refresh interval in the table footer', function () {
    BacklogEntry::factory()->create();

    [$page] = signIn();

    $page->navigate('/logs');

    $page->assertSee('Auto-refreshes every 15');
});

it('keeps the active status filter when submitting a search from a filtered view', function () {
    $match = BacklogEntry::factory()->failed()->create(['id' => 'resp_combo0001', 'model' => 'combo-model']);
    BacklogEntry::factory()->failed()->create(['id' => 'resp_combo0002', 'model' => 'other-model']);
    BacklogEntry::factory()->completed()->create(['id' => 'resp_combo0003', 'model' => 'combo-model']);

    [$page] = signIn();

    $page->navigate('/logs');

    $page->navigate(route('logs', ['status' => 'failed']))
        ->select('model', 'combo-model')
        ->click('button[type="submit"]')
        ->assertQueryStringHas('status', 'failed')
        ->assertQueryStringHas('model', 'combo-model')
        ->assertSee('#'.$match->id)
        ->assertDontSee('#resp_combo0002')
        ->assertDontSee('#resp_combo0003');
});
