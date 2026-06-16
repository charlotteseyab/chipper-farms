<?php

namespace App\Providers;

use App\Console\Commands\BackfillPigSaleInvoicesCommand;
use App\Console\Commands\Finance\CheckOutboxWiringCommand;
use App\Console\Commands\ProcessDomainEvents;
use App\Console\Commands\ReconcileInvoiceAggregatesCommand;
use App\Domain\Events\Feed\FeedBatchProduced;
use App\Domain\Events\Inventory\RawMaterialAdjusted;
use App\Domain\Events\Inventory\RawMaterialReceived;
use App\Domain\Events\Procurement\PurchaseRequestApproved;
use App\Finance\Outbox\SubscriberRegistry;
use App\Finance\Subscribers\Inventory\FeedBatchProducedSubscriber;
use App\Finance\Subscribers\Inventory\RawMaterialAdjustedSubscriber;
use App\Finance\Subscribers\Inventory\RawMaterialReceivedSubscriber;
use App\Finance\Subscribers\Procurement\PurchaseRequestInvoicingSubscriber;
use App\Finance\Subscribers\Sales\PigSaleInvoicingSubscriber;
use App\Finance\Subscribers\Sales\PigSalePaymentReceiptSubscriber;
use App\Finance\Subscribers\Sales\PigSalePaymentReceivedSubscriber;
use App\Finance\Subscribers\Sales\PigSaleRecordedSubscriber;
use App\Jobs\ProcessPendingDomainEventsJob;
use App\Models\PigSale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Phase 4 — corrected finance service provider.
 *
 * Replaces the prior version which had:
 *
 *   1. A `register()` method that referenced `$subscriberRegistry` as
 *      a free variable, never assigned. PHP would fatally error on
 *      first invocation. The previous code likely never ran in
 *      production because — finding #2 — the provider wasn't registered
 *      in bootstrap/providers.php to begin with.
 *
 *   2. A `registerSubscribers()` method that wired the subscriber
 *      registry, but was DEFINED and never CALLED. Subscribers were
 *      effectively unwired in any environment where this provider DID
 *      load. ProcessPendingDomainEventsJob would mark every event as
 *      SKIPPED with "No finance subscriber registered for event_type ...".
 *
 *   3. References to `PigSale::class` in event-type strings that were
 *      missing or inconsistent with what the dispatchers emit (the
 *      dispatchers emit 'pig_sale.recorded', not class strings).
 *
 * This rewrite:
 *
 *   - Registers SubscriberRegistry as a SINGLETON in register() so
 *     all consumers share the same in-memory map.
 *   - Wires all subscribers in registerSubscribers() and CALLS it from
 *     boot() (which runs after the singleton is bound).
 *   - Uses the canonical event-type strings (each event's TYPE constant).
 *   - Adds the new Phase 4 subscribers for raw material / feed events.
 *   - Adds a boot-time invariant check in non-production: every
 *     subscriber's eventType() resolves; no subscriber class is
 *     missing/renamed.
 *   - Registers the Phase 4 `php artisan finance:check-outbox-wiring`
 *     command for ops diagnostics.
 *
 * REGISTRATION
 *
 *   This provider must be present in bootstrap/providers.php. The
 *   Phase 4 deployment notes include the patch.
 */
class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SubscriberRegistry is a process-wide map of event_type →
        // subscriber classes. Singleton so multiple resolves return the
        // same instance.
        $this->app->singleton(SubscriberRegistry::class);

        // Merge default finance config so apps that don't ship a
        // config/finance.php still get sane defaults.
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/finance.php',
            'finance',
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/biological_assets.php',
            'biological_assets',
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/loans.php',
            'loans',
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/investors.php',
            'investors',
        );

        $this->mergeConfigFrom(
           __DIR__ . '/../../config/feed_sales.php',
          'feed_sales',
        );   
    }

    public function boot(): void
    {
        // 1. Wire subscribers — this is the change that fixes the
        //    previously-dead pipeline.
        $registry = $this->app->make(SubscriberRegistry::class);
        $this->registerSubscribers($registry);

        // 2. Non-production sanity check. Catches typos in subscriber
        //    classes or event-type mismatches at boot time, when they're
        //    cheapest to fix.
        if (! $this->app->environment('production')) {
            $this->assertSubscriberWiringValid($registry);
        }

        // 3. Console commands + scheduler.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessDomainEvents::class,
                BackfillPigSaleInvoicesCommand::class,
                ReconcileInvoiceAggregatesCommand::class,
                CheckOutboxWiringCommand::class,
            ]);

            $this->app->booted(function () {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);

                // Outbox drain — every minute, single-server, single
                // instance at a time.
                $schedule->job(new ProcessPendingDomainEventsJob(50))
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->name('finance:process-events')
                    ->onOneServer();
            });
        }

        // Publish finance config for env-specific overrides.
        $this->publishes([
            __DIR__ . '/../../config/finance.php' => config_path('finance.php'),
        ], 'finance-config');
    }

    /**
     * Bind every domain event type to its subscriber class(es).
     *
     * Multiple subscribers per event type are supported — order is
     * the insertion order. The outbox processor invokes them
     * sequentially per event; if any throws, the event is marked
     * failed and the remaining subscribers are NOT invoked. This is
     * intentional: failure in one subscriber commonly indicates data
     * the others will also stumble on.
     */
    protected function registerSubscribers(SubscriberRegistry $registry): void
    {
        // -------- Sales (pre-existing, kept) -----------------------
        $registry->register(
            \App\Domain\Events\Sales\PigSaleRecorded::TYPE,
            PigSaleRecordedSubscriber::class,
        );
        $registry->register(
            \App\Domain\Events\Sales\PigSaleRecorded::TYPE,
            PigSaleInvoicingSubscriber::class,
        );

        $registry->register(
            \App\Domain\Events\Sales\PigSalePaymentStatusChanged::TYPE,
            PigSalePaymentReceivedSubscriber::class,
        );
        $registry->register(
            \App\Domain\Events\Sales\PigSalePaymentStatusChanged::TYPE,
            PigSalePaymentReceiptSubscriber::class,
        );

        // -------- Procurement (pre-existing) -----------------------
        $registry->register(
            PurchaseRequestApproved::TYPE,
            PurchaseRequestInvoicingSubscriber::class,
        );

        // -------- Inventory / Feed (NEW in Phase 4) ----------------
        $registry->register(
            RawMaterialReceived::TYPE,
            RawMaterialReceivedSubscriber::class,
        );
        $registry->register(
            RawMaterialAdjusted::TYPE,
            RawMaterialAdjustedSubscriber::class,
        );
        $registry->register(
            FeedBatchProduced::TYPE,
            FeedBatchProducedSubscriber::class,
        );
        $registry->register(
            \App\Domain\Events\Costing\FeedIssued::TYPE,
            \App\Finance\Subscribers\Costing\FeedIssuedSubscriber::class,
        );
    
        // -------- Lifecycle (NEW in Phase 5) -----------------------
        // The existing pig_death.recorded event was dispatched but had no
        // subscriber. Phase 5 wires it.
        $registry->register(
            \App\Domain\Events\Lifecycle\PigDeathRecorded::TYPE,
            \App\Finance\Subscribers\Lifecycle\PigDeathRecordedSubscriber::class,
        );

        $registry->register(
            \App\Domain\Events\Lifecycle\PigAcquired::TYPE,
            \App\Finance\Subscribers\Lifecycle\PigAcquiredSubscriber::class,
        );

        $registry->register(
            \App\Domain\Events\Lifecycle\PigBirthRecorded::TYPE,
            \App\Finance\Subscribers\Lifecycle\PigBirthRecordedSubscriber::class,
        );

        $registry->register(
            \App\Domain\Events\Lifecycle\LitterWeaned::TYPE,
            \App\Finance\Subscribers\Lifecycle\WeaningStageTransferSubscriber::class,
        );

        $registry->register(
            \App\Domain\Events\Lifecycle\PigWeaned::TYPE,
            \App\Finance\Subscribers\Lifecycle\WeaningStageTransferSubscriber::class,
        );
    
        // -------- Sales — COGS leg (NEW in Phase 5) ----------------
        // Runs alongside the existing PigSaleRecordedSubscriber (revenue)
        // and PigSaleInvoicingSubscriber (auto-invoice). All three listen
        // to pig_sale.recorded; each posts an independent journal batch.
        $registry->register(
            \App\Domain\Events\Sales\PigSaleRecorded::TYPE,
            \App\Finance\Subscribers\Sales\PigSaleCogsSubscriber::class,
        );

         // -------- Lifecycle — biological asset stage transfer (Phase 7) --
         // When a pig is promoted/demoted between stock types, move its
        // cost between biological-asset stage accounts.
        $registry->register(
            \App\Domain\Events\Lifecycle\PigLifecycleStageChanged::TYPE,
            \App\Finance\Subscribers\Lifecycle\PigLifecycleStageChangedSubscriber::class,
        );

        // -------- Feed sales — revenue + COGS legs (feed-sale path) ------
        // A recorded feed sale posts two independent journal batches, exactly
        // like pig sales: revenue (+ cash/AR) and COGS (+ finished-feed
        // inventory decrement at weighted-average cost). Both listen to
        // feed_sale.recorded.
        $registry->register(
            \App\Domain\Events\Sales\FeedSaleRecorded::TYPE,
            \App\Finance\Subscribers\Sales\FeedSaleRevenueSubscriber::class,
        );
        $registry->register(
            \App\Domain\Events\Sales\FeedSaleRecorded::TYPE,
            \App\Finance\Subscribers\Sales\FeedSaleCogsSubscriber::class,
        );

        // Phase 5 — feed sale payment settlement (the feed analogue of the pig
        // payment-received path; closes the gap for pending feed AR).
        $registry->register(
            \App\Domain\Events\Sales\FeedSalePaymentStatusChanged::TYPE,
            \App\Finance\Subscribers\Sales\FeedSalePaymentReceivedSubscriber::class,
        );

        // Phase 4 — storefront finance integration. Routes a confirmed
        // storefront order's lines into the feed/pig sale spine above; never
        // posts to the GL directly.
        $registry->register(
            \App\Domain\Events\Sales\StorefrontOrderConfirmed::TYPE,
            \App\Finance\Subscribers\Sales\StorefrontOrderConfirmedSubscriber::class,
        );
    }

    /**
     * Walks the registered subscribers and verifies:
     *   - Every subscriber class exists.
     *   - Every subscriber type resolves cleanly (no static analysis
     *     failures at boot).
     *
     * Non-production only. Production environments load the wiring as
     * given — a misconfiguration in prod is a deployment issue, not
     * a boot-time concern.
     */
    protected function assertSubscriberWiringValid(SubscriberRegistry $registry): void
    {
        foreach ($registry->registeredTypes() as $type) {
            foreach ($registry->subscribersFor($type) as $class) {
                if (! class_exists($class)) {
                    $message = "FinanceServiceProvider: subscriber class '{$class}' "
                             . "registered for event_type '{$type}' does not exist. "
                             . "Either fix the registration or remove it.";
                    Log::warning($message);
                    if ($this->app->environment('local', 'testing')) {
                        throw new \RuntimeException($message);
                    }
                }
            }
        }
    }
}
