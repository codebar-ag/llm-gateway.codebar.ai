<?php

declare(strict_types=1);

use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('queues a chat completion and returns 202', function () {
    $response = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ], gatewayAuth());

    $response->assertStatus(202)
        ->assertJsonPath('object', 'chat.completion.queued')
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('model', 'test-model');

    $id = $response->json('id');

    expect($id)->toStartWith('resp_')
        ->and($response->json('poll_url'))->toBe("/v1/responses/{$id}");

    Queue::assertPushed(ProcessBacklogEntry::class, fn (ProcessBacklogEntry $job) => $job->entryId === $id);
});

it('stores the body exactly as it arrived', function () {
    $payload = [
        'model' => 'm',
        'messages' => [['role' => 'user', 'content' => 'hi']],
        'temperature' => 0.2,
        'top_p' => 0.9,
        'seed' => 42,
        'stop' => ['\n'],
        'some_future_field' => ['nested' => true],
    ];

    $id = $this->postJson('/v1/chat/completions', $payload, gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request)->toBe($payload);
});

it('does not strip fields it does not recognise', function () {
    $id = $this->postJson('/v1/chat/completions', [
        'model' => 'm',
        'messages' => [],
        'background' => true,
        'priority' => 5,
        'metadata' => ['a' => 1],
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request)->toHaveKeys(['background', 'priority', 'metadata']);
});

it('accepts a payload without a model', function () {
    $response = $this->postJson('/v1/chat/completions', ['messages' => []], gatewayAuth());

    $response->assertStatus(202)->assertJsonPath('model', null);

    expect(BacklogEntry::find($response->json('id'))->model)->toBeNull();
});

it('accepts an empty body', function () {
    $response = $this->postJson('/v1/chat/completions', [], gatewayAuth());

    $response->assertStatus(202);
    expect(BacklogEntry::find($response->json('id'))->request)->toBe([]);
});

it('queues a streaming request instead of refusing it', function () {
    $id = $this->postJson('/v1/chat/completions', [
        'model' => 'm', 'messages' => [], 'stream' => true,
    ], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->request['stream'])->toBeTrue();
});

it('records the api key hash for attribution', function () {
    $id = $this->postJson('/v1/chat/completions', ['model' => 'm', 'messages' => []], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->api_key_hash)->toBe(hash('sha256', 'sk-test-caller'));
});

it('takes max attempts from the configuration', function () {
    config(['gateway.backlog.max_attempts' => 4]);

    $id = $this->postJson('/v1/chat/completions', ['model' => 'm'], gatewayAuth())->json('id');

    expect(BacklogEntry::find($id)->max_attempts)->toBe(4);
});
