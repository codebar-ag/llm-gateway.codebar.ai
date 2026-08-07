<?php

declare(strict_types=1);

use App\Enums\BacklogKind;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Inertia\Testing\AssertableInertia;

it('renders the details page for an operator holding the dashboard token', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get(route('logs.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Entry')
            ->where('entry.id', $entry->id)
            ->where('urls.logs', route('logs')));
});

it('returns 404 for unknown entries', function () {
    $this->get(route('logs.entry', 'resp_missing'))->assertNotFound();
});

it('shows the request that was sent upstream', function () {
    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'test-model', 'messages' => [['role' => 'user', 'content' => 'ping me']]],
    ]);

    $this->get(route('logs.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entry.request.model', 'test-model')
            ->where('entry.request.messages.0.content', 'ping me'));
});

it('shows the answer and usage of a completed entry', function () {
    $entry = BacklogEntry::factory()->completed('the answer is 42')->create();

    $this->get(route('logs.entry', $entry->id))
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

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    expect($props['entry']['error']['details'])
        ->toContain('Upstream responded with HTTP 400')
        ->toContain('Invalid model name passed in model=nope:1b')
        ->and($props['entry']['error']['raw']['status'])->toBe(400);
});

it('shows timings and attempt counters', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    expect(array_column($props['entry']['times'], 'label'))
        ->toBe(['Created', 'Started', 'Finished', 'Updated'])
        ->and(array_column($props['entry']['facts'], 'label'))->toContain('Attempts');
});

it('tells the page which format the answer turned out to be', function (string $answer, string $expected) {
    $entry = BacklogEntry::factory()->completed($answer)->create();

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    expect($props['entry']['answerFormat'])->toBe($expected);
})->with([
    'structured' => ['{"total":1284.50}', 'json'],
    'prose' => ['The answer is 42.', 'markdown'],
]);

it('carries the full upstream url as the tooltip for its truncated tile', function () {
    $gateway = Gateway::factory()->create(['base_url' => 'http://10.0.1.14:11434']);
    $entry = BacklogEntry::factory()->create(['gateway_id' => $gateway->id]);

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    $facts = collect($props['entry']['facts'])->keyBy('label');

    expect($facts['Upstream']['exact'])->toBe($gateway->endpoint())
        ->and($facts['Upstream']['exact'])->toBe($facts['Upstream']['value']);
});

it('shows the api key hash and the forwarded header names', function () {
    $entry = BacklogEntry::factory()->create();

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    expect($props['entry']['apiKeyHash'])->toBe($entry->api_key_hash)
        ->and($props['entry']['headerNames'])->toContain('authorization');
});

it('hides the header values', function () {
    $entry = BacklogEntry::factory()->create();

    // The whole page object is serialised into the response, so a leaked value
    // would show up in the HTML — this is the assertion that proves it doesn't.
    $this->get(route('logs.entry', $entry->id))
        ->assertOk()
        ->assertDontSee('sk-test-caller');
});

it('reports cleared headers once the entry is final', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $facts = collect(inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk())['entry']['facts'])
        ->pluck('value', 'label');

    expect($facts['Forwarded headers'])->toBe('— (cleared)');
});

it('offers a download url for every part', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $urls = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk())['urls']['downloads'];

    expect(array_keys($urls))->toBe(['request', 'response', 'answer', 'error'])
        ->and($urls['request'])->toBe(route('logs.entry.download', ['id' => $entry->id, 'part' => 'request']));
});

it('says how long a wait was in minutes once it passes one', function () {
    $entry = BacklogEntry::factory()->create([
        'created_at' => now()->subSeconds(1554),
        'started_at' => now(),
    ]);

    $facts = collect(inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk())['entry']['facts'])
        ->keyBy('label');

    // The rounded form is what is read; the raw seconds stay for the tooltip.
    expect($facts['Waited']['value'])->toBe('25m 54s')
        ->and($facts['Waited']['exact'])->toBe('1554s');
});

it('renders a queued entry that has no result yet', function () {
    $entry = BacklogEntry::factory()->create();

    $this->get(route('logs.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entry.id', $entry->id)
            ->where('entry.answer', null)
            ->where('entry.error', null));
});

/*
 | The page shows the stored request, which for a translating gateway is not
 | what went on the wire. `protocolNote` is the warning that says so, and both
 | of its branches matter: saying nothing on a passthrough gateway is as much
 | the point as saying something on the other two.
 */

