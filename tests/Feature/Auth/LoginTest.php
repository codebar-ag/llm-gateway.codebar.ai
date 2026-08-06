<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use App\Models\BacklogEntry;
use App\Models\User;
use Database\Factories\UserFactory;
use Inertia\Testing\AssertableInertia;

/*
 | No token configured, so these exercise the login on its own. That the token
 | also guards this form when one *is* set is asserted in
 | Feature/Web/DashboardTokenTest.
 */

beforeEach(fn () => config()->set('gateway.dashboard.token', ''));

it('renders the login form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('urls.submit', route('login.store')));
});

it('signs an operator in and lands them on the backlog', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => UserFactory::password(),
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('returns to where the visitor was headed', function () {
    $entry = BacklogEntry::factory()->completed()->create();
    $user = User::factory()->create();

    // The redirect the guard issues is what stores the intended url.
    $this->get(route('logs.entry', $entry->id))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => UserFactory::password(),
    ])->assertRedirect(route('logs.entry', $entry->id));
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('says the same thing about an unknown address as about a wrong password', function () {
    $user = User::factory()->create();

    $unknown = $this->post(route('login.store'), [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
    ])->assertSessionHasErrors('email');

    $wrong = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    // Differing messages would turn this form into a way to find out which
    // addresses have accounts.
    expect($unknown->getSession()->get('errors')->first('email'))
        ->toBe($wrong->getSession()->get('errors')->first('email'));
});

it('throttles after five failures and says how long', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => UserFactory::password(),
    ])->assertSessionHasErrors('email');

    // Even the correct password is refused once the budget is spent.
    $this->assertGuest();

    expect($response->getSession()->get('errors')->first('email'))
        ->toContain('Too many login attempts');
});

it('spends the throttle budget on failures only', function () {
    $user = User::factory()->create();

    foreach (range(1, 4) as $ignored) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => UserFactory::password(),
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('requires both fields', function () {
    $response = $this->post(route('login.store'), [])->assertSessionHasErrors(['email', 'password']);

    // Guards against the locale/lang files going missing again, which leaves
    // Laravel unable to resolve the message and fall back to the raw key.
    $errors = $response->getSession()->get('errors');
    expect($errors->first('email'))->toBe('The email field is required.')
        ->and($errors->first('password'))->toBe('The password field is required.');
});

it('signs the operator out again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('sends a guest to the login instead of the backlog', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('gateways'))->assertRedirect(route('login'));
});

it('keeps a signed-in operator away from the login form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

it('regenerates the session id when signing in', function () {
    $user = User::factory()->create();

    $this->get(route('login'));
    $before = session()->getId();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => UserFactory::password(),
    ]);

    // Session fixation: the id a visitor arrived holding must not become the
    // id that carries an authenticated session.
    expect(session()->getId())->not->toBe($before);
});

it('does not let a dashboard session stand in for a caller key', function () {
    config()->set('gateway.api.require_caller_key', true);

    // The API authenticates on the caller's own key, which it replays upstream.
    // An operator session is a different thing entirely and must not be an
    // alternative way in — nor may it ever turn an API call into a redirect to
    // the login, which would hand a JSON client an HTML page.
    $this->actingAs(User::factory()->create())
        ->getJson('/v1/responses')
        ->assertUnauthorized()
        ->assertJsonPath('error.type', 'no_caller_key');
});

it('leaves the api alone for a signed-in operator', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/v1/responses')
        ->assertOk();
});

it('never shows a prompt to a guest', function () {
    BacklogEntry::factory()->create([
        'id' => 'resp_secret2',
        'request' => ['model' => 'test', 'messages' => [['role' => 'user', 'content' => 'salary review']]],
    ]);

    $this->get('/entries/resp_secret2')
        ->assertRedirect(route('login'))
        ->assertDontSee('salary review');
});

it('is reachable through the token guard once one is configured', function () {
    $user = User::factory()->create();
    config()->set('gateway.dashboard.token', dashboardToken());

    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::password(),
        ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
