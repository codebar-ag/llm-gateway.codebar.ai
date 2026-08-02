<?php

declare(strict_types=1);

use App\Enums\BacklogKind;
use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('queues the request and hands back an id to poll', function () {
    Queue::fake();

    $response = $this->postJson('/v1/moderations', ['model' => 'omni-moderation-latest', 'input' => 'hello'], gatewayAuth())
        ->assertStatus(202);

    $entry = BacklogEntry::sole();

    $response
        ->assertJsonPath('id', $entry->id)
        ->assertJsonPath('object', 'moderation.queued')
        ->assertJsonPath('poll_url', "/v1/moderations/{$entry->id}");

    expect($entry->kind)->toBe(BacklogKind::Moderations)
        ->and($entry->status)->toBe(BacklogStatus::Queued);
});

it('is answered by a worker, which stores the verdict for collection', function () {
    Http::fake(['http://upstream.invalid/v1/moderations' => Http::response([
        'id' => 'modr-1',
        'model' => 'omni-moderation-latest',
        'results' => [['flagged' => false]],
    ])]);

    $this->postJson('/v1/moderations', ['model' => 'omni-moderation-latest', 'input' => 'hello'], gatewayAuth())
        ->assertStatus(202);

    $entry = BacklogEntry::sole();

    expect($entry->status)->toBe(BacklogStatus::Completed)
        ->and($entry->output['results'][0]['flagged'])->toBeFalse();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://upstream.invalid/v1/moderations'
        && $request['input'] === 'hello'
        && $request->hasHeader('Authorization', 'Bearer sk-test-caller'));
});

it('collects the verdict from the moderations endpoint', function () {
    Http::fake(['*' => Http::response(['id' => 'modr-1', 'results' => [['flagged' => true]]])]);

    $id = $this->postJson('/v1/moderations', ['input' => 'hi'], gatewayAuth())->json('id');

    $this->getJson("/v1/moderations/{$id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('results.0.flagged', true);
});

it('queues under a gateway prefix too', function () {
    Queue::fake();
    $gateway = Gateway::factory()->create();

    $this->postJson("/{$gateway->id}/v1/moderations", ['input' => 'hi'], gatewayAuth())
        ->assertStatus(202)
        ->assertJsonPath('poll_url', "/{$gateway->id}/v1/moderations/".BacklogEntry::sole()->id);
});

it('shares the queueing rate limit', function () {
    Queue::fake();
    config(['gateway.backlog.rate_limit' => 1]);

    $this->postJson('/v1/moderations', ['input' => 'hi'], gatewayAuth())->assertStatus(202);

    $this->postJson('/v1/moderations', ['input' => 'hi'], gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'rate_limited');
});
