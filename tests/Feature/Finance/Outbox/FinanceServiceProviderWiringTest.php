<?php

namespace Tests\Feature\Finance\Outbox;

use App\Domain\Events\Feed\FeedBatchProduced;
use App\Domain\Events\Inventory\RawMaterialAdjusted;
use App\Domain\Events\Inventory\RawMaterialReceived;
use App\Domain\Events\Procurement\PurchaseRequestApproved;
use App\Domain\Events\Sales\PigSalePaymentStatusChanged;
use App\Domain\Events\Sales\PigSaleRecorded;
use App\Finance\Outbox\SubscriberRegistry;
use Tests\TestCase;

/**
 * Phase 4 — Verifies the FinanceServiceProvider wiring is intact.
 *
 * Catches regressions where:
 *   - A subscriber class is renamed but not updated in the provider.
 *   - An event TYPE constant is renamed.
 *   - A new event is added without a subscriber.
 */
class FinanceServiceProviderWiringTest extends TestCase
{
    public function test_subscriber_registry_is_a_singleton(): void
    {
        $a = $this->app->make(SubscriberRegistry::class);
        $b = $this->app->make(SubscriberRegistry::class);
        $this->assertSame($a, $b);
    }

    public function test_phase_4_event_types_all_have_subscribers(): void
    {
        $registry = $this->app->make(SubscriberRegistry::class);

        $expected = [
            PigSaleRecorded::TYPE,
            PigSalePaymentStatusChanged::TYPE,
            PurchaseRequestApproved::TYPE,
            RawMaterialReceived::TYPE,
            RawMaterialAdjusted::TYPE,
            FeedBatchProduced::TYPE,
        ];

        foreach ($expected as $type) {
            $subs = $registry->subscribersFor($type);
            $this->assertNotEmpty(
                $subs,
                "No subscriber registered for event type '{$type}'. "
                . "Phase 4 expects this wiring to exist."
            );

            // Every registered subscriber class must actually exist.
            foreach ($subs as $cls) {
                $this->assertTrue(
                    class_exists($cls),
                    "Registered subscriber class '{$cls}' for type '{$type}' does not exist."
                );
            }
        }
    }

    public function test_each_subscriber_is_resolvable_from_container(): void
    {
        $registry = $this->app->make(SubscriberRegistry::class);

        foreach ($registry->registeredTypes() as $type) {
            foreach ($registry->subscribersFor($type) as $cls) {
                $instance = $this->app->make($cls);
                $this->assertNotNull(
                    $instance,
                    "Subscriber '{$cls}' for type '{$type}' could not be resolved by the container."
                );
            }
        }
    }
}
