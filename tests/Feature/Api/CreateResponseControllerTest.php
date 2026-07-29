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
