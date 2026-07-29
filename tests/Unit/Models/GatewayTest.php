<?php

declare(strict_types=1);

use App\Enums\GatewayProtocol;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Services\Gateways;

it('seeds one default gateway with the table', function () {
    expect(Gateway::count())->toBe(1)
        ->and(Gateway::default())->not->toBeNull()
        ->and(Gateway::default()->protocol)->toBe(GatewayProtocol::OpenAi)
        ->and(Gateway::default()->base_url)->toBe(config('gateway.upstream.default_url'));
});

it('builds its endpoint from the base url and the protocol', function () {
    expect(Gateway::factory()->make(['base_url' => 'http://llm.test'])->endpoint())
        ->toBe('http://llm.test/v1/chat/completions')
        ->and(Gateway::factory()->anthropic()->make(['base_url' => 'https://api.test'])->endpoint())
        ->toBe('https://api.test/v1/messages')
        ->and(Gateway::factory()->ollama()->make(['base_url' => 'http://box:11434'])->endpoint())
        ->toBe('http://box:11434/api/chat');
});

it('trims a trailing slash off the base url', function () {
    expect(Gateway::factory()->make(['base_url' => 'http://llm.test/'])->endpoint())
        ->toBe('http://llm.test/v1/chat/completions');
});

it('prefers a path override over the protocol default', function () {
    expect(Gateway::factory()->make(['base_url' => 'http://llm.test', 'path' => '/proxy/chat'])->endpoint())
        ->toBe('http://llm.test/proxy/chat');
});

it('tells a caller which base url addresses it', function () {
    config(['app.url' => 'https://llm.codebar.net/']);

    $gateway = Gateway::factory()->create();

    expect($gateway->callerBaseUrl())->toBe("https://llm.codebar.net/{$gateway->id}/v1");
});

it('gets an unguessable id rather than a sequential one', function () {
    expect(Gateway::factory()->create()->id)->toMatch('/^[0-9a-f-]{36}$/');
});

it('hides inactive gateways from the active scope', function () {
    Gateway::factory()->inactive()->create();

    expect(Gateway::query()->active()->count())->toBe(1);
});

it('keeps exactly one default when the flag moves', function () {
    $second = Gateway::factory()->create();

    (new Gateways)->makeDefault($second);

    expect(Gateway::default()->id)->toBe($second->id)
        ->and(Gateway::query()->where('is_default', true)->count())->toBe(1);
});

it('refuses to delete the default', function () {
    expect(fn () => (new Gateways)->delete(Gateway::default()))
        ->toThrow(RuntimeException::class, 'Make another gateway the default');
});

it('soft deletes so entries keep their attribution', function () {
    $gateway = Gateway::factory()->create();
    $entry = BacklogEntry::factory()->create(['gateway_id' => $gateway->id]);

    (new Gateways)->delete($gateway);

    expect(Gateway::count())->toBe(1)
        ->and($entry->fresh()->gateway->name)->toBe($gateway->name);
});

it('counts the entries it answered', function () {
    $gateway = Gateway::factory()->create();
    BacklogEntry::factory()->count(3)->create(['gateway_id' => $gateway->id]);

    expect($gateway->entries()->count())->toBe(3);
});
