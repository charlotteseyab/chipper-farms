<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_no',
        'purchase_type',
        'request_date',
        'requested_by',
        'status',
        'total_amount',
        'purpose',
        'reason',
        'notes',
        'evidence_image_path',
        'approved_by',
        'approved_at',
        'decline_reason',

        // Phase 0.8 finance-readiness (Batch D)
        'organization_id',
        'document_number',
        'is_posted',
        'posted_at',
        'supplier_id',
        'invoice_number',
        'invoice_date',
        'tax_code_id',
        'withholding_rate',
        'legacy_source_id',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_at'  => 'datetime',
        'total_amount' => 'decimal:2',

        // Phase 0.8
        'is_posted'        => 'boolean',
        'posted_at'        => 'datetime',
        'invoice_date'     => 'date',
        'withholding_rate' => 'decimal:4',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Phase 0.8 supplier link. Nullable until populated by Phase 1 backfill
     * or via the new PR form's supplier picker.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'supplier_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(\App\Models\Finance\VendorInvoice::class);
    }
}
