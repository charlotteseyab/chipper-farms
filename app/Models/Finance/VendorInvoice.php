<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const STATUS_DRAFT             = 'draft';
    public const STATUS_PENDING_APPROVAL  = 'pending_approval';
    public const STATUS_POSTED            = 'posted';
    public const STATUS_CANCELLED         = 'cancelled';
    public const STATUS_REVERSED          = 'reversed';

    public const MATCH_PENDING            = 'pending';
    public const MATCH_CLEAN              = 'matched_clean';
    public const MATCH_VARIANCE           = 'matched_variance';

    protected $fillable = [
        'organization_id',
        'vendor_invoice_number',
        'supplier_invoice_number',
        'invoice_date',
        'due_date',
        'received_at',
        'counterparty_id',
        'currency_code',
        'exchange_rate',
        'source_type',
        'source_id',
        'purchase_request_id',
        'status',
        'journal_batch_id',
        'reversed_by_journal_batch_id',
        'subtotal',
        'tax_total',
        'withholding_total',
        'total',
        'amount_paid',
        'balance_due',
        'fully_paid_at',
        'match_status',
        'match_variances',
        'created_by_user_id',
        'submitted_for_approval_by_user_id',
        'submitted_for_approval_at',
        'approved_by_user_id',
        'approved_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'description',
        'notes',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'                => 'date',
            'due_date'                    => 'date',
            'received_at'                 => 'date',
            'exchange_rate'               => 'decimal:10',
            'subtotal'                    => 'decimal:2',
            'tax_total'                   => 'decimal:2',
            'withholding_total'           => 'decimal:2',
            'total'                       => 'decimal:2',
            'amount_paid'                 => 'decimal:2',
            'balance_due'                 => 'decimal:2',
            'fully_paid_at'               => 'datetime',
            'match_variances'             => 'array',
            'submitted_for_approval_at'   => 'datetime',
            'approved_at'                 => 'datetime',
            'reversed_at'                 => 'datetime',
            'cancelled_at'                => 'datetime',
        ];
    }

    /* relations */

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /** Alias for clarity in AP context. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorInvoiceLine::class)->orderBy('line_number');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function reversedByJournalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'reversed_by_journal_batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedForApprovalBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_for_approval_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function attachments(): MorphMany
    {
        // Reuses the polymorphic finance_attachments table from Phase 2d.
        return $this->morphMany(FinanceAttachment::class, 'attachable');
    }

    /* scopes */

    public function scopeDraft(Builder $q): Builder            { return $q->where('status', self::STATUS_DRAFT); }
    public function scopePendingApproval(Builder $q): Builder  { return $q->where('status', self::STATUS_PENDING_APPROVAL); }
    public function scopePosted(Builder $q): Builder           { return $q->where('status', self::STATUS_POSTED); }
    public function scopeCancelled(Builder $q): Builder        { return $q->where('status', self::STATUS_CANCELLED); }
    public function scopeReversed(Builder $q): Builder         { return $q->where('status', self::STATUS_REVERSED); }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED)
                 ->where('balance_due', '>', 0);
    }

    /* predicates */

    public function isDraft(): bool            { return $this->status === self::STATUS_DRAFT; }
    public function isPendingApproval(): bool  { return $this->status === self::STATUS_PENDING_APPROVAL; }
    public function isPosted(): bool           { return $this->status === self::STATUS_POSTED; }
    public function isCancelled(): bool        { return $this->status === self::STATUS_CANCELLED; }
    public function isReversed(): bool         { return $this->status === self::STATUS_REVERSED; }

    public function canSubmitForApproval(): bool
    {
        return $this->isDraft() && $this->lines()->exists();
    }

    public function canApprove(): bool
    {
        return $this->isPendingApproval();
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL], true);
    }

    public function canReverse(): bool
    {
        if (! $this->isPosted()) {
            return false;
        }
        if ($this->reversed_by_journal_batch_id !== null) {
            return false;
        }
        // Cannot reverse a VI that has payments allocated against it.
        // Reverse the payment allocations first (Phase 3c).
        if (bccomp((string) $this->amount_paid, '0', 2) > 0) {
            return false;
        }
        return true;
    }

    public function isFullyPaid(): bool
    {
        return $this->fully_paid_at !== null
            || bccomp((string) $this->balance_due, '0', 2) === 0;
    }

    public function isPartiallyPaid(): bool
    {
        return ! $this->isFullyPaid()
            && bccomp((string) $this->amount_paid, '0', 2) > 0;
    }

    public function hasMatchVariance(): bool
    {
        return $this->match_status === self::MATCH_VARIANCE;
    }
}
