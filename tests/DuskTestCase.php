<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    protected static bool $chromeDriverStarted = false;

    #[BeforeClass]
    public static function prepare(): void
    {
        if (static::runningInSail()) {
            return;
        }

        if (static::isChromeDriverRunning()) {
            static::$chromeDriverStarted = true;

            return;
        }

        if (static::shouldStartChromeDriverForSuite() && ! static::$chromeDriverStarted) {
            try {
                static::startChromeDriver(['--port=9515']);
                static::$chromeDriverStarted = true;
            } catch (\Throwable $e) {
                if (static::softChromeDriverFailureAllowsSkip()) {
                    static::$chromeDriverStarted = false;

                    return;
                }

                throw new \RuntimeException(
                    'ChromeDriver failed to start. Install chromedriver, free port 9515, or set DUSK_SOFT_CHROMEDRIVER=true to skip browser tests when the driver is unavailable.',
                    0,
                    $e
                );
            }
        }
    }

    /**
     * When true, a failed ChromeDriver start leaves the suite runnable so Pest can skip in beforeEach.
     * Default false keeps CI fail-fast when browser tests are explicitly enabled.
     */
    protected static function softChromeDriverFailureAllowsSkip(): bool
    {
        $value = $_ENV['DUSK_SOFT_CHROMEDRIVER'] ?? getenv('DUSK_SOFT_CHROMEDRIVER');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected static function isChromeDriverRunning(): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 1,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents('http://localhost:9515/status', false, $context);

        return $result !== false;
    }

    /**
     * Whether ChromeDriver is listening on the default port (e.g. already started in CI).
     */
    public static function chromeDriverIsAvailable(): bool
    {
        return static::isChromeDriverRunning();
    }

    protected static function isRunningBrowserTests(): bool
    {
        if (($_ENV['DUSK_BROWSER_SUITE'] ?? getenv('DUSK_BROWSER_SUITE')) === '1') {
            return true;
        }

        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_contains($arg, 'tests'.DIRECTORY_SEPARATOR.'Browser') ||
                str_contains($arg, 'tests/Browser')) {
                return true;
            }
        }

        if (isset($_ENV['PEST_TEST_PATH']) && str_contains($_ENV['PEST_TEST_PATH'], 'Browser')) {
            return true;
        }

        if (isset($_ENV['RUN_BROWSER_TESTS']) && $_ENV['RUN_BROWSER_TESTS'] === '1') {
            return true;
        }

        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_contains($arg, 'phpunit.dusk.xml')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Start ChromeDriver when this process is clearly running the Browser / Dusk suite.
     */
    protected static function shouldStartChromeDriverForSuite(): bool
    {
        return static::isRunningBrowserTests();
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--ignore-certificate-errors',
            '--allow-insecure-localhost',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    protected function baseUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }

    protected function newBrowser($driver)
    {
        return new Browser($driver);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['honeypot.enabled' => false]);
    }
}
