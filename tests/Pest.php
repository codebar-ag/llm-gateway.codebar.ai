<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Pest\Browser\Playwright\Page;
use Tests\TestCase;

/*
 | RefreshDatabase applies to Unit too, on purpose: "Unit" here means "does
 | not boot HTTP routing/middleware", not "never touches the database".
 | Several Unit tests exercise BacklogEntry through the real Eloquent model
 | rather than mocking the ORM, which is the more trustworthy of the two —
 | the in-memory sqlite connection keeps them fast regardless.
 */

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit', 'Browser');

/*
 | No test may reach the network. `QUEUE_CONNECTION=sync` runs ProcessBacklogEntry
 | inline the moment a controller queues, so a test that forgets Http::fake() does
 | not skip the upstream call — it makes it, against whatever the seeded gateway
 | points at. This turns that into a failed test instead of real traffic.
 */

uses()->beforeEach(fn () => Http::preventStrayRequests())->in('Feature', 'Unit', 'Browser');

/*
 | RefreshDatabase rolls the database back between tests, but the array cache
 | store lives for the whole process. QueueEstimate caches its per-model
 | measurement, so without this a median measured from one test's entries
 | would be served to the next test that happens to draw the same model out
 | of the factory — passing or failing depending on test order.
 */

uses()->beforeEach(fn () => Cache::flush())->in('Feature', 'Unit', 'Browser');

/*
 | The dashboard has two guards: the shared DASHBOARD_TOKEN and the operator's
 | own session. Every page test gets both, so each guard is asserted in one
 | place — Feature/Web/DashboardTokenTest and Feature/Auth/LoginTest — instead
 | of in every test that happens to open a page.
 */

uses()->beforeEach(function () {
    config()->set('gateway.dashboard.token', dashboardToken());

    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken());
    $this->actingAs(User::factory()->create());
})->in(
    'Feature/Web/ShowBacklogControllerTest.php',
    'Feature/Web/ShowBacklogEntryControllerTest.php',
    'Feature/Web/GatewayCrudTest.php',
);

function dashboardToken(): string
{
    return 'test-dashboard-token';
}

/**
 * Signs a real browser into the dashboard through the actual login form.
 *
 * `actingAs()` sets the guard on the test's own kernel, not on the cookie jar
 * a Playwright-driven browser tab carries — a browser test needs a real
 * session cookie, which only a real form submission produces.
 *
 * @return array{0: Page, 1: User}
 */
function signIn(?User $user = null): array
{
    $user ??= User::factory()->create();

    $page = visit('/login')
        ->fill('email', $user->email)
        ->fill('password', UserFactory::password())
        ->click('button[type="submit"]');

    return [$page, $user];
}

/** @return array<string, string> */
function gatewayAuth(): array
{
    return ['Authorization' => 'Bearer sk-test-caller'];
}

/**
 * The props Inertia handed the page component.
 *
 * Inertia renders the root view with the whole page object, so the props are
 * readable without decoding the `data-page` attribute — and without asserting
 * on JSON-escaped HTML, where every URL slash arrives as `\/`.
 *
 * @return array<string, mixed>
 */
function inertiaProps(TestResponse $response): array
{
    /** @var array{props: array<string, mixed>} $page */
    $page = $response->viewData('page');

    return $page['props'];
}

/**
 * The entry ids a backlog listing came back with, in order.
 *
 * @return array<int, string>
 */
function backlogIds(TestResponse $response): array
{
    /** @var array<int, array{id: string}> $entries */
    $entries = inertiaProps($response)['entries'];

    return array_column($entries, 'id');
}

/**
 * How many statements the database was asked for while $work ran.
 *
 * Shared because two suites measure the same thing — the dashboard proving a
 * partial reload is cheaper than a full one, and QueueEstimate proving the
 * queue-wide counts are paid for once a window rather than once a poll.
 *
 * The query log rather than `DB::listen`: a listener stays registered for the
 * rest of the process, so a second measurement in the same test would still be
 * feeding the first one's tally.
 */
function queriesDuring(Closure $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $work();

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $queries;
}
