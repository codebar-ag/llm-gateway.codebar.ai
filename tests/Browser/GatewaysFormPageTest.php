<?php

declare(strict_types=1);

use App\Enums\GatewayProtocol;
use App\Models\Gateway;

/**
 * The gateway the migration always seeds, holding `is_default = true`.
 *
 * Fetched by the flag rather than assumed to be named "Default" — a browser
 * test should not depend on seed naming, only on the invariant that exactly
 * one row holds the flag at any time.
 */
function currentDefaultGateway(): Gateway
{
    return Gateway::query()->where('is_default', true)->sole();
}

it('renders the create form with server-computed defaults and no caller url footer', function () {
    [$page] = signIn();

    $maxTimeout = config('gateway.upstream.max_timeout');
    $connectTimeout = config('gateway.upstream.connect_timeout');

    $page->navigate(route('gateways.create'))
        ->assertSee('New gateway')
        ->assertSee('Back to gateways')
        ->assertSelected('select[name="protocol"]', GatewayProtocol::OpenAi->value)
        ->assertValue('input[name="name"]', '')
        ->assertValue('input[name="base_url"]', '')
        ->assertValue('input[name="timeout"]', (string) $maxTimeout)
        ->assertValue('input[name="connect_timeout"]', (string) $connectTimeout)
        ->assertChecked('input[name="is_active"]')
        ->assertNotChecked('input[name="is_default"]')
        ->assertEnabled('input[name="is_default"]')
        ->assertAttribute('input[name="path"]', 'placeholder', GatewayProtocol::OpenAi->defaultPath())
        ->assertSeeIn('button[type="submit"]', 'Create gateway')
        // Create mode: the gateway does not exist yet, so `callerBaseUrl` is
        // null — the footer paragraph must be entirely absent, not merely
        // showing an empty/null value.
        ->assertDontSee('Callers reach this gateway at');
});

it('creates a gateway with every field filled and lands back on the index with a flash message', function () {
    [$page] = signIn();

    $page->navigate(route('gateways.create'))
        ->fill('name', 'Browser Test Gateway')
        ->select('protocol', GatewayProtocol::Anthropic->value)
        ->fill('base_url', 'https://browser-test.example.com')
        ->fill('path', '/custom/v2/messages')
        ->fill('default_model', 'claude-3-opus')
        ->fill('timeout', '120')
        ->fill('connect_timeout', '15')
        ->fill('description', 'Created via the browser test suite.')
        ->uncheck('is_active')
        ->click('button[type="submit"]')
        ->assertPathIs('/gateways')
        ->assertSee('Browser Test Gateway')
        ->assertSee('created.');

    $gateway = Gateway::query()->where('name', 'Browser Test Gateway')->sole();

    expect($gateway->protocol)->toBe(GatewayProtocol::Anthropic)
        ->and($gateway->base_url)->toBe('https://browser-test.example.com')
        ->and($gateway->path)->toBe('/custom/v2/messages')
        ->and($gateway->default_model)->toBe('claude-3-opus')
        ->and($gateway->timeout)->toBe(120)
        ->and($gateway->connect_timeout)->toBe(15)
        ->and($gateway->description)->toBe('Created via the browser test suite.')
        ->and($gateway->is_active)->toBeFalse()
        ->and($gateway->is_default)->toBeFalse();
});

it('prefills the edit form including the caller base url footer and copy button', function () {
    $gateway = Gateway::factory()->ollama()->create([
        'name' => 'Prefill Target',
        'base_url' => 'http://ollama.internal:11434',
        'path' => '/api/custom-chat',
        'default_model' => 'llama3.2:3b',
        'timeout' => 300,
        'connect_timeout' => 8,
        'description' => 'Existing description text.',
        'is_active' => false,
        'is_default' => false,
    ]);

    [$page] = signIn();

    $page->navigate(route('gateways.edit', $gateway))
        ->assertSee('Edit gateway')
        ->assertValue('input[name="name"]', 'Prefill Target')
        ->assertSelected('select[name="protocol"]', GatewayProtocol::Ollama->value)
        ->assertValue('input[name="base_url"]', 'http://ollama.internal:11434')
        ->assertValue('input[name="path"]', '/api/custom-chat')
        ->assertValue('input[name="default_model"]', 'llama3.2:3b')
        ->assertValue('input[name="timeout"]', '300')
        ->assertValue('input[name="connect_timeout"]', '8')
        ->assertValue('textarea[name="description"]', 'Existing description text.')
        ->assertNotChecked('input[name="is_active"]')
        ->assertNotChecked('input[name="is_default"]')
        ->assertEnabled('input[name="is_default"]')
        ->assertSeeIn('button[type="submit"]', 'Save changes')
        ->assertSee('Callers reach this gateway at')
        ->assertSeeIn('code.font-mono', $gateway->callerBaseUrl())
        ->assertAttribute('button[aria-label="Copy to clipboard"]', 'aria-label', 'Copy to clipboard');
});

it('updates a gateway and the change survives a revisit', function () {
    $gateway = Gateway::factory()->create([
        'name' => 'Original Name',
        'description' => 'Original description.',
    ]);

    [$page] = signIn();

    $page->navigate(route('gateways.edit', $gateway))
        ->fill('name', 'Renamed Gateway')
        ->fill('description', 'Updated description.')
        ->click('button[type="submit"]')
        ->assertPathIs('/gateways')
        ->assertSee('Renamed Gateway')
        ->assertSee('updated.');

    $gateway->refresh();

    expect($gateway->name)->toBe('Renamed Gateway')
        ->and($gateway->description)->toBe('Updated description.');

    // Revisit to prove the change was actually persisted, not just flashed.
    $page->navigate(route('gateways.edit', $gateway))
        ->assertValue('input[name="name"]', 'Renamed Gateway')
        ->assertValue('textarea[name="description"]', 'Updated description.');
});

