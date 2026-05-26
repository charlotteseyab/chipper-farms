<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 4 — GR/IR accrual entry.
 *
 * The operational mirror of the 2050 GR/IR liability balance. One row
 * per PR-linked goods receipt that posted to GR/IR. Lifecycle:
 *
 *   OUTSTANDING (initial) → CLEARED  (AP invoice posted that matches)
 *                       └→ REVERSED  (underlying receipt was reversed)
 *
 * The presence of an OUTSTANDING row indicates "we owe this supplier
 * for goods received". The GR/IR ageing report sums these by supplier
 * and bucket.
 *
 * Cleared rows are retained for audit; they're filterable in the UI.
 */
class GrIrEntry extends Model
{
    use BelongsToOrganization;

    public const STATUS_OUTSTANDING = 'outstanding';
    public const STATUS_CLEARED     = 'cleared';
    public const STATUS_REVERSED    = 'reversed';

    public const ALLOWED_STATUSES = [
        self::STATUS_OUTSTANDING,
        self::STATUS_CLEARED,
        self::STATUS_REVERSED,
    ];

    protected $fillable = [
        'organization_id',
        'stock_log_id',
        'purchase_request_item_id',
        'supplier_id',
        'raw_material_id',
        'quantity',
        'receipt_unit_cost',
        'receipt_amount',
        'status',
        'occurred_at',
        'receipt_journal_batch_id',
        'clearing_journal_batch_id',
        'cleared_by_vendor_invoice_line_id',
        'cleared_at',
        'cleared_amount',
        'variance_amount',
    ];

    protected $casts = [
        'quantity'           => 'decimal:4',
        'receipt_unit_cost'  => 'decimal:6',
        'receipt_amount'     => 'decimal:4',
        'cleared_amount'     => 'decimal:4',
        'variance_amount'    => 'decimal:4',
        'occurred_at'        => 'datetime',
        'cleared_at'         => 'datetime',
    ];

    public function stockLog(): BelongsTo
    {
        return $this->belongsTo(RawMaterialStockLog::class, 'stock_log_id');
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'supplier_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function clearedByVendorInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(VendorInvoiceLine::class, 'cleared_by_vendor_invoice_line_id');
    }

    public function isOutstanding(): bool { return $this->status === self::STATUS_OUTSTANDING; }
    public function isCleared(): bool     { return $this->status === self::STATUS_CLEARED; }
    public function isReversed(): bool    { return $this->status === self::STATUS_REVERSED; }

    /**
     * Age of an outstanding entry in days. NULL for non-outstanding.
     */
    public function ageDays(): ?int
    {
        if (! $this->isOutstanding() || $this->occurred_at === null) {
            return null;
        }
        return (int) abs($this->occurred_at->diffInDays(now()));
    }
}
