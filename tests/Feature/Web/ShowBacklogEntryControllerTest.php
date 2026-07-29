<?php

declare(strict_types=1);

use App\Models\BacklogEntry;

it('renders the details page for an operator holding the dashboard token', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertSee($entry->id)
        ->assertSee('Back to backlog');
});

it('returns 404 for unknown entries', function () {
    $this->get(route('backlog.entry', 'resp_missing'))->assertNotFound();
});

it('shows the request that was sent upstream', function () {
    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'test-model', 'messages' => [['role' => 'user', 'content' => 'ping me']]],
    ]);

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertSee('Stored request')
        ->assertSee('ping me');
});

it('shows the answer and usage of a completed entry', function () {
    $entry = BacklogEntry::factory()->completed('the answer is 42')->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertSee('the answer is 42')
        ->assertSee('Answer')
        ->assertSee('total_tokens');
});

it('shows the full upstream error of a failed entry', function () {
    $entry = BacklogEntry::factory()->create([
        'status' => 'failed',
        'error' => [
            'type' => 'upstream',
            'status' => 400,
            'message' => 'Upstream responded with HTTP 400',
            'body' => ['error' => ['message' => 'Invalid model name passed in model=nope:1b']],
        ],
    ]);

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertSee('Upstream responded with HTTP 400')
        ->assertSee('Invalid model name passed in model=nope:1b');
});

it('shows timings and attempt counters', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertSee('Created')
        ->assertSee('Started')
        ->assertSee('Finished')
        ->assertSee('Updated')
        ->assertSee('Attempts');
});

it('shows the api key hash and the forwarded header names', function () {
    $entry = BacklogEntry::factory()->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertSee('API key hash')
        ->assertSee($entry->api_key_hash)
        ->assertSee('Forwarded header names')
        ->assertSee('authorization');
});

it('hides the header values', function () {
    $entry = BacklogEntry::factory()->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertDontSee('sk-test-caller')
        ->assertSee('Values are never shown');
});

it('reports cleared headers once the entry is final', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get(route('backlog.entry', $entry->id))->assertOk()->assertSee('cleared');
});

it('renders a queued entry that has no result yet', function () {
    $entry = BacklogEntry::factory()->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertSee($entry->id)
        ->assertDontSee('Answer');
});
