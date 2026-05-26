<?php

namespace App\Services\Finance\VendorInvoicing;

use App\Models\Finance\VendorInvoice;
use App\Models\Finance\VendorInvoiceLine;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterialStockLog;
use Illuminate\Support\Collection;

/**
 * Phase 4 — three-way matching: PR ↔ receipt ↔ invoice.
 *
 * For each VI line linked to a PR item, evaluates whether the supplier's
 * invoice matches the originally-requested quantity/price AND whether
 * a goods receipt exists for the line. Variances are flagged but DO
 * NOT block AP posting (configurable behaviour at the policy level).
 *
 * REBUILT in Phase 4:
 *
 *   Previous version checked for raw_material_stock_logs.purchase_request_item_id
 *   at runtime via Schema::hasColumn() and returned an empty receipt set
 *   when the column didn't exist (which was always, before Phase 4). The
 *   `match_status` came back as 'matched_variance' with one 'no_receipt'
 *   variance per PR-linked line — silently dead, every time. Phase 4 adds
 *   the column properly and this service now reads it.
 *
 * VARIANCE TYPES emitted:
 *
 *   - pr_item_missing       PR item id on the VI line points to a row that
 *                           no longer exists. Operational data corruption.
 *
 *   - qty_pr_mismatch       Billed qty differs from PR qty by more than
 *                           the qty tolerance.
 *
 *   - price_pr_mismatch     Billed unit price differs from PR unit price
 *                           by more than the price tolerance.
 *
 *   - no_receipt            No raw_material_stock_logs row exists for the
 *                           PR item yet. Operations should not approve
 *                           until receipt is recorded.
 *
 *   - qty_receipt_mismatch  Billed qty differs from total received qty
 *                           (across all receipts for this PR line) by
 *                           more than the qty tolerance.
 *
 *   - raw_material_mismatch The PR item's raw_material_id differs from
 *                           every receipt's raw_material_id — the receipt
 *                           clerk attributed receipts to a different
 *                           material than the PR specified.
 *
 * MATCH STATUS:
 *
 *   null              No PR-linked lines on this invoice.
 *   matched_clean     All PR-linked lines passed every check.
 *   matched_variance  At least one variance was flagged.
 *
 * The status is INFORMATIONAL — VendorInvoicePostingService's own
 * tolerance check is what actually blocks posting for outside-tolerance
 * price variance. Three-way is for the human reviewer; posting tolerance
 * is the gate.
 */
