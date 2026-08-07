<?php

namespace Tests\Feature\Finance\Overview;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\Storefront\StorefrontOrder;
use App\Services\Finance\Overview\NeedsAttentionService;
use App\Support\CurrentOrganization;
use Database\Seeders\GhanaTaxProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The consolidated attention queue — Phase 3 (blueprint §54, prompt §28).
 *
 * Two properties matter. The queue must actually SEE each failure state, since
 * anything it misses stays invisible until a customer complains. And every
 * count must be organisation-scoped, because the dashboard this replaces
 * counted domain events across all tenants at once.
 */
class NeedsAttentionTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf-' . uniqid(), 'name' => 'Chipper Farms', 'is_active' => true,
        ]);
        $this->other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);

        // Give both organisations a tax profile so the "tax not configured"
        // item does not fire in tests that are about something else.
        $this->seed(GhanaTaxProfileSeeder::class);
    }

    protected function service(): NeedsAttentionService
    {
        return app(NeedsAttentionService::class);
    }

    protected function keys(?Organization $org = null): array
    {
        return array_column($this->service()->items($org), 'key');
    }

    protected function item(string $key, ?Organization $org = null): ?array
    {
        foreach ($this->service()->items($org) as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    protected function failedEvent(Organization $org): DomainEvent
    {
        return DomainEvent::forceCreate([
            'organization_id' => $org->id,
            'event_type'      => 'test.event',
            'idempotency_key' => 'idem-' . uniqid(),
            'source_module'   => 'test',
            'source_type'     => 'Test',
            'source_id'       => random_int(1, 99999),
            'payload'         => [],
            'status'          => DomainEvent::STATUS_FAILED,
            'occurred_at'     => now(),
        ]);
    }

    protected function order(Organization $org, array $attributes): StorefrontOrder
    {
        return StorefrontOrder::forceCreate(array_merge([
            'organization_id' => $org->id,
            'reference'       => 'SO-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status'          => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_method'  => StorefrontOrder::METHOD_COD,
            'payment_status'  => StorefrontOrder::PAY_UNPAID,
            'subtotal'        => '100.00',
            'total'           => '100.00',
            'currency_code'   => 'GHS',
        ], $attributes));
    }

    /* ------------------------------------------------------------------ */

    public function test_a_clean_organisation_reports_nothing(): void
    {
        $this->assertSame([], $this->service()->items());
        $this->assertSame(0, $this->service()->total());
        $this->assertFalse($this->service()->hasCritical());
    }

    public function test_failed_finance_postings_are_surfaced_as_critical(): void
    {
        $this->failedEvent($this->org);

        $item = $this->item('finance.failed_postings');

        $this->assertNotNull($item, 'A failed outbox event must reach the attention queue.');
        $this->assertSame(NeedsAttentionService::SEVERITY_CRITICAL, $item['severity']);
        $this->assertTrue($this->service()->hasCritical());
    }

    public function test_a_failed_posting_message_is_plain_language(): void
    {
        $this->failedEvent($this->org);

        $item = $this->item('finance.failed_postings');

        $this->assertStringNotContainsStringIgnoringCase('domain event', $item['message']);
        $this->assertStringNotContainsStringIgnoringCase('outbox', $item['message']);
        $this->assertStringNotContainsStringIgnoringCase('subscriber', $item['message']);
        $this->assertStringContainsString('bookkeeping', $item['message']);

        // The technical wording still exists, just not as the headline.
        $this->assertStringContainsStringIgnoringCase('domain event', $item['detail']);
    }

    public function test_stuck_postings_are_distinguished_from_failed_ones(): void
    {
        DomainEvent::forceCreate([
            'organization_id' => $this->org->id,
            'event_type'      => 'test.event',
            'idempotency_key' => 'idem-' . uniqid(),
            'source_module'   => 'test',
            'source_type'     => 'Test',
            'source_id'       => 1,
            'payload'         => [],
            'status'          => DomainEvent::STATUS_PROCESSING,
            'claimed_at'      => now()->subHours(2),
            'occurred_at'     => now()->subHours(2),
        ]);

        $this->assertContains('finance.stuck_postings', $this->keys());
        $this->assertNotContains(
            'finance.failed_postings',
            $this->keys(),
            'A stalled claim has not errored, so nothing will retry it on its own — that is why '
            . 'it is a separate item rather than being folded into failures.',
        );
    }

    public function test_a_recently_claimed_event_is_not_reported_as_stuck(): void
    {
        DomainEvent::forceCreate([
            'organization_id' => $this->org->id,
            'event_type'      => 'test.event',
            'idempotency_key' => 'idem-' . uniqid(),
            'source_module'   => 'test',
            'source_type'     => 'Test',
            'source_id'       => 2,
            'payload'         => [],
            'status'          => DomainEvent::STATUS_PROCESSING,
            'claimed_at'      => now()->subMinute(),
            'occurred_at'     => now(),
        ]);

        $this->assertNotContains(
            'finance.stuck_postings',
            $this->keys(),
            'Work in progress is not a problem.',
        );
    }

    public function test_orders_awaiting_confirmation_are_surfaced(): void
    {
        $this->order($this->org, ['status' => StorefrontOrder::STATUS_PENDING_CONFIRMATION]);

        $this->assertContains('storefront.awaiting_confirmation', $this->keys());
    }

    public function test_cod_orders_at_fulfilment_without_payment_are_surfaced(): void
    {
        $this->order($this->org, [
            'status'         => StorefrontOrder::STATUS_READY_FOR_PICKUP,
            'payment_method' => StorefrontOrder::METHOD_COD,
            'payment_status' => StorefrontOrder::PAY_UNPAID,
        ]);

        $this->assertContains('storefront.cod_awaiting_cash', $this->keys());
    }

    public function test_a_paid_cod_order_is_not_flagged(): void
    {
        $this->order($this->org, [
            'status'         => StorefrontOrder::STATUS_READY_FOR_PICKUP,
            'payment_method' => StorefrontOrder::METHOD_COD,
            'payment_status' => StorefrontOrder::PAY_PAID,
        ]);

        $this->assertNotContains('storefront.cod_awaiting_cash', $this->keys());
    }

    public function test_a_fresh_pending_refund_is_informational(): void
    {
        $this->order($this->org, ['payment_status' => StorefrontOrder::PAY_REFUND_PENDING]);

        $item = $this->item('storefront.refunds_pending');

        $this->assertNotNull($item);
        $this->assertSame(NeedsAttentionService::SEVERITY_INFO, $item['severity']);
    }

    public function test_a_refund_pending_for_days_escalates_to_critical(): void
    {
        $order = $this->order($this->org, ['payment_status' => StorefrontOrder::PAY_REFUND_PENDING]);
        $order->forceFill(['updated_at' => now()->subDays(5)])->saveQuietly();

        $item = $this->item('storefront.refunds_pending');

        $this->assertSame(
            NeedsAttentionService::SEVERITY_CRITICAL,
            $item['severity'],
            'The customer has been told a refund is coming and the money has not moved.',
        );
    }

    public function test_the_refund_detail_warns_against_resubmitting(): void
    {
        $this->order($this->org, ['payment_status' => StorefrontOrder::PAY_REFUND_PENDING]);

        $this->assertStringContainsStringIgnoringCase(
            'do not resubmit',
            $this->item('storefront.refunds_pending')['detail'],
            'Resubmitting a pending refund risks paying the customer twice; the guidance must say so.',
        );
    }

    public function test_missing_tax_configuration_is_surfaced(): void
    {
        // A brand-new organisation with no seeded profiles.
        $fresh = Organization::create([
            'slug' => 'fresh-' . uniqid(), 'name' => 'Fresh Farm', 'is_active' => true,
        ]);

        $item = $this->item('finance.tax_not_configured', $fresh);

        $this->assertNotNull($item);
        $this->assertStringContainsStringIgnoringCase(
            'not the same as being exempt',
            $item['detail'],
            'Untaxed and exempt look identical on an invoice and mean opposite things.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Organisation scoping — the defect this replaces                    */
    /* ------------------------------------------------------------------ */

    public function test_another_organisations_failures_are_not_counted(): void
    {
        $this->failedEvent($this->other);
        $this->failedEvent($this->other);

        $this->assertNotContains(
            'finance.failed_postings',
            $this->keys($this->org),
            'The dashboard this replaces counted domain events with no organisation filter, so one '
            . 'farm saw another farm\'s failure count.',
        );

        $this->assertContains('finance.failed_postings', $this->keys($this->other));
    }

    public function test_counts_reflect_only_the_viewing_organisation(): void
    {
        $this->failedEvent($this->org);
        $this->failedEvent($this->other);
        $this->failedEvent($this->other);

        $this->assertSame(1, $this->item('finance.failed_postings', $this->org)['count']);
        $this->assertSame(2, $this->item('finance.failed_postings', $this->other)['count']);
    }

    public function test_storefront_items_are_organisation_scoped_too(): void
    {
        $this->order($this->other, ['status' => StorefrontOrder::STATUS_PENDING_CONFIRMATION]);

        $this->assertNotContains('storefront.awaiting_confirmation', $this->keys($this->org));
        $this->assertContains('storefront.awaiting_confirmation', $this->keys($this->other));
    }

    public function test_no_organisation_context_yields_nothing(): void
    {
        $this->failedEvent($this->org);
        $this->app->make(CurrentOrganization::class)->bind(null);

        $this->assertSame(
            [],
            $this->service()->items(),
            'Without an organisation the service must return nothing rather than every tenant\'s problems.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Ordering                                                           */
    /* ------------------------------------------------------------------ */

    public function test_critical_items_sort_above_informational_ones(): void
    {
        $this->failedEvent($this->org);
        $this->order($this->org, ['status' => StorefrontOrder::STATUS_PENDING_CONFIRMATION]);

        $items = $this->service()->items();

        $this->assertSame(NeedsAttentionService::SEVERITY_CRITICAL, $items[0]['severity']);
        $this->assertSame(
            'finance.failed_postings',
            $items[0]['key'],
            'Incomplete books outrank a routine order queue.',
        );
    }

    public function test_every_item_carries_both_a_message_and_a_detail(): void
    {
        $this->failedEvent($this->org);
        $this->order($this->org, ['status' => StorefrontOrder::STATUS_PENDING_CONFIRMATION]);
        $this->order($this->org, ['payment_status' => StorefrontOrder::PAY_REFUND_PENDING]);

        foreach ($this->service()->items() as $item) {
            $this->assertNotEmpty($item['message'], "[{$item['key']}] has no plain-language message.");
            $this->assertNotEmpty($item['detail'], "[{$item['key']}] has no technical detail.");
            $this->assertGreaterThan(0, $item['count']);
            $this->assertContains($item['severity'], ['critical', 'warning', 'info']);
        }
    }
}
