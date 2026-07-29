<?php

declare(strict_types=1);

use App\Enums\GatewayProtocol;
use App\Models\BacklogEntry;
use App\Models\Gateway;

/** @return array<string, mixed> */
function gatewayAttributes(array $overrides = []): array
{
    return [
        'name' => 'Ollama box',
        'protocol' => GatewayProtocol::Ollama->value,
        'base_url' => 'http://127.0.0.1:11434',
        'path' => null,
        'default_model' => 'llama3.2:3b',
        'timeout' => 600,
        'connect_timeout' => 5,
        'is_active' => '1',
        'description' => null,
        ...$overrides,
    ];
}

it('lists the gateways with the caller base url shortened but still copyable', function () {
    $gateway = Gateway::default();

    $this->get(route('gateways'))
        ->assertOk()
        ->assertSee($gateway->name)
        ->assertSee('/'.substr($gateway->id, 0, 8).'…/v1')
        ->assertSee('data-copy="'.$gateway->callerBaseUrl().'"', false);
});

it('answers default and active in their own columns', function () {
    $default = Gateway::default();
    $other = Gateway::factory()->ollama()->create(['name' => 'Sidelined', 'is_active' => false]);

    $response = $this->get(route('gateways'))->assertOk();

    // Between a name and its protocol badge sit only the default and active cells.
    $response->assertSeeInOrder([$default->name, 'yes', 'yes', $default->protocol->label()])
        ->assertSeeInOrder([$other->name, 'no', 'no', $other->protocol->label()]);
});

it('creates a gateway', function () {
    $this->post(route('gateways.store'), gatewayAttributes())
        ->assertRedirect(route('gateways'));

    $gateway = Gateway::query()->where('name', 'Ollama box')->sole();

    expect($gateway->protocol)->toBe(GatewayProtocol::Ollama)
        ->and($gateway->endpoint())->toBe('http://127.0.0.1:11434/api/chat')
        ->and($gateway->default_model)->toBe('llama3.2:3b')
        ->and($gateway->is_active)->toBeTrue()
        ->and($gateway->is_default)->toBeFalse();
});

it('updates a gateway', function () {
    $gateway = Gateway::factory()->create();

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['name' => 'Renamed']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->name)->toBe('Renamed')
        ->and($gateway->fresh()->base_url)->toBe('http://127.0.0.1:11434');
});

it('leaves is_active alone when the field was not submitted at all', function () {
    $gateway = Gateway::factory()->create(['is_active' => true]);

    $attributes = gatewayAttributes();
    unset($attributes['is_active']);

    $this->put(route('gateways.update', $gateway), $attributes)->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_active)->toBeTrue();
});

it('deactivates a gateway when the form submits its hidden zero', function () {
    $gateway = Gateway::factory()->create(['is_active' => true]);

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['is_active' => '0']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_active)->toBeFalse();
});

it('lets a gateway keep its own name when edited', function () {
    $gateway = Gateway::factory()->create(['name' => 'Keep me']);

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['name' => 'Keep me']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->name)->toBe('Keep me');
});

it('rejects a duplicate name', function () {
    Gateway::factory()->create(['name' => 'Taken']);

    $this->post(route('gateways.store'), gatewayAttributes(['name' => 'Taken']))
        ->assertSessionHasErrors('name');
});

it('rejects a gateway slower than the ceiling', function () {
    config(['gateway.upstream.max_timeout' => 900]);

    $this->post(route('gateways.store'), gatewayAttributes(['timeout' => 901]))
        ->assertSessionHasErrors('timeout');

    expect(Gateway::query()->where('name', 'Ollama box')->exists())->toBeFalse();
});

it('rejects a path that is not a path', function () {
    $this->post(route('gateways.store'), gatewayAttributes(['path' => 'v1/messages']))
        ->assertSessionHasErrors('path');
});

it('rejects a base url that is not a url', function () {
    $this->post(route('gateways.store'), gatewayAttributes(['base_url' => 'not a url']))
        ->assertSessionHasErrors('base_url');
});

it('rejects an unknown protocol', function () {
    $this->post(route('gateways.store'), gatewayAttributes(['protocol' => 'gemini']))
        ->assertSessionHasErrors('protocol');
});

it('moves the default and clears the previous holder', function () {
    $previous = Gateway::default();
    $gateway = Gateway::factory()->create();

    $this->post(route('gateways.default', $gateway))->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_default)->toBeTrue()
        ->and($previous->fresh()->is_default)->toBeFalse()
        ->and(Gateway::query()->where('is_default', true)->count())->toBe(1);
});

it('retires a gateway without losing what it answered', function () {
    $gateway = Gateway::factory()->create();
    $entry = BacklogEntry::factory()->create(['gateway_id' => $gateway->id]);

    $this->delete(route('gateways.destroy', $gateway))->assertRedirect(route('gateways'));

    expect(Gateway::query()->whereKey($gateway->id)->exists())->toBeFalse()
        ->and($entry->fresh()->gateway->name)->toBe($gateway->name);
});

it('refuses to retire the default', function () {
    $gateway = Gateway::default();

    $this->delete(route('gateways.destroy', $gateway))
        ->assertRedirect(route('gateways'))
        ->assertSessionHas('error');

    expect(Gateway::query()->whereKey($gateway->id)->exists())->toBeTrue();
});

it('makes the very first gateway the default on its own', function () {
    Gateway::query()->forceDelete();

    $this->post(route('gateways.store'), gatewayAttributes());

    expect(Gateway::default()?->name)->toBe('Ollama box');
});

it('is switched off with the rest of the dashboard', function () {
    config(['gateway.dashboard.token' => '']);

    $this->post(route('gateways.store'), gatewayAttributes())->assertNotFound();
    $this->get(route('gateways'))->assertNotFound();

    expect(Gateway::query()->where('name', 'Ollama box')->exists())->toBeFalse();
});

it('refuses a wrong dashboard token', function () {
    config(['gateway.dashboard.token' => 'the-real-token']);

    $this->post(route('gateways.store'), gatewayAttributes())->assertForbidden();

    expect(Gateway::query()->where('name', 'Ollama box')->exists())->toBeFalse();
});

it('renders a blank create form seeded from the upstream ceiling', function () {
    $this->get(route('gateways.create'))
        ->assertOk()
        ->assertSee('New gateway')
        ->assertSee('Create gateway')
        ->assertSee((string) config('gateway.upstream.max_timeout'));
});

it('renders the edit form filled in with the gateway', function () {
    $gateway = Gateway::factory()->ollama()->create(['name' => 'Edit me']);

    $this->get(route('gateways.edit', $gateway))
        ->assertOk()
        ->assertSee('Edit gateway')
        ->assertSee('Edit me')
        ->assertSee($gateway->base_url)
        ->assertSee($gateway->callerBaseUrl());
});
