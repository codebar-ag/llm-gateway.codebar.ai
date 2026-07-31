<?php

declare(strict_types=1);

use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use Carbon\CarbonImmutable;

/**
 * Queues entries for a caller hash, so it shows up in the callers list — the
 * page is driven by what the backlog has seen, not by the labels table.
 *
 * `$seenAt` lets a test pin `created_at` so `last_seen` (max of it) produces
 * a deterministic row order, since the page sorts most-recently-active first.
 */
function queuedFor(?string $hash, int $count = 1, ?CarbonImmutable $seenAt = null): void
{
    BacklogEntry::factory()->count($count)->create([
        'api_key_hash' => $hash,
        ...($seenAt !== null ? ['created_at' => $seenAt] : []),
    ]);
}

/*
 | Pest's browser plugin only treats a string as a raw CSS/Playwright locator
 | when it contains a character from a fixed "special char" set (#.[]>+~:*|^,()).
 | A bare descendant combinator like "tbody tr" has none of those, so it would
 | silently fall through to an id/name/text lookup instead — finding nothing.
 | "tbody > tr" contains ">" and is unambiguously treated as CSS.
 */

it('renders the callers page with the explanatory copy and table headers', function () {
    [$page] = signIn();

    $page->navigate('/callers')
        ->assertSee('Callers')
        ->assertSee('Every key the backlog has seen, identified by the SHA-256 of the bearer token')
        ->assertSee('Caller')
        ->assertSee('Name')
        ->assertSee('Entries')
        ->assertSee('First seen')
        ->assertSee('Last seen');
});

it('shows an empty state when no caller has queued anything yet', function () {
    [$page] = signIn();

    $page->navigate('/callers')
        ->assertSee('No callers yet')
        ->assertSee('A caller appears here once it has queued something.');
});

it('lists a named and an unnamed caller with their hash, name, and unnamed placeholder', function () {
    $namedHash = str_repeat('a', 64);
    $unnamedHash = str_repeat('b', 64);

    queuedFor($namedHash, 3);
    queuedFor($unnamedHash);
    ApiKeyLabel::factory()->create(['api_key_hash' => $namedHash, 'name' => 'Nightly batch']);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertCount('tbody > tr', 2)
        ->assertSee('Nightly batch')
        ->assertSee('unnamed')
        ->assertSee(substr($namedHash, 0, 12).'…')
        ->assertSee(substr($unnamedHash, 0, 12).'…');
});

it('shows the anonymous bucket as a single shared row with a no key badge instead of a hash', function () {
    // Two separate keyless entries still collapse into one row: there is no
    // per-caller identity to key on once the bearer token is absent.
    queuedFor(null, 2);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertCount('tbody > tr', 1)
        ->assertSeeIn('tbody > tr', 'no key');
});

it('names the shared anonymous row through the sentinel, and it stays a single row', function () {
    queuedFor(null, 3);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertSee('no key')
        ->click('button:text-is("Name")')
        ->assertValue('input[name="name"]', '')
        ->fill('name', 'Unkeyed batch')
        ->click('button[type="submit"]')
        ->assertSee('Unkeyed batch')
        ->assertCount('tbody > tr', 1);

    expect(ApiKeyLabel::query()->sole())
        ->api_key_hash->toBe(ApiKeyLabel::ANONYMOUS)
        ->name->toBe('Unkeyed batch');
});

it('names an unnamed caller through the inline edit form, and closes it on success', function () {
    $hash = str_repeat('c', 64);
    queuedFor($hash);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertSee('unnamed')
        ->assertMissing('input[name="name"]')
        ->click('button:text-is("Name")')
        ->assertValue('input[name="name"]', '')
        ->fill('name', 'Nightly batch')
        ->click('button[type="submit"]')
        ->assertSee('Nightly batch')
        ->assertDontSee('unnamed')
        ->assertMissing('input[name="name"]')
        // The redirect flashes a success message through the Dashboard layout.
        ->assertSee('Caller named.');

    expect(ApiKeyLabel::query()->where('api_key_hash', $hash)->sole()->name)->toBe('Nightly batch');
});

it('renames a named caller, prefilling the edit form with its current name', function () {
    $hash = str_repeat('d', 64);
    queuedFor($hash);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Old name']);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertSee('Old name')
        ->click('button:text-is("Rename")')
        ->assertValue('input[name="name"]', 'Old name')
        ->fill('name', 'New name')
        ->click('button[type="submit"]')
        ->assertSee('New name')
        ->assertDontSee('Old name')
        ->assertMissing('input[name="name"]');

    expect(ApiKeyLabel::query()->where('api_key_hash', $hash)->sole()->name)->toBe('New name');
});

