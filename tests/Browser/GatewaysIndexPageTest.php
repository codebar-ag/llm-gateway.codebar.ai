<?php

declare(strict_types=1);

use App\Models\Gateway;
use App\Models\User;
use Database\Factories\UserFactory;
use Pest\Browser\Playwright\Page;

/**
 * Signs a real browser into the dashboard with clipboard permissions granted.
 *
 * `signIn()` in tests/Pest.php does not grant any browser permissions, and
 * clipboard-read needs one explicitly or `navigator.clipboard.readText()`
 * rejects. Permissions are set on the browser context at creation time, so
 * this repeats the login flow instead of reusing `signIn()`.
 *
 * @return array{0: Page, 1: User}
 */
function signInWithClipboardAccess(): array
{
    $user = User::factory()->create();

    $page = visit('/login', ['permissions' => ['clipboard-read', 'clipboard-write']])
        ->fill('email', $user->email)
        ->fill('password', UserFactory::password())
        ->click('button[type="submit"]');

    return [$page, $user];
}

it('always lists the default gateway first, regardless of alphabetical name', function () {
    // "Aardvark" sorts before "Default" alphabetically, so this only passes
    // if the default really is pinned first rather than sorted by name.
    Gateway::factory()->create(['name' => 'Aardvark Gateway']);
    Gateway::factory()->create(['name' => 'Zebra Gateway']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->assertSeeIn('table tbody tr:nth-child(1)', 'Default')
        ->assertSeeIn('table tbody tr:nth-child(2)', 'Aardvark Gateway')
        ->assertSeeIn('table tbody tr:nth-child(3)', 'Zebra Gateway')
        ->assertCount('table tbody > tr', 3);
});

it('hides Make default and Retire on the current default row but shows them elsewhere', function () {
    Gateway::factory()->create(['name' => 'Secondary']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->assertNotPresent('table tbody tr:nth-child(1) button:has-text("Make default")')
        ->assertNotPresent('table tbody tr:nth-child(1) button:has-text("Retire")')
        ->assertPresent('table tbody tr:nth-child(1) a:has-text("Edit")')
        ->assertPresent('table tbody tr:nth-child(2) button:has-text("Make default")')
        ->assertPresent('table tbody tr:nth-child(2) button:has-text("Retire")')
        ->assertPresent('table tbody tr:nth-child(2) a:has-text("Edit")');
});

it('renders isActive as plain text for yes and a danger badge for no', function () {
    Gateway::factory()->inactive()->create(['name' => 'Sidelined']);

    [$page] = signIn();

    $page->navigate('/gateways')
        // Row 1: the active "Default" gateway — plain text, no badge markup.
        ->assertSeeIn('table tbody tr:nth-child(1) td:nth-child(3)', 'yes')
        ->assertNotPresent('table tbody tr:nth-child(1) td:nth-child(3) .text-danger')
        // Row 2: the inactive gateway — a danger Badge, not plain text.
        ->assertSeeIn('table tbody tr:nth-child(2) td:nth-child(3)', 'no')
        ->assertPresent('table tbody tr:nth-child(2) td:nth-child(3) .text-danger');
});

it('links the entries count to the backlog filtered by that gateway', function () {
    $gateway = Gateway::default();

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(1) td:nth-child(8) a')
        ->assertPathIs('/logs')
        ->assertQueryStringHas('gateway', $gateway->id);
});

it('copies the full caller base url, not the truncated display text', function () {
    $gateway = Gateway::default();

    [$page] = signInWithClipboardAccess();

    $page->navigate('/gateways')
        // The visible cell is truncated to a shortId + ellipsis; the full url
        // only lives in the title attribute and whatever the copy button writes.
        ->assertSeeIn('table tbody tr:nth-child(1) td:nth-child(7)', '/'.substr($gateway->id, 0, 8).'…/v1')
        ->assertAttribute('table tbody tr:nth-child(1) td:nth-child(7) code', 'title', $gateway->callerBaseUrl())
        ->click('table tbody tr:nth-child(1) td:nth-child(7) button[aria-label="Copy to clipboard"]')
        ->assertSee('Copied to clipboard');

    expect($page->script('navigator.clipboard.readText()'))->toBe($gateway->callerBaseUrl());
});

it('promotes a gateway to default and swaps the action buttons on both rows', function () {
    Gateway::factory()->create(['name' => 'Secondary']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(2) button:has-text("Make default")')
        ->assertPathIs('/gateways')
        ->assertSee('Requests without a gateway id now go to “Secondary”.')
        ->assertSeeIn('table tbody tr:nth-child(1) td:nth-child(1)', 'Secondary')
        ->assertNotPresent('table tbody tr:nth-child(1) button:has-text("Make default")')
        ->assertNotPresent('table tbody tr:nth-child(1) button:has-text("Retire")')
        ->assertSeeIn('table tbody tr:nth-child(2) td:nth-child(1)', 'Default')
        ->assertPresent('table tbody tr:nth-child(2) button:has-text("Make default")')
        ->assertPresent('table tbody tr:nth-child(2) button:has-text("Retire")');

    expect(Gateway::default()?->name)->toBe('Secondary');
});

it('opens the retire modal with that row’s gateway name interpolated', function () {
    Gateway::factory()->create(['name' => 'Doomed Gateway']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(2) button:has-text("Retire")')
        ->assertVisible('[role="dialog"]')
        ->assertSeeIn('[role="dialog"]', 'Retire this gateway?')
        ->assertSeeIn('[role="dialog"]', 'Queued requests already accepted for “Doomed Gateway” still run.');
});

it('cancels the retire modal without deleting the gateway', function () {
    Gateway::factory()->create(['name' => 'Spared Gateway']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(2) button:has-text("Retire")')
        ->assertVisible('[role="dialog"]')
        ->click('[role="dialog"] button:has-text("Cancel")')
        ->assertMissing('[role="dialog"]')
        ->assertCount('table tbody > tr', 2)
        ->assertSee('Spared Gateway');

    expect(Gateway::query()->where('name', 'Spared Gateway')->exists())->toBeTrue();
});

it('closes the retire modal on Escape without deleting the gateway', function () {
    Gateway::factory()->create(['name' => 'Escape Hatch']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(2) button:has-text("Retire")')
        ->assertVisible('[role="dialog"]')
        ->keys('[role="dialog"] button[aria-label="Close"]', 'Escape')
        ->assertMissing('[role="dialog"]')
        ->assertCount('table tbody > tr', 2);

    expect(Gateway::query()->where('name', 'Escape Hatch')->exists())->toBeTrue();
});

it('closes the retire modal on a backdrop click without deleting the gateway', function () {
    Gateway::factory()->create(['name' => 'Backdrop Survivor']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(2) button:has-text("Retire")')
        ->assertVisible('[role="dialog"]');

    // The scrim sits behind the centered panel, so a real pointer click at its
    // bounding-box center would actually land on the panel. Dispatching the
    // click directly on the backdrop element exercises the same
    // `@click="closeOnBackdrop && close()"` handler a real click reaches.
    $page->script("document.querySelector('[role=dialog] > div.absolute').click();");

    $page->assertMissing('[role="dialog"]')
        ->assertCount('table tbody > tr', 2);

    expect(Gateway::query()->where('name', 'Backdrop Survivor')->exists())->toBeTrue();
});

it('traps focus inside the retire modal and returns it to the trigger button on close', function () {
    Gateway::factory()->create(['name' => 'Focus Test Gateway']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(2) button:has-text("Retire")')
        ->assertVisible('[role="dialog"]')
        // Nothing sets `initialFocus`, so the trap focuses the first focusable
        // element in the panel — the "×" close button, before Cancel/Retire.
        ->assertScript("document.activeElement?.getAttribute('aria-label')", 'Close')
        // Shift+Tab from the first element wraps to the last one (Retire).
        ->keys('[role="dialog"] button[aria-label="Close"]', 'Shift+Tab')
        ->assertScript('document.activeElement?.textContent?.trim()', 'Retire')
        // Tab from the last element wraps back to the first (Close).
        ->keys('[role="dialog"] button:has-text("Retire")', 'Tab')
        ->assertScript("document.activeElement?.getAttribute('aria-label')", 'Close')
        ->click('[role="dialog"] button:has-text("Cancel")')
        ->assertMissing('[role="dialog"]')
        // Focus returns to whatever was focused before the modal opened —
        // the row's own Retire button, which triggered `retiring = row`.
        ->assertScript('document.activeElement?.textContent?.trim()', 'Retire');
});

it('retires a gateway from inside the modal and shows a success flash message', function () {
    Gateway::factory()->create(['name' => 'Retire Me']);

    [$page] = signIn();

    $page->navigate('/gateways')
        ->click('table tbody tr:nth-child(2) button:has-text("Retire")')
        ->assertVisible('[role="dialog"]')
        ->click('[role="dialog"] button:has-text("Retire")')
        ->assertMissing('[role="dialog"]')
        ->assertSee('Gateway “Retire Me” retired. The entries it answered keep pointing at it.')
        ->assertCount('table tbody > tr', 1);

    expect(Gateway::query()->where('name', 'Retire Me')->exists())->toBeFalse();
});

it('shows the empty state when no gateways exist at all', function () {
    // Unreachable through the UI (the current default can never be retired),
    // only by force-deleting every row directly, as the underlying
    // Feature test suite already does for this same edge case.
    Gateway::query()->forceDelete();

    [$page] = signIn();

    $page->navigate('/gateways')
        ->assertSee('No gateways yet')
        ->assertSee('Nothing can be queued until one exists.')
        ->assertCount('table tbody > tr', 1);
});
