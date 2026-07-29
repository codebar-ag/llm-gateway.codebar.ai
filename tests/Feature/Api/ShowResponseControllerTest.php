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
