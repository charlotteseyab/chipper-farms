<?php

namespace App\Services\Finance\VendorInvoicing;

/**
 * Immutable draft of one vendor invoice line. Pre-persistence shape used
 * by VendorInvoiceService to construct a vendor invoice atomically.
 *
 * Computes its own line totals at construction. Amounts are strings to
 * preserve decimal precision through bcadd/bccomp/bcmul.
 *
 * Account resolution does NOT happen here — the draft accepts an
 * account_id directly, or NULL. The VendorInvoiceService runs the
 * resolution hierarchy (line override → raw_material → supplier →
 * category → COA fallback) before persisting.
 *
 * Withholding semantics:
 *   withholding_rate is applied to line_subtotal (NOT line_total —
 *   withholding doesn't apply to tax). Result is stored as
 *   withholding_amount; this is INFORMATIONAL on the invoice. The
 *   actual withholding journal happens at payment time (Phase 3c)
 *   when the cash leaves the business.
 */
final class VendorInvoiceLineDraft
{
    public readonly string $lineSubtotal;
    public readonly string $lineTaxAmount;
    public readonly string $lineWithholdingAmount;
    public readonly string $lineTotal;

    public function __construct(
        public readonly string $description,
        public readonly string $quantity,            // decimal-as-string
        public readonly string $unitCost,            // decimal-as-string
        public readonly ?string $unit = null,
        public readonly ?int $rawMaterialId = null,
        public readonly ?int $purchaseRequestItemId = null,
        public readonly string $taxRate = '0',
        public readonly ?int $taxCodeId = null,
        public readonly string $withholdingRate = '0',
        public readonly ?int $withholdingTaxCodeId = null,
        public readonly ?int $accountId = null,
    ) {
        if (trim($description) === '') {
            throw new \InvalidArgumentException('VendorInvoiceLineDraft description must not be empty');
        }
        if (bccomp($quantity, '0', 4) <= 0) {
            throw new \InvalidArgumentException("VendorInvoiceLineDraft quantity must be > 0, got {$quantity}");
        }
        if (bccomp($unitCost, '0', 4) < 0) {
            throw new \InvalidArgumentException("VendorInvoiceLineDraft unitCost must be >= 0, got {$unitCost}");
        }
        if (bccomp($taxRate, '0', 4) < 0 || bccomp($taxRate, '100', 4) > 0) {
            throw new \InvalidArgumentException("VendorInvoiceLineDraft taxRate must be 0..100, got {$taxRate}");
        }
        if (bccomp($withholdingRate, '0', 4) < 0 || bccomp($withholdingRate, '100', 4) > 0) {
            throw new \InvalidArgumentException("VendorInvoiceLineDraft withholdingRate must be 0..100, got {$withholdingRate}");
        }

        // line_subtotal = quantity * unit_cost, rounded to 2dp
        $subtotal = bcmul($quantity, $unitCost, 8);
        $this->lineSubtotal = $this->roundToCents($subtotal);

        // tax_amount = line_subtotal * tax_rate / 100, rounded to 2dp
        $taxAmt = bcdiv(bcmul($this->lineSubtotal, $taxRate, 8), '100', 8);
        $this->lineTaxAmount = $this->roundToCents($taxAmt);

        // withholding_amount = line_subtotal * withholding_rate / 100, rounded to 2dp
        // Note: applied to subtotal, NOT to subtotal+tax (WHT doesn't apply on VAT).
        $whtAmt = bcdiv(bcmul($this->lineSubtotal, $withholdingRate, 8), '100', 8);
        $this->lineWithholdingAmount = $this->roundToCents($whtAmt);

        // line_total = line_subtotal + tax_amount
        // Withholding does NOT reduce line_total — it's a payment-time deduction.
        $this->lineTotal = $this->roundToCents(bcadd($this->lineSubtotal, $this->lineTaxAmount, 8));
    }

    private function roundToCents(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