it('warns that a translating gateway rewrites the body on the way out', function (string $state, string $label) {
    $gateway = Gateway::factory()->{$state}()->create();
    $entry = BacklogEntry::factory()->create(['gateway_id' => $gateway->id]);

    $this->get(route('logs.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where(
            'entry.protocolNote',
            "Sent as {$label}: the driver rewrites this body on the way out.",
        ));
})->with([
    ['anthropic', 'Anthropic'],
    ['ollama', 'Ollama'],
]);

it('says nothing about the protocol when the gateway is a passthrough', function () {
    $entry = BacklogEntry::factory()->create();

    $this->get(route('logs.entry', $entry->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('entry.protocolNote', null));
});

it('says nothing about the protocol when the gateway is gone entirely', function () {
    $entry = BacklogEntry::factory()->create();
    $entry->gateway->forceDelete();

    // `gateway_id` is nullOnDelete, so a force-deleted gateway leaves the entry
    // with no protocol to report on rather than with a dangling one.
    $this->get(route('logs.entry', $entry->fresh()->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('entry.protocolNote', null));
});

it('names the endpoint an entry was queued through, and summarises its answer', function () {
    $entry = BacklogEntry::factory()->embedded()->create();

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    expect(collect($props['entry']['facts'])->firstWhere('label', 'Endpoint')['value'])
        ->toBe('embeddings')
        // Summarised rather than thousands of floats, and the pane says where
        // the vectors themselves are.
        ->and($props['entry']['answer'])->toBe('2 embeddings × 4 dimensions')
        ->and($props['entry']['answerNote'])->toContain('under Response');
});

it('says nothing about the answer of an entry a worker ran', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    expect(inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk())['entry']['answerNote'])
        ->toBeNull();
});

it('shows no answer for an embedding still waiting in the queue', function () {
    $entry = BacklogEntry::factory()->embeddings()->create();

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    // Not "0 embeddings × 0 dimensions": nothing has answered yet, which is a
    // different fact from an upstream that answered with nothing.
    expect($props['entry']['answer'])->toBeNull();
});

it('shows the verdict of a moderation whole, since it is small enough to read', function () {
    $entry = BacklogEntry::factory()->completed()->create([
        'kind' => BacklogKind::Moderations,
        'output' => ['id' => 'modr-1', 'results' => [['flagged' => true]]],
    ]);

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());

    expect($props['entry']['answer'])->toContain('"flagged": true')
        ->and($props['entry']['answerFormat'])->toBe('json')
        // Only embeddings are summarised away, so only they need the warning.
        ->and($props['entry']['answerNote'])->toBeNull();
});

it('shortens a payload blob the page could not afford to inline', function () {
    // An OCR request, the way a vision caller actually sends one: the image
    // rides inside the request as a single base64 string of over a megabyte.
    $image = 'data:image/png;base64,'.str_repeat('QUJDRA==', 200_000);

    $entry = BacklogEntry::factory()->create([
        'request' => [
            'model' => 'qwen3-vl:32b',
            'messages' => [['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'read this receipt'],
                ['type' => 'image_url', 'image_url' => ['url' => $image]],
            ]]],
        ],
    ]);

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());
    $url = $props['entry']['request']['messages'][0]['content'][1]['image_url']['url'];

    expect($url)
        ->toContain('the download holds the whole value')
        ->and(strlen($url))->toBeLessThan(2_000)
        // Everything readable in the same request arrives untouched — the
        // preview drops the blob, not the shape around it.
        ->and($props['entry']['request']['model'])->toBe('qwen3-vl:32b')
        ->and($props['entry']['request']['messages'][0]['content'][0]['text'])->toBe('read this receipt');
});

it('caps an embeddings vector the page could not afford to inline', function () {
    // A real embeddings output: hundreds of thousands of floats nobody scrolls.
    $entry = BacklogEntry::factory()->create([
        'kind' => BacklogKind::Embeddings,
        'output' => ['data' => [[
            'object' => 'embedding',
            'index' => 0,
            'embedding' => array_map(fn (int $i): float => $i / 1000, range(1, 1536)),
        ]]],
    ]);

    $props = inertiaProps($this->get(route('logs.entry', $entry->id))->assertOk());
    $vector = $props['entry']['output']['data'][0]['embedding'];

    expect($vector)->toHaveCount(101)
        ->and($vector[100])->toContain('1,436 more values')
        // The fields around the vector survive: only the list was cut.
        ->and($props['entry']['output']['data'][0]['object'])->toBe('embedding');
});
