<?php

namespace Tests\Feature\Finance\Payments;

use App\Models\Finance\VendorInvoice;
use App\Services\Finance\Payments\WithholdingCalculator;
use Tests\TestCase;

/**
 * Pure unit tests — the VendorInvoice instantiation doesn't touch the DB
 * (we only set attributes via the property accessor), but the Eloquent
 * boot chain still expects the framework to be initialised, so we extend
 * the project TestCase rather than PHPUnit's bare TestCase.
 */
class WithholdingCalculatorTest extends TestCase
{
    protected WithholdingCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new WithholdingCalculator();
    }

    public function test_proportional_slice_basic(): void
    {
        $vi = $this->makeInvoice(total: '1000.00', whtTotal: '75.00', amountPaid: '0.00');

        // Pay half: slice should be half of 75 = 37.50
        $this->assertSame('37.50', $this->calc->slice($vi, '500.00', '0'));
    }

    public function test_zero_wht_returns_zero(): void
    {
        $vi = $this->makeInvoice(total: '1000.00', whtTotal: '0.00', amountPaid: '0.00');
        $this->assertSame('0.00', $this->calc->slice($vi, '500.00', '0'));
    }

    public function test_zero_vi_total_returns_zero_defensively(): void
    {
        $vi = $this->makeInvoice(total: '0.00', whtTotal: '50.00', amountPaid: '0.00');
        $this->assertSame('0.00', $this->calc->slice($vi, '500.00', '0'));
    }

    public function test_final_payment_uses_remainder_to_avoid_rounding_drift(): void
    {
        // 75.00 split across 4 equal payments of 250 on a 1000 bill.
        // Proportional: 250 * 75 / 1000 = 18.75, so each 250 slice = 18.75 (clean).
        // But take a trickier case:
        // 100 split across 3 equal payments on a 1000 bill where wht=100:
        //   First two: round(333.33 * 100 / 1000) = round(33.333) = 33.33
        //   Last: 333.34 to total 1000.00; final-payment branch returns
        //         100 - 33.33 - 33.33 = 33.34 (exact, no drift)
        $vi = $this->makeInvoice(total: '1000.00', whtTotal: '100.00', amountPaid: '666.67');
        // Final allocation of 333.33 brings amount_paid to exactly 1000.
        $slice = $this->calc->slice($vi, '333.33', '66.66');
        $this->assertSame('33.34', $slice, 'Final-payment branch returns exact remainder');
    }

    public function test_final_payment_when_already_withheld_covers_total(): void
    {
        // Edge case: previous rounding pushed alreadyWithheld over total.
        // Should clamp to 0 instead of returning negative.
        $vi = $this->makeInvoice(total: '1000.00', whtTotal: '50.00', amountPaid: '500.00');
        $slice = $this->calc->slice($vi, '500.00', '60.00');  // 60 > 50
        $this->assertSame('0.00', $slice, 'Clamps to non-negative');
    }

    public function test_proportional_rounds_correctly(): void
    {
        // 100 paid against a 333 bill with 25 WHT total.
        // Ratio: 100/333 = 0.3003... Slice: 0.3003 * 25 = 7.5075 → 7.51 (half-up)
        $vi = $this->makeInvoice(total: '333.00', whtTotal: '25.00', amountPaid: '0.00');
        $this->assertSame('7.51', $this->calc->slice($vi, '100.00', '0'));
    }

    public function test_full_payment_in_single_allocation_returns_full_wht(): void
    {
        // Pay the whole bill in one go. amountPaid + amount = total → final-payment branch.
        $vi = $this->makeInvoice(total: '1000.00', whtTotal: '75.00', amountPaid: '0.00');
        $this->assertSame('75.00', $this->calc->slice($vi, '1000.00', '0'));
    }

    public function test_negative_wht_total_returns_zero(): void
    {
        // Defensive: shouldn't happen but if a buggy caller produces
        // negative WHT, we return 0.
        $vi = $this->makeInvoice(total: '1000.00', whtTotal: '-5.00', amountPaid: '0.00');
        $this->assertSame('0.00', $this->calc->slice($vi, '500.00', '0'));
    }

    /* helper */
    protected function makeInvoice(string $total, string $whtTotal, string $amountPaid): VendorInvoice
    {
        $vi = new VendorInvoice();
        $vi->total = $total;
        $vi->withholding_total = $whtTotal;
        $vi->amount_paid = $amountPaid;
        return $vi;
    }
}
