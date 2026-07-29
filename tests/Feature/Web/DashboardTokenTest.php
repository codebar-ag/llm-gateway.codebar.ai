<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use App\Models\BacklogEntry;

beforeEach(fn () => config()->set('gateway.dashboard.token', dashboardToken()));

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

it('hides the dashboard entirely when no token is configured', function () {
    config()->set('gateway.dashboard.token', '');

    $this->get('/')->assertNotFound();
});

it('cannot be opened with an empty token when none is configured', function () {
    config()->set('gateway.dashboard.token', '');

    $this->get('/?token=')->assertNotFound();
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

it('lets the remembered cookie through', function () {
    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->get('/')
        ->assertOk()
        ->assertSee('LLM Gateway');
});

it('accepts the token as a bearer token for scripted checks', function () {
    $this->withHeader('Authorization', 'Bearer '.dashboardToken())
        ->get('/')
        ->assertOk();
});

it('does not let a caller api key open the dashboard', function () {
    $this->withHeaders(gatewayAuth())->get('/')->assertForbidden();
});

it('leaves the api reachable with the dashboard switched off', function () {
    config()->set('gateway.dashboard.token', '');

    $this->getJson('/v1/responses')->assertOk();
    $this->get('/up')->assertOk();
});
