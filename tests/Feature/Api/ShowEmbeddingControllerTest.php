<?php

declare(strict_types=1);

use App\Enums\BacklogKind;
use App\Models\BacklogEntry;
use App\Models\Gateway;

it('answers 202 while the embedding is still in line', function () {
    $entry = BacklogEntry::factory()->embeddings()->create();

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertStatus(202)
        ->assertJsonPath('id', $entry->id)
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('queue.position', 1)
        // A polling loop reads the status code; "not yet" must never come back
        // as a 200 with nothing in it.
        ->assertHeader('Retry-After');
});

it('answers 202 while a worker is running it', function () {
    $entry = BacklogEntry::factory()->embeddings()->running()->create();

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertStatus(202)
        ->assertJsonPath('status', 'in_progress');
});

it('hands back the upstream body once a worker has answered', function () {
    $entry = BacklogEntry::factory()->embedded()->create();

    // Exactly the OpenAI shape, so a client can read `data[].embedding`
    // without knowing a queue was ever involved.
    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('object', 'list')
        ->assertJsonPath('data.0.embedding', [0.1, 0.2, 0.3, 0.4])
        ->assertJsonPath('data.1.index', 1)
        ->assertJsonPath('usage.total_tokens', 4);
});

it('reports a failed embedding with the upstream status it failed on', function () {
    $entry = BacklogEntry::factory()->embeddings()->failed()->create([
        'error' => ['status' => 429, 'type' => 'upstream', 'message' => 'Upstream responded with HTTP 429'],
    ]);

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertStatus(429)
        ->assertJsonPath('error.type', 'upstream_error')
        ->assertJsonPath('error.attempts', $entry->attempts);
});

it('falls back to 502 for a failure that never reached an upstream status', function () {
    $entry = BacklogEntry::factory()->embeddings()->failed()->create();

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())->assertStatus(502);
});

it('reports a cancelled embedding rather than an empty answer', function () {
    $entry = BacklogEntry::factory()->embeddings()->cancelled()->create();

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertStatus(409)
        ->assertJsonPath('error.type', 'cancelled');
});

it('does not hand one caller another caller-s embedding', function () {
    $entry = BacklogEntry::factory()->embedded()->create(['api_key_hash' => str_repeat('b', 64)]);

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())->assertNotFound();
});

it('does not collect a chat entry through the embeddings endpoint', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    // Its id is valid; the shape it would come back in is not.
    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())->assertNotFound();
});

it('collects under the gateway prefix too', function () {
    $gateway = Gateway::factory()->create();
    $entry = BacklogEntry::factory()->embedded()->create(['gateway_id' => $gateway->id]);

    $this->getJson("/{$gateway->id}/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('object', 'list');
});

it('404s an id that never existed', function () {
    $this->getJson('/v1/embeddings/resp_nothing', gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'not_found');
});

it('404s an embedding the caller has already deleted', function () {
    $entry = BacklogEntry::factory()->embedded()->create();

    $this->deleteJson("/v1/responses/{$entry->id}", [], gatewayAuth())->assertOk();

    // Deleting removes it from the API; the row is kept for the dashboard.
    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())->assertNotFound();
    expect(BacklogEntry::withTrashed()->find($entry->id))->not->toBeNull();
});

it('still hands back an embedding whose gateway has been deleted since', function () {
    $gateway = Gateway::factory()->create();
    $entry = BacklogEntry::factory()->embedded()->create(['gateway_id' => $gateway->id]);

    $gateway->forceDelete();

    // History outlives its upstream: the vectors were computed and are still
    // the caller's to collect.
    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertOk()
        ->assertJsonPath('data.0.index', 0);
});

it('falls back to 502 when the stored failure status is not a usable one', function () {
    $entry = BacklogEntry::factory()->embeddings()->failed()->create([
        'error' => ['status' => 'boom', 'message' => 'nonsense from somewhere'],
    ]);

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())->assertStatus(502);
});

it('reports the error message the entry recorded', function () {
    $entry = BacklogEntry::factory()->embeddings()->failed('ConnectionException: Connection refused')->create();

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())
        ->assertStatus(502)
        ->assertJsonPath('error.message', 'ConnectionException: Connection refused');
});

it('does not answer for an id belonging to a moderation', function () {
    $entry = BacklogEntry::factory()->completed()->create(['kind' => BacklogKind::Moderations]);

    $this->getJson("/v1/embeddings/{$entry->id}", gatewayAuth())->assertNotFound();
    $this->getJson("/v1/moderations/{$entry->id}", gatewayAuth())->assertOk();
});
