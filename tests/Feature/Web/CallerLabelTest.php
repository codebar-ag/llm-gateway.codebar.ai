<?php

declare(strict_types=1);

use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config()->set('gateway.dashboard.token', '');
    $this->actingAs(User::factory()->create());
});

/** A caller only exists because it queued something, so entries come first. */
function entriesFrom(?string $hash, int $count = 1): void
{
    BacklogEntry::factory()->count($count)->create(['api_key_hash' => $hash]);
}

it('lists every caller the backlog has seen', function () {
    entriesFrom(str_repeat('a', 64), 3);
    entriesFrom(str_repeat('b', 64));

    $callers = collect(inertiaProps($this->get(route('callers'))->assertOk())['callers']);

    expect($callers)->toHaveCount(2)
        ->and($callers->firstWhere('hash', str_repeat('a', 64))['entries'])->toBe(3);
});

it('renders the callers page', function () {
    entriesFrom(str_repeat('a', 64));

    $this->get(route('callers'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Callers')
            ->where('urls.store', route('callers.store')));
});

it('shows a caller that sent no key as anonymous', function () {
    entriesFrom(null);

    $caller = collect(inertiaProps($this->get(route('callers'))->assertOk())['callers'])->sole();

    expect($caller['isAnonymous'])->toBeTrue()
        ->and($caller['hash'])->toBe(ApiKeyLabel::ANONYMOUS)
        ->and($caller['shortHash'])->toBeNull();
});

it('names a caller', function () {
    $hash = str_repeat('a', 64);
    entriesFrom($hash);

    $this->post(route('callers.store'), ['api_key_hash' => $hash, 'name' => 'Nightly batch'])
        ->assertRedirect(route('callers'));

    expect(ApiKeyLabel::query()->where('api_key_hash', $hash)->sole()->name)->toBe('Nightly batch');
});

it('renames a caller instead of adding a second label', function () {
    $hash = str_repeat('a', 64);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Old name']);

    $this->post(route('callers.store'), ['api_key_hash' => $hash, 'name' => 'New name'])
        ->assertRedirect(route('callers'));

    expect(ApiKeyLabel::query()->where('api_key_hash', $hash)->count())->toBe(1)
        ->and(ApiKeyLabel::query()->where('api_key_hash', $hash)->sole()->name)->toBe('New name');
});

it('lets a caller keep its own name when renamed to the same thing', function () {
    $hash = str_repeat('a', 64);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Same']);

    $this->post(route('callers.store'), ['api_key_hash' => $hash, 'name' => 'Same'])
        ->assertRedirect(route('callers'));
});

it('refuses a name another caller already uses', function () {
    ApiKeyLabel::factory()->create(['api_key_hash' => str_repeat('a', 64), 'name' => 'Taken']);

    $this->post(route('callers.store'), ['api_key_hash' => str_repeat('b', 64), 'name' => 'Taken'])
        ->assertSessionHasErrors('name');

    expect(ApiKeyLabel::query()->count())->toBe(1);
});

it('names the keyless bucket through its sentinel', function () {
    entriesFrom(null);

    $this->post(route('callers.store'), ['api_key_hash' => ApiKeyLabel::ANONYMOUS, 'name' => 'Unkeyed'])
        ->assertRedirect(route('callers'));

    expect(ApiKeyLabel::query()->sole()->api_key_hash)->toBe(ApiKeyLabel::ANONYMOUS);
});

it('refuses anything that is not a hash or the sentinel', function (string $hash) {
    $this->post(route('callers.store'), ['api_key_hash' => $hash, 'name' => 'Whatever'])
        ->assertSessionHasErrors('api_key_hash');

    expect(ApiKeyLabel::query()->count())->toBe(0);
})->with([
    // Each is the right length except where the name says otherwise, so what
    // fails is the character class rather than a length check standing in for it.
    'too short' => 'abc',
    'one over' => str_repeat('a', 65),
    'non-hex letter' => str_repeat('z', 64),
    'uppercase hex' => str_repeat('A', 64),
    'sql-ish' => "' or 1=1--",
    'nearly the sentinel' => 'anonymouse',
]);

it('accepts the sentinel with padding, because the framework trims it first', function () {
    $this->post(route('callers.store'), ['api_key_hash' => ' anonymous ', 'name' => 'Unkeyed'])
        ->assertRedirect(route('callers'));

    // TrimStrings runs before validation, so the stored value is the sentinel
    // itself — not a second, whitespace-padded row that would never match.
    expect(ApiKeyLabel::query()->sole()->api_key_hash)->toBe(ApiKeyLabel::ANONYMOUS);
});

it('forgets a name without touching the entries', function () {
    $hash = str_repeat('a', 64);
    entriesFrom($hash, 2);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Doomed']);

    $this->delete(route('callers.destroy', ['hash' => $hash]))->assertRedirect(route('callers'));

    expect(ApiKeyLabel::query()->count())->toBe(0)
        ->and(BacklogEntry::query()->where('api_key_hash', $hash)->count())->toBe(2);
});

it('filters the backlog by caller', function () {
    $mine = str_repeat('a', 64);
    entriesFrom($mine, 2);
    entriesFrom(str_repeat('b', 64), 3);

    $ids = backlogIds($this->get(route('logs', ['caller' => $mine]))->assertOk());

    expect($ids)->toHaveCount(2);
});

it('filters the backlog down to the keyless callers', function () {
    entriesFrom(null, 2);
    entriesFrom(str_repeat('a', 64), 3);

    $ids = backlogIds($this->get(route('logs', ['caller' => ApiKeyLabel::ANONYMOUS]))->assertOk());

    expect($ids)->toHaveCount(2);
});

it('shows the label in the backlog once the caller is named', function () {
    $hash = str_repeat('a', 64);
    entriesFrom($hash);
    ApiKeyLabel::factory()->create(['api_key_hash' => $hash, 'name' => 'Nightly batch']);

    $row = collect(inertiaProps($this->get(route('logs'))->assertOk())['entries'])->sole();

    expect($row['caller']['label'])->toBe('Nightly batch')
        ->and($row['caller']['named'])->toBeTrue();
});

it('shows a shortened hash for a caller nobody named', function () {
    $hash = str_repeat('a', 64);
    entriesFrom($hash);

    $row = collect(inertiaProps($this->get(route('logs'))->assertOk())['entries'])->sole();

    // The filter value stays the whole hash, so a bookmarked URL survives the
    // caller later being named or the name being removed.
    expect($row['caller']['label'])->toBe(substr($hash, 0, 12))
        ->and($row['caller']['named'])->toBeFalse()
        ->and($row['caller']['value'])->toBe($hash);
});

it('offers named callers before unnamed ones in the filter', function () {
    entriesFrom(str_repeat('a', 64));
    entriesFrom(str_repeat('b', 64));
    ApiKeyLabel::factory()->create(['api_key_hash' => str_repeat('b', 64), 'name' => 'Named one']);

    $callers = inertiaProps($this->get(route('logs'))->assertOk())['callers'];

    expect($callers[0]['label'])->toBe('Named one');
});

it('keeps the callers page behind both guards', function () {
    config()->set('gateway.dashboard.token', dashboardToken());

    // Signed in, but no token: still refused, because the token runs first.
    $this->get(route('callers'))->assertForbidden();
    $this->post(route('callers.store'), ['api_key_hash' => str_repeat('a', 64), 'name' => 'x'])
        ->assertForbidden();
});

it('sends a guest on the callers page to the login', function () {
    Auth::logout();

    $this->get(route('callers'))->assertRedirect(route('login'));
});
