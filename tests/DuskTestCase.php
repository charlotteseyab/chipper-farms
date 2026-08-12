<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;


    /**
     * The database this suite is allowed to touch.
     *
     * WHY THIS EXISTS — READ BEFORE REMOVING
     * --------------------------------------
     * Browser tests that use `DatabaseMigrations` run `migrate:fresh`, which
     * DROPS EVERY TABLE on whatever connection the application is bound to.
     * Dusk drives a real HTTP server, so that connection is the one in `.env` —
     * the development database — unless something stops it.
     *
     * Nothing stopped it. The first run of this suite dropped the development
     * database and every row in it. The pages still rendered, empty, so the run
     * looked like it had worked.
     *
     * The concurrency suite has had `assertSafeDatabaseSelection()` since it was
     * written, for exactly this reason. This is the same guard for the same
     * mistake: the suite refuses to start unless it is pointed at a database
     * whose name says it is disposable.
     *
     * To run these tests:
     *
     *   mysql -e "CREATE DATABASE IF NOT EXISTS chipper_farms_upgrade_test"
     *   php artisan serve --port=8899 --env=dusk.local   # or an .env.dusk.local
     *   DB_DATABASE=chipper_farms_upgrade_test php artisan dusk
     *
     * The served application must use the same database, or the browser will be
     * looking at one database while the test truncates another.
     */
    protected const ALLOWED_DATABASES = ['chipper_farms_upgrade_test'];

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.' . config('database.default') . '.database');
        $basename = is_string($database) ? basename((string) $database) : '';

        if (! in_array($basename, static::ALLOWED_DATABASES, true)) {
            $this->fail(
                "DUSK-DB-GUARD: refusing to run browser tests against '{$basename}'.\n\n"
                . "This suite uses DatabaseMigrations, which drops every table on the connected\n"
                . "database. It has already destroyed a development database once by running\n"
                . "against the .env connection.\n\n"
                . 'Allowed: ' . implode(', ', static::ALLOWED_DATABASES) . "\n"
                . "Set DB_DATABASE (and point the served app at the same database) before retrying."
            );
        }
    }

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    /**
     * The system Chromium, named explicitly.
     *
     * The driver binary is pinned to the installed browser with
     * `php artisan dusk:chrome-driver 126`. `dusk:install` fetches the LATEST
     * driver, which does not speak to Chromium 126 — re-pin after ever running
     * it, or every browser test dies at session creation.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            // Required to run as root in this environment; the browser is
            // driving a local test server only.
            '--no-sandbox',
            '--disable-dev-shm-usage',
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
}
