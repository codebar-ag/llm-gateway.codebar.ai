<?php

declare(strict_types=1);

use App\Enums\GatewayProtocol;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

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
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Gateways/Index')
            ->where('gateways.0.name', $gateway->name)
            ->where('gateways.0.shortId', substr($gateway->id, 0, 8))
            ->where('gateways.0.callerBaseUrl', $gateway->callerBaseUrl()));
});

it('answers default and active per row', function () {
    $default = Gateway::default();
    Gateway::factory()->ollama()->create(['name' => 'Sidelined', 'is_active' => false]);

    $rows = collect(inertiaProps($this->get(route('gateways'))->assertOk())['gateways'])
        ->keyBy('name');

    expect($rows[$default->name]['isDefault'])->toBeTrue()
        ->and($rows[$default->name]['isActive'])->toBeTrue()
        ->and($rows['Sidelined']['isDefault'])->toBeFalse()
        ->and($rows['Sidelined']['isActive'])->toBeFalse()
        ->and($rows['Sidelined']['protocol']['label'])->toBe('Ollama');
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

it('promotes a gateway to default from the edit form', function () {
    $previous = Gateway::default();
    $gateway = Gateway::factory()->create();

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['is_default' => '1']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_default)->toBeTrue()
        ->and($previous->fresh()->is_default)->toBeFalse()
        ->and(Gateway::query()->where('is_default', true)->count())->toBe(1);
});

it('promotes a gateway to default as it is created', function () {
    $previous = Gateway::default();

    $this->post(route('gateways.store'), gatewayAttributes(['is_default' => '1']))
        ->assertRedirect(route('gateways'));

    expect(Gateway::default()?->name)->toBe('Ollama box')
        ->and($previous->fresh()->is_default)->toBeFalse()
        ->and(Gateway::query()->where('is_default', true)->count())->toBe(1);
});

it('leaves the default alone when the box is unticked', function () {
    $gateway = Gateway::factory()->create();

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['is_default' => '0']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_default)->toBeFalse()
        ->and(Gateway::default()?->name)->toBe('Default');
});

it('cannot demote the gateway that holds the default', function () {
    $gateway = Gateway::default();

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['is_default' => '0']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_default)->toBeTrue()
        ->and(Gateway::query()->where('is_default', true)->count())->toBe(1);
});

it('disables the default box on the gateway already holding it', function () {
    $default = Gateway::default();
    $other = Gateway::factory()->create();

    // `isDefault` is what disables the box, so assert the flag the page binds
    // rather than the markup it happens to produce.
    $this->get(route('gateways.edit', $default))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('gateway.isDefault', true));

    $this->get(route('gateways.edit', $other))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('gateway.isDefault', false));
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

it('still requires a session when no dashboard token is configured', function () {
    config(['gateway.dashboard.token' => '']);

    // The shared beforeEach logs an operator in; this asserts what is left
    // guarding the gateway table once both that session and the token are
    // gone. Writing here decides where every request in the app is sent.
    Auth::logout();

    $this->post(route('gateways.store'), gatewayAttributes())->assertRedirect(route('login'));

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
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Gateways/Form')
            ->where('editing', false)
            ->where('gateway.name', '')
            ->where('gateway.timeout', config('gateway.upstream.max_timeout'))
            ->where('maxTimeout', config('gateway.upstream.max_timeout'))
            ->where('urls.submit', route('gateways.store')));
});

it('renders the edit form filled in with the gateway', function () {
    $gateway = Gateway::factory()->ollama()->create(['name' => 'Edit me']);

    $this->get(route('gateways.edit', $gateway))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Gateways/Form')
            ->where('editing', true)
            ->where('gateway.name', 'Edit me')
            ->where('gateway.baseUrl', $gateway->base_url)
            ->where('gateway.callerBaseUrl', $gateway->callerBaseUrl())
            ->where('urls.submit', route('gateways.update', $gateway)));
});

/*
 | The default gateway has to stay usable, not merely exist. Routing reads
 | `Gateway::activeDefault()`, which wants both flags — so an inactive default
 | takes the root API down exactly as a missing one would. Retiring the default
 | was already refused; these cover the two ways the same thing used to happen
 | quietly, through the edit form and through the promote button.
 */

it('refuses to deactivate the gateway holding the default', function () {
    $gateway = Gateway::default();

    $this->put(route('gateways.update', $gateway), gatewayAttributes([
        'name' => $gateway->name,
        'is_active' => '0',
    ]))->assertSessionHasErrors('is_active');

    expect($gateway->fresh()->is_active)->toBeTrue();
});

it('rolls the whole edit back when the deactivation is refused', function () {
    $gateway = Gateway::default();

    $this->put(route('gateways.update', $gateway), gatewayAttributes([
        'name' => 'Renamed as well',
        'is_active' => '0',
    ]))->assertSessionHasErrors('is_active');

    // The rename rode along in the same request; refusing one field must not
    // leave the other applied.
    expect($gateway->fresh()->name)->toBe($gateway->name);
});

it('still deactivates a gateway that is not the default', function () {
    $gateway = Gateway::factory()->create(['is_active' => true]);

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['is_active' => '0']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_active)->toBeFalse();
});

it('activates an inactive gateway as it is promoted from the index', function () {
    $gateway = Gateway::factory()->create(['is_active' => false]);

    $this->post(route('gateways.default', $gateway))->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_default)->toBeTrue()
        ->and($gateway->fresh()->is_active)->toBeTrue();
});

it('keeps routing working after an inactive gateway is promoted', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $gateway = Gateway::factory()->create(['is_active' => false, 'base_url' => 'http://promoted.test']);

    $this->post(route('gateways.default', $gateway));

    // The point of activating on promotion: a request with no gateway id has
    // somewhere to go rather than the 503 nobody asked for.
    $this->postJson('/v1/chat/completions', ['model' => 'x'], gatewayAuth())->assertStatus(202);
});

it('activates an inactive gateway promoted from the edit form', function () {
    $gateway = Gateway::factory()->create(['is_active' => false]);

    $this->put(route('gateways.update', $gateway), gatewayAttributes(['is_default' => '1']))
        ->assertRedirect(route('gateways'));

    expect($gateway->fresh()->is_default)->toBeTrue()
        ->and($gateway->fresh()->is_active)->toBeTrue();
});

it('refuses an edit that promotes and deactivates in the same breath', function () {
    $gateway = Gateway::factory()->create(['is_active' => true]);

    $this->put(route('gateways.update', $gateway), gatewayAttributes([
        'is_default' => '1',
        'is_active' => '0',
    ]))->assertSessionHasErrors('is_active');

    expect($gateway->fresh()->is_default)->toBeFalse();
});

it('explains that a retired gateway is still holding a name', function () {
    $gateway = Gateway::factory()->create(['name' => 'Ollama box']);
    $gateway->delete();

    /*
     | Retiring is a soft delete and the unique index spans the retired rows, so
     | the name really is taken — deliberately, since that gateway keeps being
     | named against every entry it answered. The operator cannot see the row
     | holding it, so the message has to say why rather than leaving them
     | hunting through the list for a gateway that is not in it.
     */
    $this->post(route('gateways.store'), gatewayAttributes(['name' => 'Ollama box']))
        ->assertSessionHasErrors(['name' => 'Another gateway already has that name. Retired gateways keep theirs too, '
            .'so the entries they answered stay attributable — pick a different one.']);
});
