<?php

namespace Tests\Feature\Finance\Phase2d;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Services\Finance\CreditControl\CreditControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class CreditControlServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected CreditControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service = $this->app->make(CreditControlService::class);
    }

    public function test_no_credit_limit_means_never_blocked(): void
    {
        $customer = $this->makeCustomer(creditLimit: null, holdMode: 'block');
        $this->makeOpenInvoice($customer, '1000000.00');

        $result = $this->service->evaluate($customer, '5000');

        $this->assertFalse($result->isOverLimit);
        $this->assertFalse($result->isBlocked());
        $this->assertFalse($result->shouldWarn());
    }

    public function test_under_limit_no_warning(): void
    {
        $customer = $this->makeCustomer(creditLimit: '10000', holdMode: 'warn');
        $this->makeOpenInvoice($customer, '5000.00');

        $result = $this->service->evaluate($customer, '1000');

        $this->assertFalse($result->isOverLimit);
        $this->assertFalse($result->shouldWarn());
        $this->assertFalse($result->isBlocked());
        $this->assertSame('emerald', $result->severityColor());
    }

    public function test_over_limit_warn_mode(): void
    {
        $customer = $this->makeCustomer(creditLimit: '10000', holdMode: 'warn');
        $this->makeOpenInvoice($customer, '8000.00');

        $result = $this->service->evaluate($customer, '5000');

        $this->assertTrue($result->isOverLimit);
        $this->assertTrue($result->shouldWarn());
        $this->assertFalse($result->isBlocked(), 'warn mode never blocks');
        $this->assertSame('amber', $result->severityColor());
        $this->assertNotNull($result->message);
    }

    public function test_over_limit_block_mode(): void
    {
        $customer = $this->makeCustomer(creditLimit: '10000', holdMode: 'block');
        $this->makeOpenInvoice($customer, '8000.00');

        $result = $this->service->evaluate($customer, '5000');

        $this->assertTrue($result->isOverLimit);
        $this->assertTrue($result->shouldWarn());
        $this->assertTrue($result->isBlocked(), 'block mode blocks when over limit');
        $this->assertSame('red', $result->severityColor());
    }

    public function test_over_limit_none_mode(): void
    {
        $customer = $this->makeCustomer(creditLimit: '10000', holdMode: 'none');
        $this->makeOpenInvoice($customer, '20000.00');

        $result = $this->service->evaluate($customer, '5000');

        $this->assertTrue($result->isOverLimit);
        $this->assertFalse($result->shouldWarn(), 'none mode never warns');
        $this->assertFalse($result->isBlocked(), 'none mode never blocks');
    }

    public function test_projected_balance_includes_new_transaction(): void
    {
        $customer = $this->makeCustomer(creditLimit: '10000', holdMode: 'warn');
        $this->makeOpenInvoice($customer, '6000.00');

        $result = $this->service->evaluate($customer, '5000');

        $this->assertSame('6000.00', $result->outstandingBalance);
        $this->assertSame('11000.00', $result->projectedBalance);
        $this->assertTrue($result->isOverLimit, '6000 + 5000 = 11000 > 10000');
    }

    public function test_zero_new_transaction_evaluates_outstanding_only(): void
    {
        $customer = $this->makeCustomer(creditLimit: '10000', holdMode: 'block');
        $this->makeOpenInvoice($customer, '12000.00');

        $result = $this->service->evaluate($customer, '0');

        $this->assertTrue($result->isOverLimit, 'already over limit before any new tx');
        $this->assertTrue($result->isBlocked());
    }

    public function test_default_hold_mode_when_unset(): void
    {
        $customer = $this->makeCustomer(creditLimit: '10000', holdMode: null);
        $this->makeOpenInvoice($customer, '15000.00');

        $result = $this->service->evaluate($customer);

        // When credit_hold_mode column is NULL, treat as 'warn' (default).
        $this->assertSame('warn', $result->holdMode);
    }

    /* helpers */

    protected function makeCustomer(?string $creditLimit, ?string $holdMode): Counterparty
    {
        static $i = 0;
        return Counterparty::create([
            'organization_id'  => $this->org->id,
            'name'             => 'C' . (++$i),
            'code'             => 'CC' . $i,
            'type'             => 'customer',
            'currency_code'    => 'GHS',
            'credit_limit'     => $creditLimit,
            'credit_hold_mode' => $holdMode,
        ]);
    }

    protected function makeOpenInvoice(Counterparty $customer, string $balance): Invoice
    {
        static $counter = 0;
        return Invoice::create([
            'organization_id'  => $this->org->id,
            'invoice_number'   => 'INV-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'invoice_date'     => '2026-05-15',
            'counterparty_id'  => $customer->id,
            'currency_code'    => 'GHS', 'exchange_rate' => '1',
            'status'           => Invoice::STATUS_POSTED,
            'is_cash_invoice'  => false,
            'subtotal'         => $balance, 'discount_total' => '0',
            'tax_total'        => '0',      'total'         => $balance,
            'amount_paid'      => '0',
            'balance_due'      => $balance,
            'created_by_user_id' => $this->user->id,
        ]);
    }
}
