<?php

namespace Tests\Feature\Finance\Invoices;

use App\Services\Finance\Invoicing\InvoiceLineDraft;
use Tests\TestCase;

class InvoiceLineDraftTest extends TestCase
{
    public function test_simple_line_computes_subtotal(): void
    {
        $line = new InvoiceLineDraft(
            description: 'Pig sale',
            quantity:    '50.00',
            unitPrice:   '12.50',
        );

        $this->assertSame('625.00', $line->lineSubtotal);
        $this->assertSame('0.00', $line->lineDiscountAmount);
        $this->assertSame('0.00', $line->lineTaxAmount);
        $this->assertSame('625.00', $line->lineTotal);
    }

    public function test_line_with_discount(): void
    {
        $line = new InvoiceLineDraft(
            description:     'With discount',
            quantity:        '10',
            unitPrice:       '100',
            discountPercent: '10',
        );

        $this->assertSame('1000.00', $line->lineSubtotal);
        $this->assertSame('100.00', $line->lineDiscountAmount);
        $this->assertSame('900.00', $line->lineTotal);
    }

    public function test_line_with_tax(): void
    {
        $line = new InvoiceLineDraft(
            description:    'With VAT',
            quantity:       '1',
            unitPrice:      '100',
            taxRatePercent: '15',  // Ghana VAT
        );

        $this->assertSame('100.00', $line->lineSubtotal);
        $this->assertSame('15.00', $line->lineTaxAmount);
        $this->assertSame('115.00', $line->lineTotal);
    }

    public function test_line_with_discount_and_tax(): void
    {
        $line = new InvoiceLineDraft(
            description:     'Discount before tax',
            quantity:        '1',
            unitPrice:       '100',
            discountPercent: '10',  // 100 → 90
            taxRatePercent:  '15',  // 90 * 15% = 13.50
        );

        $this->assertSame('100.00', $line->lineSubtotal);
        $this->assertSame('10.00', $line->lineDiscountAmount);
        $this->assertSame('13.50', $line->lineTaxAmount);
        $this->assertSame('103.50', $line->lineTotal);
    }

    public function test_zero_quantity_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InvoiceLineDraft(description: 'x', quantity: '0', unitPrice: '100');
    }

    public function test_negative_quantity_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InvoiceLineDraft(description: 'x', quantity: '-1', unitPrice: '100');
    }

    public function test_negative_price_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InvoiceLineDraft(description: 'x', quantity: '1', unitPrice: '-50');
    }

    public function test_zero_price_allowed(): void
    {
        // Zero-price line is allowed (free sample, promo).
        $line = new InvoiceLineDraft(description: 'Free sample', quantity: '1', unitPrice: '0');
        $this->assertSame('0.00', $line->lineTotal);
    }

    public function test_discount_over_100_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InvoiceLineDraft(description: 'x', quantity: '1', unitPrice: '100', discountPercent: '150');
    }

    public function test_decimal_precision_rounds_half_up(): void
    {
        // 0.005 rounds up to 0.01 (banker's rounding would round to 0.00).
        $line = new InvoiceLineDraft(
            description: 'rounding',
            quantity:    '1',
            unitPrice:   '0.005',
        );
        $this->assertSame('0.01', $line->lineSubtotal);
    }
}