it('shows the inline field error and the alert summary together without clearing other fields', function () {
    [$page] = signIn();

    $page->navigate(route('gateways.create'))
        // Name deliberately left blank.
        ->fill('base_url', 'https://kept.example.com')
        ->fill('default_model', 'kept-model')
        ->fill('description', 'Kept description.')
        ->click('button[type="submit"]')
        ->assertPathIs('/gateways/create')
        ->assertSee('That did not save')
        ->assertSeeIn('#name-error', 'The name field is required.')
        // Both surfaces show the same message simultaneously.
        ->assertSee('The name field is required.')
        // Nothing else was cleared by the failed round-trip.
        ->assertValue('input[name="base_url"]', 'https://kept.example.com')
        ->assertValue('input[name="default_model"]', 'kept-model')
        ->assertValue('textarea[name="description"]', 'Kept description.');
});

it('disables the is_default checkbox on the current default gateway and leaves it checked', function () {
    $default = currentDefaultGateway();

    [$page] = signIn();

    $page->navigate(route('gateways.edit', $default))
        ->assertChecked('input[name="is_default"]')
        ->assertDisabled('input[name="is_default"]')
        ->assertAttribute('input[name="is_default"]', 'disabled', '');
});

it('enables and allows toggling the is_default checkbox on a non-default gateway', function () {
    $gateway = Gateway::factory()->create(['is_default' => false]);

    [$page] = signIn();

    $page->navigate(route('gateways.edit', $gateway))
        ->assertNotChecked('input[name="is_default"]')
        ->assertEnabled('input[name="is_default"]')
        ->check('is_default')
        ->assertChecked('input[name="is_default"]');
});

it('steps the timeout input with the increase and decrease buttons and clamps at the boundaries', function () {
    $maxTimeout = config('gateway.upstream.max_timeout');

    [$page] = signIn();

    $increase = 'label[for="timeout"] ~ div button[aria-label="Increase"]';
    $decrease = 'label[for="timeout"] ~ div button[aria-label="Decrease"]';

    $page->navigate(route('gateways.create'))
        // Create mode seeds timeout at the ceiling already.
        ->assertValue('input[name="timeout"]', (string) $maxTimeout)
        ->assertAttribute('input[name="timeout"]', 'min', '1')
        ->assertAttribute('input[name="timeout"]', 'max', (string) $maxTimeout)
        // Already at the max — increasing further must clamp, not overflow.
        ->click($increase)
        ->assertValue('input[name="timeout"]', (string) $maxTimeout)
        // One decrease steps down by 1.
        ->click($decrease)
        ->assertValue('input[name="timeout"]', (string) ($maxTimeout - 1))
        // Drive it down to the floor and confirm decreasing further clamps at 1.
        ->fill('timeout', '1')
        ->click($decrease)
        ->assertValue('input[name="timeout"]', '1')
        // From the floor, one increase steps back up by 1.
        ->click($increase)
        ->assertValue('input[name="timeout"]', '2');
});

it('clamps the connect timeout input at its fixed 1-120 boundaries', function () {
    [$page] = signIn();

    $increase = 'label[for="connect_timeout"] ~ div button[aria-label="Increase"]';
    $decrease = 'label[for="connect_timeout"] ~ div button[aria-label="Decrease"]';

    $page->navigate(route('gateways.create'))
        ->assertAttribute('input[name="connect_timeout"]', 'min', '1')
        ->assertAttribute('input[name="connect_timeout"]', 'max', '120')
        ->fill('connect_timeout', '120')
        ->click($increase)
        ->assertValue('input[name="connect_timeout"]', '120')
        ->fill('connect_timeout', '1')
        ->click($decrease)
        ->assertValue('input[name="connect_timeout"]', '1');
});

it('does not update the path override placeholder when the protocol select changes', function () {
    [$page] = signIn();

    $page->navigate(route('gateways.create'))
        ->assertAttribute('input[name="path"]', 'placeholder', GatewayProtocol::OpenAi->defaultPath())
        ->select('protocol', GatewayProtocol::Anthropic->value)
        // `defaultPath` is a static, server-computed prop from the *initial*
        // protocol — it is never recomputed client-side, so the placeholder
        // must stay exactly what it was, even though Anthropic's own default
        // path ("/v1/messages") differs from OpenAI's.
        ->assertAttribute('input[name="path"]', 'placeholder', GatewayProtocol::OpenAi->defaultPath())
        ->select('protocol', GatewayProtocol::Ollama->value)
        ->assertAttribute('input[name="path"]', 'placeholder', GatewayProtocol::OpenAi->defaultPath());
});

it('navigates away via the header and footer links without submitting any data', function () {
    [$page] = signIn();

    $before = Gateway::query()->count();

    $page->navigate(route('gateways.create'))
        ->fill('name', 'Should Not Be Saved')
        ->click('Back to gateways')
        ->assertPathIs('/gateways');

    expect(Gateway::query()->count())->toBe($before)
        ->and(Gateway::query()->where('name', 'Should Not Be Saved')->exists())->toBeFalse();

    $page->navigate(route('gateways.create'))
        ->fill('name', 'Also Should Not Be Saved')
        ->click('Cancel')
        ->assertPathIs('/gateways');

    expect(Gateway::query()->count())->toBe($before)
        ->and(Gateway::query()->where('name', 'Also Should Not Be Saved')->exists())->toBeFalse();
});
