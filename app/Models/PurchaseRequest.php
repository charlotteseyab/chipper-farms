<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    // See PigSale: PurchaseRequestPolicy enforces tenancy, so the write path
    // must stamp it. The controller already set organization_id explicitly on
    // store(); the trait closes the gap for every other creation path
    // (CF-P0-005).
    use \App\Models\Concerns\BelongsToOrganization;

    use HasFactory;

    protected $fillable = [
        'request_no',
        'purchase_type',
        'request_date',
        'requested_by',
        'status',
        'payment_arrangement',
        'deposit_percent',
        'payment_progress',
        'completed_at',
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
        'deposit_percent'  => 'decimal:2',
        'completed_at'     => 'datetime',
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

    public function payments(): HasMany
    {
        return $this->hasMany(\App\Models\Finance\Payment::class);
    }

    public function requiresAdvancePayment(): bool
    {
        return in_array($this->payment_arrangement, ['before_delivery', 'deposit_balance'], true);
    }

    public function paymentArrangementLabel(): string
    {
        return match ($this->payment_arrangement) {
            'before_delivery' => 'Pay before delivery',
            'deposit_balance' => 'Deposit and balance',
            'supplier_credit' => 'Supplier credit',
            default => 'Pay after delivery',
        };
    }

    /** Derive the operational payment state from posted finance payments. */
    public function syncPaymentProgressFromFinance(): self
    {
        $paid = (float) $this->payments()
            ->where('status', \App\Models\Finance\Payment::STATUS_POSTED)
            ->sum('amount');
        $total = (float) $this->total_amount;
        $progress = $paid <= 0
            ? 'not_paid'
            : ($paid + 0.005 >= $total ? 'paid' : 'partially_paid');

        $changes = ['payment_progress' => $progress];
        if (in_array($this->status, ['approved', 'paid'], true)) {
            $changes['status'] = $progress === 'paid' ? 'paid' : 'approved';
        }

        $this->update($changes);

        return $this->fresh();
    }
}
