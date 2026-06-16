<?php

namespace App\Providers;

use App\Console\Commands\Storefront\SyncListingAvailabilityCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Storefront module service provider (Phase 2).
 *
 * Keeps storefront console/scheduling wiring isolated from the finance
 * provider. Registers the availability-sync command and schedules it hourly so
 * the public shop stays consistent with operational sales that happen outside
 * the storefront.
 */
class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the Paystack gateway client. HttpPaystackClient in production;
        // tests bind a fake implementation of the PaystackClient interface.
        $this->app->bind(
            \App\Storefront\Payments\PaystackClient::class,
            fn () => new \App\Storefront\Payments\HttpPaystackClient(
                secretKey: config('services.paystack.secret_key'),
                baseUrl:   (string) config('services.paystack.base_url', 'https://api.paystack.co'),
            ),
        );
    }

    public function boot(): void
    {
        // Phase 6 hardening — named rate limiters for the public-facing
        // endpoints. Quote submission and the Paystack webhook are the two
        // unauthenticated POST surfaces; both are throttled to blunt spam and
        // abuse. Keyed by IP so one source can't flood.
        \Illuminate\Support\Facades\RateLimiter::for('storefront-quote', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });
        \Illuminate\Support\Facades\RateLimiter::for('storefront-webhook', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip());
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncListingAvailabilityCommand::class,
            ]);

            $this->app->booted(function () {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);

                $schedule->command('storefront:sync-availability')
                    ->hourly()
                    ->withoutOverlapping(10)
                    ->name('storefront:sync-availability')
                    ->onOneServer();
            });
        }
    }
}
