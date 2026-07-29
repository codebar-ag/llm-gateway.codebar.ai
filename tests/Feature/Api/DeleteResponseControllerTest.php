<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use App\Models\BacklogEntry;

it('deletes an entry in any status', function (string $state) {
    $entry = BacklogEntry::factory()->{$state}()->create();

    $this->deleteJson("/v1/responses/{$entry->id}", [], gatewayAuth())
        ->assertOk()
        ->assertJson(['id' => $entry->id, 'object' => 'response.deleted', 'deleted' => true]);

    expect(BacklogEntry::find($entry->id))->toBeNull();
})->with(['running', 'completed', 'failed', 'cancelled']);

it('deletes a queued entry', function () {
    $entry = BacklogEntry::factory()->create();

    $this->deleteJson("/v1/responses/{$entry->id}", [], gatewayAuth())->assertOk();

    expect(BacklogEntry::count())->toBe(0);
});

it('returns 404 for unknown entries', function () {
    $this->deleteJson('/v1/responses/resp_missing', [], gatewayAuth())
        ->assertStatus(404)
        ->assertJsonPath('error.type', 'not_found');
});

it('keeps the row as history rather than erasing it', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->deleteJson("/v1/responses/{$entry->id}", [], gatewayAuth())->assertOk();

    expect(BacklogEntry::find($entry->id))->toBeNull()
        ->and(BacklogEntry::withTrashed()->find($entry->id))->not->toBeNull();
});

it('hides a deleted entry from listing and reading', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->deleteJson("/v1/responses/{$entry->id}", [], gatewayAuth())->assertOk();

    $this->getJson("/v1/responses/{$entry->id}", gatewayAuth())->assertNotFound();
    $this->getJson('/v1/responses', gatewayAuth())->assertOk()->assertJsonCount(0, 'data');
});

it('hides a deleted entry from the dashboard', function () {
    $entry = BacklogEntry::factory()->completed()->create();
    $entry->delete();

    config()->set('gateway.dashboard.token', dashboardToken());

    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->get('/')
        ->assertOk()
        ->assertDontSee($entry->id);
});
