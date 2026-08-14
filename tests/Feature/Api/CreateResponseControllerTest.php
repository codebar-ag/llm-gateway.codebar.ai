<?php

declare(strict_types=1);

use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('queues an entry and answers in the responses shape', function () {
    $response = $this->postJson('/v1/responses', [
        'model' => 'test-model',
        'input' => 'hello',
    ], gatewayAuth());

    $response->assertStatus(202)
        ->assertJsonPath('object', 'response')
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('output', []);

    expect(BacklogEntry::find($response->json('id'))->request['messages'])
        ->toBe([['role' => 'user', 'content' => 'hello']]);

    Queue::assertPushed(ProcessBacklogEntry::class);
});

it('translates instructions and multimodal input into chat messages', function () {
    $id = $this->postJson('/v1/responses', [
        'model' => 'm',
        'instructions' => 'Be brief.',
        'input' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'input_text', 'text' => 'What is this?'],
                ['type' => 'input_image', 'image_url' => 'data:image/png;base64,xyz'],
            ],
        ]],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request['messages'])->toBe([
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => 'What is this?'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,xyz']],
        ]],
    ]);
});

it('maps responses parameters onto chat parameters', function () {
    $id = $this->postJson('/v1/responses', [
        'model' => 'm',
        'input' => 'x',
        'max_output_tokens' => 128,
        'temperature' => 0.1,
        'top_p' => 0.9,
        'response_format' => ['type' => 'json_object'],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request)->toBe([
        'model' => 'm',
        'messages' => [['role' => 'user', 'content' => 'x']],
        'max_tokens' => 128,
        'temperature' => 0.1,
        'top_p' => 0.9,
        'response_format' => ['type' => 'json_object'],
    ]);
});

/**
 * The Responses API spells structured output `text.format`, not
 * `response_format`. Until this was translated the key fell outside
 * `PARAMETER_MAP` and was dropped in silence — the request succeeded, the model
 * answered free-form, and nothing said the schema had never been applied.
 */
it('translates the responses text.format schema into a chat response_format', function () {
    $schema = [
        'type' => 'object',
        'properties' => ['number' => ['type' => 'string']],
        'required' => ['number'],
        'additionalProperties' => false,
    ];

    $id = $this->postJson('/v1/responses', [
        'model' => 'm',
        'input' => 'x',
        'text' => ['format' => [
            'type' => 'json_schema',
            'name' => 'document_extraction',
            'schema' => $schema,
            'strict' => true,
        ]],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request)->toBe([
        'model' => 'm',
        'messages' => [['role' => 'user', 'content' => 'x']],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'document_extraction',
                'schema' => $schema,
                'strict' => true,
            ],
        ],
    ]);
});

it('translates a bare json_object format too', function () {
    $id = $this->postJson('/v1/responses', [
        'model' => 'm',
        'input' => 'x',
        'text' => ['format' => ['type' => 'json_object']],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request['response_format'])->toBe(['type' => 'json_object']);
});

it('lets an explicit response_format win over text.format rather than fighting it', function () {
    $id = $this->postJson('/v1/responses', [
        'model' => 'm',
        'input' => 'x',
        'response_format' => ['type' => 'json_object'],
        'text' => ['format' => ['type' => 'json_schema', 'name' => 'ignored', 'schema' => []]],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request['response_format'])->toBe(['type' => 'json_object']);
});

it('adds no response_format for a text block that asks for no format', function () {
    $id = $this->postJson('/v1/responses', [
        'model' => 'm',
        'input' => 'x',
        'text' => ['format' => ['type' => 'text']],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request)->not->toHaveKey('response_format');
});

it('adds no response_format when text carries no format at all', function () {
    $id = $this->postJson('/v1/responses', [
        'model' => 'm',
        'input' => 'x',
        'text' => ['verbosity' => 'low'],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request)->not->toHaveKey('response_format');
});

it('omits parameters the client did not send', function () {
    $id = $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request)->toBe([
        'model' => 'm',
        'messages' => [['role' => 'user', 'content' => 'x']],
    ]);
});

it('accepts a payload without a model', function () {
    $response = $this->postJson('/v1/responses', ['input' => 'x'], gatewayAuth());

    $response->assertStatus(202)->assertJsonPath('model', null);
    expect(BacklogEntry::find($response->json('id'))->request['model'])->toBeNull();
});

it('accepts an empty body', function () {
    $response = $this->postJson('/v1/responses', [], gatewayAuth());

    $response->assertStatus(202);
    expect(BacklogEntry::find($response->json('id'))->request['messages'])->toBe([]);
});

it('queues instead of refusing background false', function () {
    $this->postJson('/v1/responses', [
        'model' => 'm', 'input' => 'x', 'background' => false,
    ], gatewayAuth())->assertStatus(202);
});

it('queues instead of refusing a streaming request', function () {
    $this->postJson('/v1/responses', [
        'model' => 'm', 'input' => 'x', 'stream' => true,
    ], gatewayAuth())->assertStatus(202);
});

it('does not return metadata it no longer keeps', function () {
    $this->postJson('/v1/responses', [
        'model' => 'm', 'input' => 'x', 'metadata' => ['a' => 1],
    ], gatewayAuth())->assertStatus(202)->assertJsonMissingPath('metadata');
});

it('tells the caller where they landed in the queue', function () {
    config()->set('gateway.backlog.workers', 1);
    config()->set('gateway.backlog.estimate.default_seconds', 30);

    BacklogEntry::factory()->count(2)->create(['created_at' => now()->subMinute()]);

    $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], gatewayAuth())
        ->assertStatus(202)
        // Third in line, so the entry counts itself: estimating before the
        // insert would report the queue the caller just missed.
        ->assertJsonPath('queue.position', 3)
        ->assertJsonPath('queue.depth', 3)
        ->assertJsonPath('queue.workers', 1)
        ->assertJsonPath('queue.estimated_seconds', 90);
});

it('quotes the same wait as the chat completions door', function () {
    BacklogEntry::factory()->count(2)->create(['created_at' => now()->subMinute()]);

    $responses = $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], gatewayAuth());
    $chat = $this->postJson('/v1/chat/completions', ['model' => 'm', 'messages' => []], gatewayAuth());

    expect($chat->json('queue.position'))->toBe($responses->json('queue.position') + 1)
        ->and($chat->json('queue.workers'))->toBe($responses->json('queue.workers'))
        ->and($chat->json('queue.estimated_seconds'))
        ->toBeGreaterThanOrEqual($responses->json('queue.estimated_seconds'));
});
