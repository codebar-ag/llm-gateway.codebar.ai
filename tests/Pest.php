<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
 | RefreshDatabase applies to Unit too, on purpose: "Unit" here means "does
 | not boot HTTP routing/middleware", not "never touches the database".
 | Several Unit tests exercise BacklogEntry through the real Eloquent model
 | rather than mocking the ORM, which is the more trustworthy of the two —
 | the in-memory sqlite connection keeps them fast regardless.
 */

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');

/*
 | No test may reach the network. `QUEUE_CONNECTION=sync` runs ProcessBacklogEntry
 | inline the moment a controller queues, so a test that forgets Http::fake() does
 | not skip the upstream call — it makes it, against whatever the seeded gateway
 | points at. This turns that into a failed test instead of real traffic.
 */

uses()->beforeEach(fn () => Http::preventStrayRequests())->in('Feature', 'Unit');

/*
 | RefreshDatabase rolls the database back between tests, but the array cache
 | store lives for the whole process. QueueEstimate caches its per-model
 | measurement, so without this a median measured from one test's entries
 | would be served to the next test that happens to draw the same model out
 | of the factory — passing or failing depending on test order.
 */

uses()->beforeEach(fn () => Cache::flush())->in('Feature', 'Unit');

/*
 | The dashboard is guarded by DASHBOARD_TOKEN. Every page test gets a
 | configured token and the cookie that goes with it, so the guard itself is
 | asserted in one place (Feature/Web/DashboardTokenTest) instead of everywhere.
 */

uses()->beforeEach(function () {
    config()->set('gateway.dashboard.token', dashboardToken());

    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken());
})->in(
    'Feature/Web/ShowBacklogControllerTest.php',
    'Feature/Web/ShowBacklogEntryControllerTest.php',
    'Feature/Web/GatewayCrudTest.php',
);

function dashboardToken(): string
{
    return 'test-dashboard-token';
}

/** @return array<string, string> */
function gatewayAuth(): array
{
    return ['Authorization' => 'Bearer sk-test-caller'];
}
