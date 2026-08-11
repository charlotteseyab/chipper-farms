<?php

namespace Tests\Feature\Storefront;

use App\Domain\Events\Sales\StorefrontOrderConfirmed;
use App\Models\DomainEvent;
use App\Models\Storefront\StorefrontOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 15 — the admin order page as recovery console (blueprint §46).
 *
 * §46: "every non-final state needs a visible recovery path", and "the admin
 * order page should become the recovery console for storefront states".
 *
 * The order has recorded its finance posting outcome since Phase 4 and showed it
 * nowhere. A confirmed order whose sale never reached the books looked identical
 * to one that posted cleanly, on the one page a staff member actually opens.
 */
class OrderRecoveryConsoleTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();
    }

    protected function order(array $overrides = []): StorefrontOrder
    {
        static $sequence = 0;

        return StorefrontOrder::create(array_merge([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-REC-' . (++$sequence),
            'status'            => StorefrontOrder::STATUS_CONFIRMED,
            'payment_status'    => StorefrontOrder::PAY_PAID,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '250.00', 'delivery_fee' => '0.00', 'total' => '250.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ], $overrides));
    }

    protected function failedEventFor(StorefrontOrder $order): DomainEvent
    {
        return DomainEvent::create([
            'organization_id'  => $this->org->id,
            'event_type'       => StorefrontOrderConfirmed::TYPE,
            'source_module'    => 'storefront',
            'source_type'      => StorefrontOrder::class,
            'source_id'        => $order->id,
            'payload'          => [],
            'status'           => DomainEvent::STATUS_FAILED,
            'processing_error' => 'Account 4110 does not exist',
            'occurred_at'      => now(),
            'idempotency_key'  => 'rec-' . $order->id,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Visibility                                                       */
    /* ---------------------------------------------------------------- */

    public function test_a_failed_finance_posting_is_visible_on_the_order(): void
    {
        $order = $this->order([
            'finance_posting_status' => 'failed',
            'finance_posting_error'  => 'Account 4110 does not exist',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('has not reached the books')
            ->assertSee('Account 4110 does not exist');
    }

    /**
     * A deferred line is not an error, and calling it one sends staff hunting
     * for a fault that is not there.
     */
    public function test_skipped_lines_are_explained_rather_than_reported_as_a_failure(): void
    {
        $order = $this->order([
            'finance_posting_status' => 'skipped',
            'finance_posting_error'  => 'Deferred lines: item#3: pig_requires_weight',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Some lines were not posted')
            ->assertSee('Deferred lines are not errors');
    }

    public function test_a_cleanly_posted_order_shows_no_recovery_panel(): void
    {
        $order = $this->order(['finance_posting_status' => 'posted']);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('has not reached the books')
            ->assertDontSee('Retry finance posting');
    }

    /* ---------------------------------------------------------------- */
    /* The retry                                                        */
    /* ---------------------------------------------------------------- */

    public function test_the_retry_button_appears_only_when_there_is_a_failed_event(): void
    {
        $order = $this->order(['finance_posting_status' => 'failed']);

        // No event yet — nothing to replay, so nothing offered.
        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Retry finance posting');

        $this->failedEventFor($order);
        $this->flushSession();

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Retry finance posting');
    }

    /**
     * The retry re-queues; it does not post. The outbox does the work, and
     * every posting downstream is idempotency-keyed.
     */
    public function test_retrying_returns_the_event_to_the_queue(): void
    {
        $order = $this->order([
            'finance_posting_status' => 'failed',
            'finance_posting_error'  => 'Account 4110 does not exist',
        ]);
        $event = $this->failedEventFor($order);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.orders.retry-finance', $order))
            ->assertRedirect();

        $this->assertSame(DomainEvent::STATUS_PENDING, $event->fresh()->status);
        $this->assertNull($event->fresh()->processing_error);

        $order->refresh();
        $this->assertSame('pending', $order->finance_posting_status);
        $this->assertNull($order->finance_posting_error);

        // And the timeline records who did it — a retry is a staff decision.
        $this->assertTrue($order->events()->where('type', 'finance')->exists());
    }

    /**
     * A processed event has already done its work. Re-queueing it would be a
     * no-op that looked like an action.
     */
    public function test_an_order_that_posted_cleanly_cannot_be_retried(): void
    {
        $order = $this->order(['finance_posting_status' => 'posted']);

        DomainEvent::create([
            'organization_id' => $this->org->id,
            'event_type'      => StorefrontOrderConfirmed::TYPE,
            'source_module'   => 'storefront',
            'source_type'     => StorefrontOrder::class,
            'source_id'       => $order->id,
            'payload'         => [],
            'status'          => DomainEvent::STATUS_PROCESSED,
            'occurred_at'     => now(),
            'idempotency_key' => 'rec-posted-' . $order->id,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.orders.retry-finance', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('posted', $order->fresh()->finance_posting_status);
    }

    /**
     * The status filter, isolated.
     *
     * `replayableEventFor()` has two guards: the order must not be marked
     * posted, AND the event must have failed. The test above only exercises the
     * first — removing the `STATUS_FAILED` filter left it green, because the
     * `posted` short-circuit caught the case anyway.
     *
     * This is the case only the filter protects: an order whose lines were
     * SKIPPED (not an error, so not 'posted' either) with an event that
     * processed successfully. There is nothing failed to replay, and offering
     * the button would suggest otherwise.
     */
    public function test_a_skipped_order_whose_event_succeeded_cannot_be_retried(): void
    {
        $order = $this->order([
            'finance_posting_status' => 'skipped',
            'finance_posting_error'  => 'Deferred lines: item#3: pig_requires_weight',
        ]);

        DomainEvent::create([
            'organization_id' => $this->org->id,
            'event_type'      => StorefrontOrderConfirmed::TYPE,
            'source_module'   => 'storefront',
            'source_type'     => StorefrontOrder::class,
            'source_id'       => $order->id,
            'payload'         => [],
            'status'          => DomainEvent::STATUS_PROCESSED,
            'occurred_at'     => now(),
            'idempotency_key' => 'rec-skipped-' . $order->id,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.orders.retry-finance', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Untouched: the deferred lines still need a human, not a replay.
        $this->assertSame('skipped', $order->fresh()->finance_posting_status);
    }

    /**
     * SEC-001: seeing a failure and correcting it are different rights.
     *
     * A staff member who can open the order (`view_storefront_orders`) can read
     * the recovery panel — that is the point of showing it. Re-queueing the
     * posting changes the books' inbound work and needs
     * `manage_storefront_orders`, the same permission every other corrective
     * action on this page carries.
     */
    public function test_retrying_requires_the_permission_to_manage_orders(): void
    {
        $order = $this->order(['finance_posting_status' => 'failed']);
        $this->failedEventFor($order);

        $viewer = $this->userWithOnlyPermission('view_storefront_orders');

        // They can read it, and are not offered a button that would refuse them.
        $this->actingAs($viewer)
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('has not reached the books')
            ->assertDontSee('Retry finance posting');

        $this->flushSession();

        // ...and cannot act on it.
        $this->actingAs($viewer)
            ->post(route('storefront.admin.orders.retry-finance', $order))
            ->assertForbidden();

        $this->assertSame('failed', $order->fresh()->finance_posting_status);
    }

    protected function userWithOnlyPermission(string $permission): \App\Models\User
    {
        static $sequence = 0;
        $role = \App\Models\Role::firstOrCreate(['name' => 'orders-viewer-' . (++$sequence)]);
        $role->permissions()->syncWithoutDetaching(
            \App\Models\Permission::where('name', $permission)->pluck('id')->all(),
        );

        return \App\Models\User::forceCreate([
            'name'              => 'Viewer ' . $sequence,
            'email'             => "orders-viewer-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_another_organisations_order_cannot_be_retried(): void
    {
        $other = \App\Models\Organization::create(['slug' => 'other-rec', 'name' => 'Other Farm']);

        $foreign = StorefrontOrder::create([
            'organization_id'   => $other->id,
            'reference'         => 'SO-FOREIGN-1',
            'status'            => StorefrontOrder::STATUS_CONFIRMED,
            'payment_status'    => StorefrontOrder::PAY_PAID,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '250.00', 'delivery_fee' => '0.00', 'total' => '250.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
            'finance_posting_status' => 'failed',
        ]);

        // 404, not 403. Phase 15 recorded the inconsistency as IMP-112 and left
        // it alone rather than changing every action on the controller during a
        // UX phase; Phase 16 made the change where it belongs, with the security
        // matrices. Another farm's order is now indistinguishable from an order
        // that never existed, which matters because storefront references are
        // sequential and easy to guess.
        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.orders.retry-finance', $foreign))
            ->assertNotFound();

        $this->assertSame('failed', $foreign->fresh()->finance_posting_status);
    }

    /* ---------------------------------------------------------------- */
    /* Held stock                                                       */
    /* ---------------------------------------------------------------- */

    /**
     * §46: a held listing needs a visible expire/release/confirm path.
     */
    public function test_stock_held_for_the_order_is_shown(): void
    {
        $order   = $this->order(['finance_posting_status' => 'posted']);
        $listing = $this->makePublishedListing(['title' => 'Weaner boar']);

        \App\Models\Storefront\StorefrontInventoryHold::create([
            'organization_id' => $this->org->id,
            'listing_id'      => $listing->id,
            'order_id'        => $order->id,
            'quantity'        => '1',
            'status'          => \App\Models\Storefront\StorefrontInventoryHold::STATUS_ACTIVE,
            'expires_at'      => now()->addMinutes(30),
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Stock held for this order')
            ->assertSee('Weaner boar');
    }
}
