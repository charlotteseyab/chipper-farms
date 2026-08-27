<?php

namespace App\Models;

use App\Models\Finance\GrIrEntry;
use App\Models\Finance\VendorInvoiceLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Phase 4 — extends fillable + casts + relations for the new columns
 * added by Phase 4 migrations:
 *   - purchase_request_item_id   : the PR line this receipt fulfils
 *   - vendor_invoice_line_id     : the VI line that cleared the receipt
 *   - posting_status / posting_error / journal_batch_id
 *   - receipt_reason             : reason for non-PR receipts
 *
 * is_posted remains as a denormalised convenience flag (true when
 * posting_status='posted'). Kept for backward compat with prior reports.
 */
class RawMaterialStockLog extends Model
{
    use \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'raw_material_id',
        'type',
        'quantity',
        'unit_cost',
        'reference',
        'notes',
        'performed_by',

        // Phase 0.8 finance-readiness (Batch D)
        'organization_id',
        'document_number',
        'is_posted',
        'posted_at',
        'supplier_id',
        'cost_centre_id',
        'legacy_source_id',

        // Phase 4
        'purchase_request_item_id',
        'vendor_invoice_line_id',
        'posting_status',
        'posting_error',
        'journal_batch_id',
        'receipt_reason',
    ];

    protected $casts = [
        'quantity'  => 'decimal:2',
        'unit_cost' => 'decimal:2',

        // Phase 0.8
        'is_posted' => 'boolean',
        'posted_at' => 'datetime',
    ];

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'supplier_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class, 'cost_centre_id');
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function vendorInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(VendorInvoiceLine::class, 'vendor_invoice_line_id');
    }

    public function grIrEntry(): HasOne
    {
        return $this->hasOne(GrIrEntry::class, 'stock_log_id');
    }
}
