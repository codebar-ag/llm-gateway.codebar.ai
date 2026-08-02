<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use App\Services\Upstream\Upstreams;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/*
 | A tool call is a conversation, not one request — and the queue does not
 | stand in the way of one. Chat completions is stateless: the caller sends
 | the whole history back on the next turn, so a turn ending in a queued
 | answer they poll for is the same turn a synchronous client would have had,
 | with a wait in the middle.
 |
 | This walks the whole loop the way a caller does: declare a tool, collect
 | the call the model asked for, run it, and send the result back.
 |
 | Nothing here drives a worker: `QUEUE_CONNECTION=sync` runs the job inline
 | the moment a controller queues, so the upstream has already been called by
 | the time the POST returns and the entry is collectable straight away.
 */

$tool = [
    'type' => 'function',
    'function' => [
        'name' => 'count_invoices',
        'description' => 'How many invoices are in the archive.',
        'parameters' => ['type' => 'object', 'properties' => ['archive' => ['type' => 'string']]],
    ],
];

it('carries a tool declaration through the queue to the upstream', function () use ($tool) {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'wie viele Rechnungen habe ich im Archiv?']],
        'tools' => [$tool],
        'tool_choice' => 'auto',
    ], gatewayAuth());

    Http::assertSent(fn ($request) => $request['tools'] === [$tool] && $request['tool_choice'] === 'auto');
});

it('hands a queued caller the tool call the model asked for', function () use ($tool) {
    Http::fake(['*' => Http::response(['choices' => [['message' => [
        'content' => '',
        'tool_calls' => [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
        ]],
    ], 'finish_reason' => 'tool_calls']]])]);

    $id = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'wie viele Rechnungen habe ich im Archiv?']],
        'tools' => [$tool],
    ], gatewayAuth())->json('id');

    $this->getJson("/v1/responses/{$id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('output.0.type', 'function_call')
        ->assertJsonPath('output.0.call_id', 'call_1')
        ->assertJsonPath('output.0.name', 'count_invoices')
        ->assertJsonPath('output.0.arguments', '{"archive":"main"}');
});

it('accepts the result of that call as the next turn', function () use ($tool) {
    Http::fake(['*' => Http::response(['choices' => [['message' => [
        'content' => 'Du hast 42 Rechnungen im Archiv.',
    ]]]])]);

    $id = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [
            ['role' => 'user', 'content' => 'wie viele Rechnungen habe ich im Archiv?'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                'id' => 'call_1',
                'type' => 'function',
                'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
            ]]],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'],
        ],
        'tools' => [$tool],
    ], gatewayAuth())->json('id');

    Http::assertSent(fn ($request) => $request['messages'][1]['tool_calls'][0]['id'] === 'call_1'
        && $request['messages'][2] === ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42']);

    $this->getJson("/v1/responses/{$id}", gatewayAuth())
        ->assertJsonPath('output.0.content.0.text', 'Du hast 42 Rechnungen im Archiv.');
});

/*
 | The same loop through the other door. `/v1/responses` states its tools flat
 | and answers a call with a `function_call` item, so the round trip has to
 | survive the translation into chat completions and back.
 */

it('translates a responses tool declaration into chat form', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $this->postJson('/v1/responses', [
        'model' => 'test-model',
        'input' => 'wie viele Rechnungen habe ich im Archiv?',
        'tools' => [[
            'type' => 'function',
            'name' => 'count_invoices',
            'description' => 'How many invoices are in the archive.',
            'parameters' => ['type' => 'object'],
        ]],
        'tool_choice' => ['type' => 'function', 'name' => 'count_invoices'],
    ], gatewayAuth());

    Http::assertSent(fn ($request) => $request['tools'] === [[
        'type' => 'function',
        'function' => [
            'name' => 'count_invoices',
            'description' => 'How many invoices are in the archive.',
            'parameters' => ['type' => 'object'],
        ],
    ]] && $request['tool_choice'] === ['type' => 'function', 'function' => ['name' => 'count_invoices']]);
});

it('reads a function call and its result back off the responses input', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '42 Rechnungen.']]]])]);

    $this->postJson('/v1/responses', [
        'model' => 'test-model',
        'input' => [
            ['role' => 'user', 'content' => 'wie viele Rechnungen habe ich im Archiv?'],
            [
                'type' => 'function_call',
                'call_id' => 'call_1',
                'name' => 'count_invoices',
                'arguments' => '{"archive":"main"}',
            ],
            ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => '42'],
        ],
    ], gatewayAuth());

    Http::assertSent(fn ($request) => $request['messages'][1]['tool_calls'] === [[
        'id' => 'call_1',
        'type' => 'function',
        'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
    ]] && $request['messages'][2] === ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42']);
});

it('adds no tool_choice to a payload that declared no tools', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $this->postJson('/v1/responses', [
        'model' => 'test-model',
        'input' => 'hi',
        'tool_choice' => 'required',
    ], gatewayAuth());

    Http::assertSent(fn ($request) => ! array_key_exists('tool_choice', $request->data())
        && ! array_key_exists('tools', $request->data()));
});

/*
 | A tool this gateway cannot restate reaches the upstream anyway. Dropping a
 | `web_search` the caller declared would leave the model answering from
 | memory and the caller reading it as a searched answer; forwarding it makes
 | the upstream's own rejection the report — which is what every other
 | untranslatable request in this app already does.
 */

it('forwards a hosted tool through the translation to the upstream', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $this->postJson('/v1/responses', [
        'model' => 'test-model',
        'input' => 'wie viele Rechnungen habe ich im Archiv?',
        'tools' => [
            ['type' => 'web_search'],
            ['type' => 'function', 'name' => 'count_invoices', 'parameters' => ['type' => 'object']],
        ],
    ], gatewayAuth());

    Http::assertSent(fn ($request) => $request['tools'] === [
        ['type' => 'web_search'],
        ['type' => 'function', 'function' => [
            'name' => 'count_invoices',
            'parameters' => ['type' => 'object'],
        ]],
    ]);
});

/*
 | And the point of forwarding it: the caller finds out. An upstream that
 | cannot run the tool says so in its own words, which is what the entry
 | records — a dropped tool would have produced a confident answer instead.
 */
it('lets the upstream reject a tool it cannot run, in its own words', function () {
    Http::fake(['*' => Http::response([
        'error' => ['message' => "Invalid value: 'web_search'", 'type' => 'invalid_request_error'],
    ], 400)]);

    // One attempt, so the first rejection is the last word rather than a retry.
    $entry = BacklogEntry::factory()->create([
        'max_attempts' => 1,
        'request' => [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'tools' => [['type' => 'web_search']],
        ],
    ]);

    $job = new ProcessBacklogEntry($entry->id);

    try {
        $job->handle(new Upstreams);
    } catch (RequestException $exception) {
        $job->failed($exception);
    }

    Http::assertSent(fn ($request) => $request['tools'] === [['type' => 'web_search']]);

    $entry->refresh();

    expect($entry->status)->toBe(BacklogStatus::Failed)
        ->and($entry->error['status'])->toBe(400)
        ->and($entry->errorDetails())->toContain("Invalid value: 'web_search'");
});

it('keeps a chat completions tools list exactly as the caller wrote it', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $tools = [
        ['type' => 'web_search'],
        ['type' => 'function', 'function' => ['name' => 'count_invoices', 'strict' => true]],
    ];

    $id = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'hi']],
        'tools' => $tools,
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::findOrFail($id)->request['tools'])->toBe($tools);

    Http::assertSent(fn ($request) => $request['tools'] === $tools);
});
