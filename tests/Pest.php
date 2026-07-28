<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use NovaTesting\NovaAssertions;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\DuskTestCase;
use Tests\TestCase;

uses(
    DuskTestCase::class,
    DatabaseMigrations::class,
)
    ->beforeAll(function () {
        if ((($_ENV['RUN_BROWSER_TESTS'] ?? null) !== '1') && getenv('RUN_BROWSER_TESTS') !== '1') {
            return;
        }

        $_ENV['DUSK_BROWSER_SUITE'] = '1';
        putenv('DUSK_BROWSER_SUITE=1');

        DuskTestCase::prepare();
    })
    ->beforeEach(function () {
        if ((($_ENV['RUN_BROWSER_TESTS'] ?? null) !== '1') && getenv('RUN_BROWSER_TESTS') !== '1') {
            $this->markTestSkipped('Browser tests are opt-in: RUN_BROWSER_TESTS=1 (CI uses php artisan dusk separately).');
        }

        if (! DuskTestCase::chromeDriverIsAvailable()) {
            $this->markTestSkipped(
                'ChromeDriver is not reachable (start chromedriver on port 9515, set DUSK_DRIVER_URL, or use DUSK_SOFT_CHROMEDRIVER=true when start fails in prepare()).'
            );
        }

        $this->seed(RolesAndPermissionsSeeder::class);
    })
    ->in('Browser');

uses(TestCase::class, RefreshDatabase::class, NovaAssertions::class)
    ->beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
    })
    ->in('Core', 'Feature', 'Helper', 'Unit');

function createRequest($method, $uri): Request
{
    $symfonyRequest = SymfonyRequest::create(
        $uri,
        $method
    );

    return Request::createFromBase($symfonyRequest);
}
