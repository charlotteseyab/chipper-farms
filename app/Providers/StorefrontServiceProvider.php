<?php

namespace App\Providers;

use App\Console\Commands\Storefront\ExpireInventoryHoldsCommand;
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
        // Phase 8 — per-organisation storefront settings. A singleton so its
        // per-organisation cache lives exactly one request (and exactly one
        // test), rather than a static memo that outlives the rows it describes.
        $this->app->singleton(\App\Storefront\Support\StorefrontSettingsResolver::class);

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

        // Phase 9 — checkout became an unauthenticated POST when guest checkout
        // landed, so it joins the throttled surfaces. Ten a minute is generous
        // for a person and useless for a script: each attempt mints an order
        // reference from the numbering sequence and can create a counterparty.
        // Keyed by the authenticated user where there is one, so several buyers
        // behind one office NAT do not throttle each other.
        \Illuminate\Support\Facades\RateLimiter::for('storefront-checkout', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip());
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncListingAvailabilityCommand::class,
                ExpireInventoryHoldsCommand::class,
            ]);

            $this->app->booted(function () {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);

                $schedule->command('storefront:sync-availability')
                    ->hourly()
                    ->withoutOverlapping(10)
                    ->name('storefront:sync-availability')
                    ->onOneServer();

                // Phase 10 (§37). Every minute, because the unit being returned
                // is often the only one: an hourly sweep would leave the last
                // boar unsellable for up to an hour after an abandoned
                // checkout. The sweep is one conditional UPDATE over an indexed
                // predicate, so running it often is cheap.
                $schedule->command('storefront:expire-holds')
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->name('storefront:expire-holds')
                    ->onOneServer();
            });
        }
    }
}
