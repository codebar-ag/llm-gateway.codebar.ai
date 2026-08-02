<?php

declare(strict_types=1);

use App\Models\BacklogEntry;

it('shows a completed entry with output and usage', function () {
    $entry = BacklogEntry::factory()->completed('hi')->create(['model' => 'test-model']);

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('id', $entry->id)
        ->assertJsonPath('object', 'response')
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('model', 'test-model')
        ->assertJsonPath('output.0.content.0.text', 'hi')
        ->assertJsonPath('usage.total_tokens', 15);
});

it('shows a failed entry with its error', function () {
    $entry = BacklogEntry::factory()->failed('upstream exploded')->create();

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error.message', 'upstream exploded');
});

it('shows a queued entry with an empty output', function () {
    $entry = BacklogEntry::factory()->create();

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('output', [])
        ->assertJsonPath('usage', null);
});

it('returns 404 for unknown entries', function () {
    $this->getJson('/v1/responses/resp_missing', gatewayAuth())
        ->assertStatus(404)
        ->assertJsonPath('error.type', 'not_found');
});

it('is not wrapped in a data envelope', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('id', $entry->id)
        ->assertJsonMissingPath('data');
});

it('reports a place in line while the entry is still waiting', function () {
    config()->set('gateway.backlog.workers', 1);
    config()->set('gateway.backlog.estimate.default_seconds', 30);

    BacklogEntry::factory()->count(2)->create(['created_at' => now()->subMinute()]);
    $mine = BacklogEntry::factory()->create();

    $this->getJson("/v1/responses/{$mine->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('queue.position', 3)
        ->assertJsonPath('queue.estimated_seconds', 90)
        ->assertHeader('Retry-After', '90');
});

it('recomputes the position as the line moves rather than freezing it', function () {
    $ahead = BacklogEntry::factory()->count(2)->create(['created_at' => now()->subMinute()]);
    $mine = BacklogEntry::factory()->create();

    expect($this->getJson("/v1/responses/{$mine->id}", gatewayAuth())->json('queue.position'))->toBe(3);

    $ahead->first()->markCompleted('done', []);

    expect($this->getJson("/v1/responses/{$mine->id}", gatewayAuth())->json('queue.position'))->toBe(2);
});

it('drops the queue block once the entry is no longer waiting', function () {
    $entry = BacklogEntry::factory()->completed('hi')->create();

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('queue', null)
        ->assertHeaderMissing('Retry-After');
});

it('never tells a client to retry immediately', function () {
    config()->set('gateway.backlog.estimate.default_seconds', 0);

    $entry = BacklogEntry::factory()->create();

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('queue.estimated_seconds', 0)
        ->assertHeader('Retry-After', '1');
});

it('does not answer for a synchronously answered call', function () {
    $entry = BacklogEntry::factory()->embeddings()->create();

    // 404 rather than the entry: the Responses API only knows what it queued.
    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())->assertNotFound();
    $this->postJson("/v1/responses/{$entry->id}/cancel", [], gatewayAuth())->assertNotFound();
    $this->deleteJson("/v1/responses/{$entry->id}", [], gatewayAuth())->assertNotFound();

    expect($entry->fresh()->trashed())->toBeFalse();
});
