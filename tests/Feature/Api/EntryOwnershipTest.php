<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use Database\Factories\BacklogEntryFactory;
use Illuminate\Support\Facades\Queue;

/*
 | An entry belongs to the key that queued it. Everything here is the same
 | question asked once per endpoint: does someone else's key get to see it?
 */

function otherCaller(): array
{
    return ['Authorization' => 'Bearer sk-somebody-else'];
}

function ownedByOtherCaller(): BacklogEntry
{
    return BacklogEntry::factory()->create([
        'api_key_hash' => hash('sha256', 'sk-somebody-else'),
    ]);
}

it('hides another key\'s entry behind a 404', function () {
    $entry = ownedByOtherCaller();

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'not_found');
});

it('keeps another key\'s entries out of the list', function () {
    $mine = BacklogEntry::factory()->create();
    $theirs = ownedByOtherCaller();

    $this->getJson('/v1/responses', gatewayAuth())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertDontSee($theirs->id);
});

it('refuses to cancel another key\'s entry', function () {
    $entry = ownedByOtherCaller();

    $this->postJson("/v1/responses/{$entry->id}/cancel", [], gatewayAuth())
        ->assertNotFound();

    expect($entry->refresh()->status->value)->toBe('queued');
});

it('refuses to delete another key\'s entry', function () {
    $entry = ownedByOtherCaller();

    $this->deleteJson("/v1/responses/{$entry->id}", [], gatewayAuth())
        ->assertNotFound();

    expect(BacklogEntry::find($entry->id))->not->toBeNull();
});

it('lets the key that queued the entry read it back', function () {
    $entry = ownedByOtherCaller();

    $this->getJson("/v1/responses/{$entry->id}", otherCaller())
        ->assertOk()
        ->assertJsonPath('id', $entry->id);
});

it('lets the key that queued the entry cancel and delete it', function () {
    $entry = ownedByOtherCaller();

    $this->postJson("/v1/responses/{$entry->id}/cancel", [], otherCaller())->assertOk();
    $this->deleteJson("/v1/responses/{$entry->id}", [], otherCaller())->assertOk();

    expect(BacklogEntry::find($entry->id))->toBeNull();
});

it('follows the whole queue-then-poll round trip for one key', function () {
    Queue::fake();

    $created = $this->postJson('/v1/responses', [
        'model' => 'test-model',
        'input' => 'Say PONG',
    ], gatewayAuth())->assertStatus(202);

    $id = $created->json('id');

    $this->getJson("/v1/responses/{$id}", gatewayAuth())->assertOk();
    $this->getJson("/v1/responses/{$id}", otherCaller())->assertNotFound();
});

it('does not let a keyless caller reach a keyed entry', function () {
    $entry = BacklogEntry::factory()->create();

    expect($entry->api_key_hash)->toBe(hash('sha256', BacklogEntryFactory::CALLER_KEY));

    $this->getJson("/v1/responses/{$entry->id}")->assertNotFound();
    $this->getJson('/v1/responses')->assertOk()->assertJsonCount(0, 'data');
    $this->deleteJson("/v1/responses/{$entry->id}")->assertNotFound();
});

it('gives keyless callers only the entries queued without a key', function () {
    $keyless = BacklogEntry::factory()->create(['api_key_hash' => null]);
    $keyed = BacklogEntry::factory()->create();

    $this->getJson('/v1/responses')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $keyless->id)
        ->assertDontSee($keyed->id);
});

it('does not leak existence through the cancel conflict response', function () {
    $running = BacklogEntry::factory()->running()->create([
        'api_key_hash' => hash('sha256', 'sk-somebody-else'),
    ]);

    $this->postJson("/v1/responses/{$running->id}/cancel", [], gatewayAuth())
        ->assertNotFound()
        ->assertJsonPath('error.type', 'not_found');
});
