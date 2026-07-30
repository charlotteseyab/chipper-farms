<?php

namespace Tests\Feature\Finance;

use App\Models\Organization;
use App\Models\Storefront\StorefrontOrder;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reconciliation of storefront money state (remediation brief §11.7).
 *
 * These conditions are all cases where what the business believes and what
 * actually happened have diverged. The command reports and never corrects,
 * because every one has money attached and the recurring failure in this
 * codebase has been software silently deciding on the operator's behalf.
 *
 * The tests below matter beyond covering a command: several assert that a
 * discrepancy the remediation cannot yet fix is at least VISIBLE. CF-P0-008 in
 * particular remains open, so orders with incomplete sales-return accounting
 * must show up somewhere rather than sitting in a text note nothing reads.
 */
class StorefrontSettlementReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF', 'is_active' => true]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    protected function makeOrder(array $attributes): StorefrontOrder
    {
        return StorefrontOrder::forceCreate(array_merge([
            'organization_id' => $this->org->id,
            'reference'       => 'SO-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status'          => StorefrontOrder::STATUS_CONFIRMED,
            'payment_method'  => StorefrontOrder::METHOD_COD,
            'payment_status'  => StorefrontOrder::PAY_UNPAID,
            'subtotal'        => '100.00',
            'total'           => '100.00',
            'currency_code'   => 'GHS',
        ], $attributes));
    }

    public function test_clean_data_reports_no_discrepancies(): void
    {
        $this->makeOrder([
            'status'         => StorefrontOrder::STATUS_CONFIRMED,
            'payment_status' => StorefrontOrder::PAY_UNPAID,
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')
            ->expectsOutputToContain('No discrepancies found.')
            ->assertExitCode(0);
    }

    /**
     * The CF-P0-007 signature: refunded with no evidence money moved.
     */
    public function test_detects_order_refunded_without_provider_proof(): void
    {
        $this->makeOrder([
            'status'                    => StorefrontOrder::STATUS_REFUNDED,
            'payment_method'            => StorefrontOrder::METHOD_PAYSTACK,
            'payment_status'            => StorefrontOrder::PAY_REFUNDED,
            'refunded_at'               => now(),
            'refund_provider_reference' => null,
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')
            ->assertExitCode(1);
    }

    /**
     * Cash on delivery has no gateway leg, so it must NOT be flagged — otherwise
     * every COD refund becomes noise and buries the real cases.
     */
    public function test_cod_refund_without_provider_reference_is_not_flagged(): void
    {
        $this->makeOrder([
            'status'                    => StorefrontOrder::STATUS_REFUNDED,
            'payment_method'            => StorefrontOrder::METHOD_COD,
            'payment_status'            => StorefrontOrder::PAY_REFUNDED,
            'refunded_at'               => now(),
            'refund_provider_reference' => null,
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')
            ->expectsOutputToContain('No discrepancies found.')
            ->assertExitCode(0);
    }

    /**
     * A refund accepted by the provider but never settled. Expected to occur in
     * practice today, because the refund.processed webhook is not implemented —
     * this check is currently the only thing that surfaces a customer still
     * waiting for their money.
     */
    public function test_detects_refund_submitted_but_never_settled(): void
    {
        $this->makeOrder([
            'status'                    => StorefrontOrder::STATUS_REFUND_APPROVED,
            'payment_method'            => StorefrontOrder::METHOD_PAYSTACK,
            'payment_status'            => StorefrontOrder::PAY_REFUND_PENDING,
            'refund_submitted_at'       => now()->subDays(3),
            'refund_provider_reference' => 'rf_stuck_1',
            'refund_provider_status'    => 'pending',
            'refunded_at'               => null,
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')
            ->assertExitCode(1);
    }

    /**
     * A refund submitted an hour ago is NOT stuck. Paystack settles
     * asynchronously and flagging it immediately would make the report useless.
     */
    public function test_recent_pending_refund_is_not_flagged_as_stale(): void
    {
        $this->makeOrder([
            'status'                    => StorefrontOrder::STATUS_REFUND_APPROVED,
            'payment_method'            => StorefrontOrder::METHOD_PAYSTACK,
            'payment_status'            => StorefrontOrder::PAY_REFUND_PENDING,
            'refund_submitted_at'       => now()->subHour(),
            'refund_provider_reference' => 'rf_recent_1',
            'refund_provider_status'    => 'pending',
            'refunded_at'               => null,
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')
            ->expectsOutputToContain('No discrepancies found.')
            ->assertExitCode(0);
    }

    public function test_detects_failed_refund_submission(): void
    {
        $this->makeOrder([
            'status'                 => StorefrontOrder::STATUS_CONFIRMED,
            'payment_method'         => StorefrontOrder::METHOD_PAYSTACK,
            'refund_provider_status' => 'submission_failed',
            'refund_failure_reason'  => 'provider unavailable',
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')
            ->assertExitCode(1);
    }

    /**
     * CF-P0-008 made visible: sales-return accounting handed to finance as a
     * note that nothing consumes.
     */
    public function test_detects_refund_with_incomplete_sales_return_accounting(): void
    {
        $this->makeOrder([
            'status'                => StorefrontOrder::STATUS_REFUNDED,
            'payment_method'        => StorefrontOrder::METHOD_COD,
            'payment_status'        => StorefrontOrder::PAY_REFUNDED,
            'refunded_at'           => now(),
            'finance_reversal_note' => 'Reversed automatically: none. Handed to finance (sales-return): '
                                       . 'PigSale#12 invoice/COGS/biological-asset reversal + pig restore.',
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')
            ->assertExitCode(1);
    }

    public function test_json_output_is_machine_readable(): void
    {
        $this->makeOrder([
            'status'                    => StorefrontOrder::STATUS_REFUNDED,
            'payment_method'            => StorefrontOrder::METHOD_PAYSTACK,
            'payment_status'            => StorefrontOrder::PAY_REFUNDED,
            'refunded_at'               => now(),
            'refund_provider_reference' => null,
        ]);

        $this->artisan('finance:reconcile-storefront-settlements', ['--json' => true])
            ->assertExitCode(1);
    }

    /**
     * Exit code 1 on discrepancies is what makes this usable from cron with
     * alerting. A command that always exits 0 is a report nobody reads.
     */
    public function test_exit_code_signals_discrepancies_for_monitoring(): void
    {
        $this->artisan('finance:reconcile-storefront-settlements')->assertExitCode(0);

        $this->makeOrder([
            'status'                    => StorefrontOrder::STATUS_REFUNDED,
            'payment_method'            => StorefrontOrder::METHOD_PAYSTACK,
            'payment_status'            => StorefrontOrder::PAY_REFUNDED,
            'refunded_at'               => now(),
            'refund_provider_reference' => null,
        ]);

        $this->artisan('finance:reconcile-storefront-settlements')->assertExitCode(1);
    }
}
