<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;

it('cancels a queued entry', function () {
    $entry = BacklogEntry::factory()->create();

    $this->postJson("/v1/responses/{$entry->id}/cancel", [], gatewayAuth())
        ->assertOk()
        ->assertJsonPath('status', 'cancelled');

    expect($entry->refresh()->status)->toBe(BacklogStatus::Cancelled)
        ->and($entry->finished_at)->not->toBeNull();
});

it('refuses to cancel entries that already started', function (string $state) {
    $entry = BacklogEntry::factory()->{$state}()->create();

    $this->postJson("/v1/responses/{$entry->id}/cancel", [], gatewayAuth())
        ->assertStatus(409)
        ->assertJsonPath('error.type', 'invalid_state')
        ->assertJsonPath('error.message', "Not cancellable, status is '{$entry->status->value}'");

    expect($entry->refresh()->status)->not->toBe(BacklogStatus::Cancelled);
})->with(['running', 'completed', 'failed']);

it('refuses to cancel twice', function () {
    $entry = BacklogEntry::factory()->cancelled()->create();

    $this->postJson("/v1/responses/{$entry->id}/cancel", [], gatewayAuth())->assertStatus(409);
});

it('returns 404 for unknown entries', function () {
    $this->postJson('/v1/responses/resp_missing/cancel', [], gatewayAuth())
        ->assertStatus(404)
        ->assertJsonPath('error.type', 'not_found');
});
