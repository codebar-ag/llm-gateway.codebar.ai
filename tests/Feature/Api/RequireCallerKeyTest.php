<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Support\Facades\Queue;

/*
 | Ownership of an entry is the hash of the key that queued it, so keyless
 | callers all share one bucket and can read and delete each other's entries.
 | API_REQUIRE_CALLER_KEY is how a reachable deployment closes that, and it is
 | off by default because switching it on rejects existing keyless callers.
 */

beforeEach(function () {
    Queue::fake();
});

it('lets a keyless caller queue while the requirement is off', function () {
    $this->postJson('/v1/chat/completions', ['model' => 'm'])->assertStatus(202);

    expect(BacklogEntry::sole()->api_key_hash)->toBeNull();
});

it('refuses a keyless caller once the requirement is on', function () {
    config()->set('gateway.api.require_caller_key', true);

    $this->postJson('/v1/chat/completions', ['model' => 'm'])
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'no_caller_key');

    expect(BacklogEntry::query()->count())->toBe(0);
});

it('still lets a caller with a key through', function () {
    config()->set('gateway.api.require_caller_key', true);

    $this->postJson('/v1/chat/completions', ['model' => 'm'], gatewayAuth())->assertStatus(202);

    expect(BacklogEntry::sole()->api_key_hash)->not->toBeNull();
});

it('guards reading an entry back, not only queueing one', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    config()->set('gateway.api.require_caller_key', true);

    $this->getJson("/v1/responses/{$entry->id}")->assertStatus(401);
    $this->getJson('/v1/responses')->assertStatus(401);
});

it('guards a request addressed to a gateway by id too', function () {
    config()->set('gateway.api.require_caller_key', true);

    $gateway = Gateway::factory()->create();

    $this->postJson("/{$gateway->id}/v1/chat/completions", ['model' => 'm'])
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'no_caller_key');
});
