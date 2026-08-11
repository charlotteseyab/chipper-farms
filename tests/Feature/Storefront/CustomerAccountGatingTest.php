<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontOrder;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 16 (§18, §46) — the gating decision Phase 14 parked.
 *
 * Phase 14 gated every other optional surface and stopped here, because gating
 * `account/*` naively would hide existing customers' orders — a §46 dead end
 * manufactured by a feature flag. Prompt §110 still applies: a disabled feature
 * must not remain usable via URL.
 *
 * The resolution was already written down in `EnsureStorefrontOpen`, for the
 * same tension when the shop closes: "What closes is the ability to browse and
 * to place something new." Account self-service closes; order history does not.
 */
class CustomerAccountGatingTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function customer(): \App\Models\User
    {
        return $this->makeUser('customer');
    }

    public function test_account_self_service_closes_when_the_farm_switches_accounts_off(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS);

        $this->actingAs($this->customer())
            ->get(route('storefront.account.dashboard'))
            ->assertStatus(503)
            ->assertSee('not offering customer accounts');
    }

    /**
     * The whole reason this was parked twice.
     */
    public function test_a_customers_order_history_stays_reachable(): void
    {
        $customer = $this->customer();

        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-ACCT-1',
            'customer_user_id'  => $customer->id,
            'status'            => StorefrontOrder::STATUS_CONFIRMED,
            'payment_status'    => StorefrontOrder::PAY_PAID,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '80.00', 'delivery_fee' => '0.00', 'total' => '80.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS);

        $this->actingAs($customer)
            ->get(route('storefront.account.orders.index'))
            ->assertOk();

        $this->flushSession();

        $this->actingAs($customer)
            ->get(route('storefront.account.orders.show', $order))
            ->assertOk();
    }

    /**
     * §46: the closed page must answer the question the visitor arrived with.
     */
    public function test_the_closed_page_points_at_the_orders_that_still_work(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS);

        $this->actingAs($this->customer())
            ->get(route('storefront.account.profile'))
            ->assertStatus(503)
            ->assertSee('View my orders');
    }

    /**
     * 503 rather than 404, so a browser and a crawler both treat it as a
     * surface that is unavailable rather than one that never existed.
     */
    public function test_the_refusal_is_temporary_rather_than_a_missing_page(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS);

        $this->actingAs($this->customer())
            ->get(route('storefront.account.saved'))
            ->assertStatus(503);
    }

    public function test_the_account_area_works_normally_when_the_feature_is_on(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS);

        $this->actingAs($this->customer())
            ->get(route('storefront.account.dashboard'))
            ->assertOk();
    }

    /**
     * SEC-001: the flag hides a surface, it does not decide who sees whose data.
     * A signed-out visitor is stopped by authentication first, as before.
     */
    public function test_a_signed_out_visitor_is_still_sent_to_login(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS);

        $this->get(route('storefront.account.dashboard'))
            ->assertRedirect(route('login'));
    }
}