it('cancels the edit form without submitting or persisting the typed value', function () {
    $hash = str_repeat('e', 64);
    queuedFor($hash);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Keep me']);

    [$page] = signIn();

    $page->navigate('/callers')
        ->click('button:text-is("Rename")')
        ->fill('name', 'Should not save')
        ->click('button:text-is("Cancel")')
        ->assertMissing('input[name="name"]')
        ->assertSee('Keep me')
        ->assertDontSee('Should not save');

    expect(ApiKeyLabel::query()->where('api_key_hash', $hash)->sole()->name)->toBe('Keep me');
});

it('only keeps one row in edit mode at a time', function () {
    $hashA = str_repeat('a', 64);
    $hashB = str_repeat('b', 64);

    // A is older, B is more recently active, so the page (sorted by last_seen
    // desc) places B first and A second.
    queuedFor($hashA, 1, now()->subMinute()->toImmutable());
    queuedFor($hashB, 1, now()->toImmutable());
    ApiKeyLabel::factory()->create(['api_key_hash' => $hashA, 'name' => 'Caller A']);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hashB, 'name' => 'Caller B']);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertCount('tbody > tr', 2)
        ->click('tbody > tr:nth-child(2) button:text-is("Rename")')
        ->assertCount('input[name="name"]', 1)
        ->assertValue('input[name="name"]', 'Caller A')
        // Opening row 1's edit form must close row 2's — only one at a time.
        ->click('tbody > tr:nth-child(1) button:text-is("Rename")')
        ->assertCount('input[name="name"]', 1)
        ->assertValue('input[name="name"]', 'Caller B');
});

it('only shows the forget button on named rows, never on unnamed or anonymous ones', function () {
    $namedHash = str_repeat('f', 64);
    $unnamedHash = str_repeat('1', 64);

    queuedFor($namedHash);
    queuedFor($unnamedHash);
    queuedFor(null);
    ApiKeyLabel::factory()->create(['api_key_hash' => $namedHash, 'name' => 'Has a name']);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertCount('tbody > tr', 3)
        ->assertCount('button:text-is("Forget")', 1);
});

it('forgets a name immediately with no confirmation dialog, leaving the entries untouched', function () {
    $hash = str_repeat('9', 64);
    queuedFor($hash, 2);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Doomed']);

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertSee('Doomed')
        ->assertVisible('button:text-is("Forget")')
        // No modal/confirm step exists — the very next state is the deletion.
        ->click('button:text-is("Forget")')
        ->assertDontSee('Doomed')
        ->assertSee('unnamed')
        ->assertMissing('button:text-is("Forget")')
        ->assertSee('Name removed. The caller is shown by its hash again.');

    expect(ApiKeyLabel::query()->where('api_key_hash', $hash)->count())->toBe(0);
    expect(BacklogEntry::query()->where('api_key_hash', $hash)->count())->toBe(2);
});

it('surfaces a duplicate name through the page-level paragraph, not an inline field error', function () {
    $takenHash = str_repeat('a', 64);
    $renamingHash = str_repeat('b', 64);

    queuedFor($takenHash);
    queuedFor($renamingHash);
    ApiKeyLabel::factory()->create(['api_key_hash' => $takenHash, 'name' => 'Taken']);

    [$page] = signIn();

    $page->navigate('/callers')
        // This page never wraps the rename form in <Field>, so there is no
        // role="alert" error paragraph anywhere on it to begin with.
        ->assertMissing('p[role="alert"]')
        ->click('button:text-is("Name")')
        ->fill('name', 'Taken')
        ->click('button[type="submit"]')
        ->assertSeeIn('p.text-danger', 'Another caller already goes by that name.')
        ->assertMissing('p[role="alert"]');

    expect(ApiKeyLabel::query()->count())->toBe(1);
});

it('navigates to the backlog filtered by this callers hash via the entries link', function () {
    $hash = str_repeat('a', 64);
    queuedFor($hash);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Nightly batch']);

    [$page] = signIn();

    $page->navigate('/callers')
        ->click('button:text-is("Entries")')
        ->assertPathIs('/')
        ->assertQueryStringHas('caller', $hash)
        ->assertSee('Nightly batch');
});

it('never paginates the callers table no matter how many rows exist', function () {
    foreach (range(1, 15) as $i) {
        queuedFor(hash('sha256', "caller-{$i}"));
    }

    [$page] = signIn();

    $page->navigate('/callers')
        ->assertCount('tbody > tr', 15)
        ->assertDontSee('Previous')
        ->assertDontSee('Next');
});
