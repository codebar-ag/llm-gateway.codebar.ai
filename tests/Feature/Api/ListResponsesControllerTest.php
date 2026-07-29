<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use Illuminate\Support\Facades\DB;

it('lists entries newest first', function () {
    $old = BacklogEntry::factory()->create(['created_at' => now()->subMinute()]);
    $new = BacklogEntry::factory()->create();

    $this->getJson('/v1/responses', gatewayAuth())
        ->assertOk()
        ->assertJsonPath('object', 'list')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
});

it('returns an empty list when nothing was queued', function () {
    $this->getJson('/v1/responses', gatewayAuth())
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('filters by status', function () {
    BacklogEntry::factory()->completed()->create();
    BacklogEntry::factory()->count(2)->failed()->create();

    $this->getJson('/v1/responses?status=failed', gatewayAuth())->assertJsonCount(2, 'data');
    $this->getJson('/v1/responses?status=cancelled', gatewayAuth())->assertJsonCount(0, 'data');
});

it('filters by model', function () {
    $wanted = BacklogEntry::factory()->create(['model' => 'wanted-model']);
    BacklogEntry::factory()->create(['model' => 'other-model']);

    $this->getJson('/v1/responses?model=wanted-model', gatewayAuth())
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $wanted->id);
});

it('combines filters', function () {
    BacklogEntry::factory()->failed()->create(['model' => 'a']);
    BacklogEntry::factory()->failed()->create(['model' => 'b']);
    BacklogEntry::factory()->completed()->create(['model' => 'a']);

    $this->getJson('/v1/responses?status=failed&model=a', gatewayAuth())->assertJsonCount(1, 'data');
});

it('applies the default limit and caps oversized ones', function () {
    BacklogEntry::factory()->count(25)->create();

    $this->getJson('/v1/responses', gatewayAuth())->assertJsonCount(20, 'data');
    $this->getJson('/v1/responses?limit=5', gatewayAuth())->assertJsonCount(5, 'data');
    $this->getJson('/v1/responses?limit=999', gatewayAuth())->assertJsonCount(25, 'data');
});

it('clamps a zero, negative, or non-numeric limit up to one', function (string $limit) {
    BacklogEntry::factory()->count(3)->create();

    $this->getJson("/v1/responses?limit={$limit}", gatewayAuth())->assertJsonCount(1, 'data');
})->with(['0', '-5', 'abc']);

/*
 | A request body is never inspected — it is the upstream's to judge. A filter
 | is different: nothing downstream will ever see it, so a typo has to be said
 | out loud instead of reading as "you have no entries".
 */

it('refuses a status filter it has never heard of', function () {
    BacklogEntry::factory()->completed()->create();

    $this->getJson('/v1/responses?status=finnished', gatewayAuth())
        ->assertStatus(422)
        ->assertJsonPath('error.type', 'invalid_request')
        ->assertJsonPath('error.message', fn (string $message) => str_contains($message, 'finnished')
            && str_contains($message, 'completed'));
});

it('accepts every status it does know', function (string $status) {
    $this->getJson("/v1/responses?status={$status}", gatewayAuth())->assertOk();
})->with(['queued', 'in_progress', 'completed', 'failed', 'cancelled']);

it('ignores an empty status filter rather than refusing it', function () {
    $this->getJson('/v1/responses?status=', gatewayAuth())->assertOk();
});

it('ignores a status filter sent as an array', function () {
    $this->getJson('/v1/responses?status[]=queued', gatewayAuth())->assertOk();
});

it('reports no queue place per row, whatever the list costs', function () {
    BacklogEntry::factory()->count(2)->create();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson('/v1/responses', gatewayAuth())
        ->assertOk()
        ->assertJsonPath('data.0.queue', null);

    $forTwo = $queries;

    BacklogEntry::factory()->count(18)->create();

    $queries = 0;
    $this->getJson('/v1/responses', gatewayAuth())->assertOk()->assertJsonCount(20, 'data');

    // Ten times the rows for the same number of queries: a position counted
    // per row would have made this grow with the page.
    expect($queries)->toBe($forTwo);
});
