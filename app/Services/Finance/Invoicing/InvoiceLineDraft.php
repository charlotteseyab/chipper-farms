<?php

namespace App\Services\Finance\Invoicing;

/**
 * Immutable draft of one invoice line. Pre-persistence shape used by
 * InvoiceService to construct an invoice atomically.
 *
 * Computes its own line totals at construction. Amounts are strings to
 * preserve decimal precision through bccomp/bcadd.
 *
 * Discount semantics:
 *   If discount_percent is set, discount_amount is computed as
 *     line_subtotal * discount_percent / 100.
 *   If both are passed explicitly, discount_percent takes precedence —
 *   prevents the caller from supplying internally-inconsistent values.
 */
final class InvoiceLineDraft
{
    public readonly string $lineSubtotal;
    public readonly string $lineDiscountAmount;
    public readonly string $lineTaxAmount;
    public readonly string $lineTotal;

    public function __construct(
        public readonly string $description,
        public readonly string $quantity,            // decimal-as-string
        public readonly string $unitPrice,           // decimal-as-string
        public readonly string $unitOfMeasure = 'unit',
        public readonly ?string $itemCode = null,
        public readonly ?int $pigId = null,
        public readonly string $discountPercent = '0',
        public readonly string $taxRatePercent = '0',
        public readonly ?int $taxCodeId = null,
        public readonly ?int $revenueAccountId = null,
        public readonly ?int $costCentreId = null,
        public readonly ?int $salesChannelId = null,
        /**
         * A tax amount the CALLER already computed (IMP-091).
         *
         * `taxRatePercent` can only express a single percentage. Ghana's 2026
         * regime charges VAT, NHIL and GETFund on a shared base, and a profile
         * may also carry a fixed levy or a component that compounds on the
         * others — none of which a single rate can represent, and recomputing
         * from an "effective rate" would round differently from the figure the
         * buyer was actually charged.
         *
         * So a caller holding a `TaxCalculation` passes the amount instead.
         * When this is non-null it IS the line tax and `taxRatePercent` is
         * ignored; the two are mutually exclusive by construction below rather
         * than by convention.
         */
        public readonly ?string $taxAmount = null,
    ) {
        // Validate quantity > 0.
        if (bccomp($quantity, '0', 4) <= 0) {
            throw new \InvalidArgumentException("InvoiceLineDraft quantity must be > 0, got {$quantity}");
        }
        if (bccomp($unitPrice, '0', 4) < 0) {
            throw new \InvalidArgumentException("InvoiceLineDraft unitPrice must be >= 0, got {$unitPrice}");
        }
        if (bccomp($discountPercent, '0', 2) < 0 || bccomp($discountPercent, '100', 2) > 0) {
            throw new \InvalidArgumentException("InvoiceLineDraft discountPercent must be 0..100, got {$discountPercent}");
        }
        if (bccomp($taxRatePercent, '0', 2) < 0) {
            throw new \InvalidArgumentException("InvoiceLineDraft taxRatePercent must be >= 0, got {$taxRatePercent}");
        }
        if ($taxAmount !== null && bccomp($taxAmount, '0', 2) < 0) {
            throw new \InvalidArgumentException("InvoiceLineDraft taxAmount must be >= 0, got {$taxAmount}");
        }
        // Refuse rather than silently preferring one. A caller supplying both
        // has two different ideas about the tax and only one of them can be
        // right; picking quietly is how the invoice ends up disagreeing with
        // the receipt.
        if ($taxAmount !== null && bccomp($taxRatePercent, '0', 2) > 0) {
            throw new \InvalidArgumentException(
                'InvoiceLineDraft accepts taxRatePercent OR taxAmount, not both.'
            );
        }

        // Compute totals. Round each to 2 decimals so the persisted values
        // match what the customer sees on the printed invoice.
        $subtotal = bcmul($quantity, $unitPrice, 8);
        $subtotal = $this->roundToCents($subtotal);

        $discount = bcdiv(bcmul($subtotal, $discountPercent, 8), '100', 8);
        $discount = $this->roundToCents($discount);

        $netOfDiscount = bcsub($subtotal, $discount, 8);

        if ($taxAmount !== null) {
            $tax = $this->roundToCents($taxAmount);
        } else {
            $tax = bcdiv(bcmul($netOfDiscount, $taxRatePercent, 8), '100', 8);
            $tax = $this->roundToCents($tax);
        }

        $total = bcadd(bcsub($subtotal, $discount, 2), $tax, 2);

        $this->lineSubtotal       = $subtotal;
        $this->lineDiscountAmount = $discount;
        $this->lineTaxAmount      = $tax;
        $this->lineTotal          = $total;
    }

    /**
     * Round half-up to 2 decimal places for cash-precision values.
     * bcmath itself truncates by default — banker's rounding is overkill
     * for invoice totals; half-up is the conventional billing rule.
     */
    private function roundToCents(string $value): string
    {
        // Strip sign, work with abs, then re-apply.
        $negative = bccomp($value, '0', 8) < 0;
        $abs = $negative ? bcmul($value, '-1', 8) : $value;

        // Add 0.005 then truncate to 2 decimal places.
        $bumped = bcadd($abs, '0.005', 8);
        $rounded = bcadd($bumped, '0', 2);

        return $negative ? bcmul($rounded, '-1', 2) : $rounded;
    }

    /**
     * Render as the array shape expected by InvoiceLine::create().
     */
    public function toPersistedArray(int $invoiceId, int $lineNumber, ?int $organizationId): array
    {
        return [
            'organization_id'        => $organizationId,
            'invoice_id'             => $invoiceId,
            'line_number'            => $lineNumber,
            'description'            => $this->description,
            'item_code'              => $this->itemCode,
            'pig_id'                 => $this->pigId,
            'quantity'               => $this->quantity,
            'unit_of_measure'        => $this->unitOfMeasure,
            'unit_price'             => $this->unitPrice,
            'line_subtotal'          => $this->lineSubtotal,
            'line_discount_percent'  => $this->discountPercent,
            'line_discount_amount'   => $this->lineDiscountAmount,
            'tax_code_id'            => $this->taxCodeId,
            'line_tax_amount'        => $this->lineTaxAmount,
            'line_total'             => $this->lineTotal,
            'revenue_account_id'     => $this->revenueAccountId,
            'cost_centre_id'         => $this->costCentreId,
            'sales_channel_id'       => $this->salesChannelId,
        ];
    }
}
