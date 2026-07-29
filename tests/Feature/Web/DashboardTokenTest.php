<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use App\Models\BacklogEntry;
use App\Models\User;

beforeEach(fn () => config()->set('gateway.dashboard.token', dashboardToken()));

/*
 | The token is the outer of two guards. These tests hold a session throughout
 | wherever they expect to get past it, so what they assert is the token's own
 | behaviour rather than the login's — that is Feature/Auth/LoginTest.
 */

it('refuses the backlog without a token', function () {
    $this->get('/')->assertForbidden();
});

it('refuses an entry without a token', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get(route('backlog.entry', $entry->id))->assertForbidden();
});

it('refuses a wrong token', function () {
    $this->get('/?token=not-the-token')->assertForbidden();
});

it('never leaks a prompt to an unauthenticated visitor', function () {
    BacklogEntry::factory()->create([
        'id' => 'resp_secret1',
        'request' => ['model' => 'test', 'messages' => [['role' => 'user', 'content' => 'invoice total']]],
    ]);

    $this->get('/entries/resp_secret1')
        ->assertForbidden()
        ->assertDontSee('invoice total');
});

/*
 | The two below reverse what this middleware used to do. An empty token once
 | 404'd the dashboard, on the grounds that it would otherwise stand open. It
 | no longer stands open — a login is required either way — so an empty token
 | now means "no second secret", not "no dashboard".
 */

it('falls back to the login alone when no token is configured', function () {
    config()->set('gateway.dashboard.token', '');

    $this->get('/')->assertRedirect(route('login'));
});

it('still requires a session when no token is configured', function () {
    config()->set('gateway.dashboard.token', '');

    $this->actingAs(User::factory()->create())->get('/')->assertOk();
});

it('trades a token in the query for a cookie', function () {
    $this->get('/?token='.dashboardToken())
        ->assertRedirect('/')
        ->assertCookie(VerifyDashboardToken::COOKIE, dashboardToken());
});

it('keeps the other filters when it strips the token', function () {
    $this->get('/?status=failed&token='.dashboardToken().'&q=resp_')
        ->assertRedirect('/?status=failed&q=resp_');
});

it('strips the token from an entry url too', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->get(route('backlog.entry', $entry->id).'?token='.dashboardToken())
        ->assertRedirect('/entries/'.$entry->id);
});

it('lets the remembered cookie through to the login', function () {
    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->get('/')
        ->assertRedirect(route('login'));
});

it('opens the dashboard with both the cookie and a session', function () {
    $this->actingAs(User::factory()->create())
        ->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->get('/')
        ->assertOk();
});

it('accepts the token as a bearer token for scripted checks', function () {
    $this->actingAs(User::factory()->create())
        ->withHeader('Authorization', 'Bearer '.dashboardToken())
        ->get('/')
        ->assertOk();
});

it('does not let a caller api key open the dashboard', function () {
    $this->withHeaders(gatewayAuth())->get('/')->assertForbidden();
});

it('guards the login form itself when a token is configured', function () {
    $this->get(route('login'))->assertForbidden();

    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->get(route('login'))
        ->assertOk();
});

it('leaves the api reachable with the dashboard token unset', function () {
    config()->set('gateway.dashboard.token', '');

    $this->getJson('/v1/responses')->assertOk();
    $this->get('/up')->assertOk();
});

it('never redirects an api caller to the login', function () {
    $this->getJson('/v1/responses')->assertOk();

    $this->getJson('/v1/responses/resp_missing')->assertNotFound();
});