class ThreeWayMatchingService
{
    public function evaluate(VendorInvoice $invoice): array
    {
        $invoice->loadMissing([
            'lines.purchaseRequestItem',
            'lines.purchaseRequestItem.rawMaterial',
            'purchaseRequest',
        ]);

        $tolQty = (float) config('finance.vendor_invoices.match_tolerance_qty_pct', 5.0);
        $tolPrice = (float) config('finance.vendor_invoices.match_tolerance_price_pct', 2.0);

        $variances = [];
        $hasMatchedLines = false;

        foreach ($invoice->lines as $line) {
            if (! $line->purchase_request_item_id) {
                continue;  // standalone — not subject to matching.
            }

            $hasMatchedLines = true;

            $prItem = $line->purchaseRequestItem;
            if (! $prItem) {
                $variances[] = $this->variance($line, 'pr_item_missing', 'Cited PR line not found');
                continue;
            }

            // Variance check 1 — billed qty vs PR qty
            $prQty   = (string) $prItem->quantity;
            $billQty = (string) $line->quantity;
            $qtyVarPct = $this->percentVariance($prQty, $billQty);
            if (abs($qtyVarPct) > $tolQty) {
                $variances[] = $this->variance(
                    $line,
                    'qty_pr_mismatch',
                    sprintf(
                        'PR qty %s, billed qty %s (%.2f%% variance, tolerance %.2f%%)',
                        $prQty,
                        $billQty,
                        $qtyVarPct,
                        $tolQty,
                    ),
                );
            }

            // Variance check 2 — billed price vs PR price
            $prPrice   = (string) $prItem->unit_price;
            $billPrice = (string) $line->unit_cost;
            $priceVarPct = $this->percentVariance($prPrice, $billPrice);
            if (abs($priceVarPct) > $tolPrice) {
                $variances[] = $this->variance(
                    $line,
                    'price_pr_mismatch',
                    sprintf(
                        'PR price %s, billed price %s (%.2f%% variance, tolerance %.2f%%)',
                        $prPrice,
                        $billPrice,
                        $priceVarPct,
                        $tolPrice,
                    ),
                );
            }

            // Variance check 3 — receipt presence
            $receivedLogs = $this->getReceivedLogsForPRItem($prItem);
            if ($receivedLogs->isEmpty()) {
                $variances[] = $this->variance(
                    $line,
                    'no_receipt',
                    'No raw_material_stock_logs row recorded for this PR line — goods not received yet',
                );
                continue;
            }

            // Variance check 4 — receipt raw material matches PR raw material.
            // Skipped when PR item has no raw_material_id (free-text PR
            // line). For raw-material-linked PR items, every receipt
            // MUST be for the same raw material.
            if ($prItem->raw_material_id) {
                $mismatched = $receivedLogs->filter(
                    fn (RawMaterialStockLog $log) => (int) $log->raw_material_id !== (int) $prItem->raw_material_id,
                );

                if ($mismatched->isNotEmpty()) {
                    $variances[] = $this->variance(
                        $line,
                        'raw_material_mismatch',
                        sprintf(
                            'PR item expects raw_material #%d but %d receipt(s) reference a different material',
                            (int) $prItem->raw_material_id,
                            $mismatched->count(),
                        ),
                    );
                }
            }

            // Variance check 5 — total received qty vs billed qty.
            $receivedQty = $receivedLogs->sum(fn (RawMaterialStockLog $log) => (float) $log->quantity);
            $receivedQty = number_format($receivedQty, 4, '.', '');
            $receiptVarPct = $this->percentVariance($receivedQty, $billQty);
            if (abs($receiptVarPct) > $tolQty) {
                $variances[] = $this->variance(
                    $line,
                    'qty_receipt_mismatch',
                    sprintf(
                        'Received qty %s, billed qty %s (%.2f%% variance, tolerance %.2f%%)',
                        $receivedQty,
                        $billQty,
                        $receiptVarPct,
                        $tolQty,
                    ),
                );
            }
        }

        $matchStatus = match (true) {
            ! $hasMatchedLines      => null,
            empty($variances)       => VendorInvoice::MATCH_CLEAN,
            default                 => VendorInvoice::MATCH_VARIANCE,
        };

        return [
            'match_status'    => $matchStatus,
            'match_variances' => $variances,
        ];
    }

    public function evaluateAndSave(VendorInvoice $invoice): VendorInvoice
    {
        $result = $this->evaluate($invoice);
        $invoice->update([
            'match_status'    => $result['match_status'],
            'match_variances' => $result['match_variances'],
        ]);
        return $invoice->fresh();
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * @return Collection<int, RawMaterialStockLog>
     */
    protected function getReceivedLogsForPRItem(PurchaseRequestItem $prItem): Collection
    {
        // Phase 4 — purchase_request_item_id is now a real column.
        return RawMaterialStockLog::query()
            ->where('purchase_request_item_id', $prItem->id)
            ->where('quantity', '>', 0)  // receipts only, not issues
            ->get();
    }

    /**
     * Signed percent variance: 100 × (actual - base) / |base|.
     */
    protected function percentVariance(string $base, string $actual): float
    {
        if (bccomp($base, '0', 4) === 0) {
            return bccomp($actual, '0', 4) === 0 ? 0.0 : 100.0;
        }
        $diff = bcsub($actual, $base, 8);
        $pct = bcmul(bcdiv($diff, $base, 8), '100', 8);
        return (float) $pct;
    }

    /**
     * @return array<string, mixed>
     */
    protected function variance(VendorInvoiceLine $line, string $type, string $detail): array
    {
        return [
            'line_id'     => $line->id,
            'line_number' => $line->line_number,
            'type'        => $type,
            'detail'      => $detail,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
