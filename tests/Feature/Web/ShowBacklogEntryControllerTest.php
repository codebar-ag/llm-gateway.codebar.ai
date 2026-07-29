<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use Inertia\Testing\AssertableInertia;

it('renders the details page for an operator holding the dashboard token', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Entry')
            ->where('entry.id', $entry->id)
            ->where('urls.backlog', route('backlog')));
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
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entry.request.model', 'test-model')
            ->where('entry.request.messages.0.content', 'ping me'));
});

it('shows the answer and usage of a completed entry', function () {
    $entry = BacklogEntry::factory()->completed('the answer is 42')->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entry.answer', 'the answer is 42')
            ->has('entry.usage.total_tokens'));
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

    $props = inertiaProps($this->get(route('backlog.entry', $entry->id))->assertOk());

    expect($props['entry']['error']['details'])
        ->toContain('Upstream responded with HTTP 400')
        ->toContain('Invalid model name passed in model=nope:1b')
        ->and($props['entry']['error']['raw']['status'])->toBe(400);
});

it('shows timings and attempt counters', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $props = inertiaProps($this->get(route('backlog.entry', $entry->id))->assertOk());

    expect(array_column($props['entry']['times'], 'label'))
        ->toBe(['Created', 'Started', 'Finished', 'Updated'])
        ->and(array_column($props['entry']['facts'], 'label'))->toContain('Attempts');
});

it('shows the api key hash and the forwarded header names', function () {
    $entry = BacklogEntry::factory()->create();

    $props = inertiaProps($this->get(route('backlog.entry', $entry->id))->assertOk());

    expect($props['entry']['apiKeyHash'])->toBe($entry->api_key_hash)
        ->and($props['entry']['headerNames'])->toContain('authorization');
});

it('hides the header values', function () {
    $entry = BacklogEntry::factory()->create();

    // The whole page object is serialised into the response, so a leaked value
    // would show up in the HTML — this is the assertion that proves it doesn't.
    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertDontSee('sk-test-caller');
});

it('reports cleared headers once the entry is final', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $facts = collect(inertiaProps($this->get(route('backlog.entry', $entry->id))->assertOk())['entry']['facts'])
        ->pluck('value', 'label');

    expect($facts['Forwarded headers'])->toBe('— (cleared)');
});

it('offers a download url for every part', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $urls = inertiaProps($this->get(route('backlog.entry', $entry->id))->assertOk())['urls']['downloads'];

    expect(array_keys($urls))->toBe(['request', 'response', 'answer', 'error'])
        ->and($urls['request'])->toBe(route('backlog.entry.download', ['id' => $entry->id, 'part' => 'request']));
});

it('says how long a wait was in minutes once it passes one', function () {
    $entry = BacklogEntry::factory()->create([
        'created_at' => now()->subSeconds(1554),
        'started_at' => now(),
    ]);

    $facts = collect(inertiaProps($this->get(route('backlog.entry', $entry->id))->assertOk())['entry']['facts'])
        ->keyBy('label');

    // The rounded form is what is read; the raw seconds stay for the tooltip.
    expect($facts['Waited']['value'])->toBe('25m 54s')
        ->and($facts['Waited']['exact'])->toBe('1554s');
});

it('renders a queued entry that has no result yet', function () {
    $entry = BacklogEntry::factory()->create();

    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entry.id', $entry->id)
            ->where('entry.answer', null)
            ->where('entry.error', null));
});
