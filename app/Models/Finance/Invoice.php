<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\PaymentMethod;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_POSTED    = 'posted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED  = 'reversed';

    protected $fillable = [
        'organization_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'counterparty_id',
        'currency_code',
        'exchange_rate',
        'source_type',
        'source_id',
        'status',
        'journal_batch_id',
        'reversed_by_journal_batch_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'amount_paid',
        'balance_due',
        'first_payment_at',
        'last_payment_at',
        'fully_paid_at',
        'is_cash_invoice',
        'payment_method_id',
        'default_tax_code_id',
        'header_discount_percent',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'description',
        'notes',
        'reference',
        'internal_notes',
        'billing_address_snapshot',
        'shipping_address_snapshot',
        
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'             => 'date',
            'due_date'                 => 'date',
            'exchange_rate'            => 'decimal:10',
            'subtotal'                 => 'decimal:2',
            'discount_total'           => 'decimal:2',
            'tax_total'                => 'decimal:2',
            'total'                    => 'decimal:2',
            'amount_paid'              => 'decimal:2',
            'balance_due'              => 'decimal:2',
            'first_payment_at'         => 'datetime',
            'last_payment_at'          => 'datetime',
            'fully_paid_at'            => 'datetime',
            'is_cash_invoice'          => 'boolean',
            'header_discount_percent'  => 'decimal:2',
            'posted_at'                => 'datetime',
            'cancelled_at'             => 'datetime',
        ];
    }

    /* relations */

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function defaultTaxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'default_tax_code_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_number');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function reversedByJournalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'reversed_by_journal_batch_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /* scopes */

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED)
                 ->where('is_cash_invoice', false)
                 ->where('balance_due', '>', 0);
                 
    }

    public function scopeOverdue(Builder $q, ?\DateTimeInterface $asOf = null): Builder
    {
        $asOf ??= now();
        return $q->open()->whereDate('due_date', '<', $asOf->format('Y-m-d'));
    }

    /* status predicates */

    public function isDraft(): bool      { return $this->status === self::STATUS_DRAFT; }
    public function isPosted(): bool     { return $this->status === self::STATUS_POSTED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }
    public function isReversed(): bool   { return $this->status === self::STATUS_REVERSED; }

    /**
     * Can be edited?  Only drafts.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /**
     * Can be posted?  Only drafts.
     */
    public function canPost(): bool
    {
        return $this->isDraft();
    }

    /**
     * Can be cancelled?  Only drafts.
     */
    public function canCancel(): bool
    {
        return $this->isDraft();
    }

    /**
     * Can be reversed?  Only posted, not yet reversed.
     */
    public function canReverse(): bool
    {
        if (! $this->isPosted()) {
            return false;
        }
        if ($this->reversed_by_journal_batch_id !== null) {
            return false;
        }
        // New in 2c: cannot reverse an invoice with active allocations.
        // Reverse the allocations first.
        if ($this->activeAllocations()->doesntExist()) {
            return true;
        }
    } 

    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Finance\ReceiptAllocation::class);
    }

    public function activeAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Finance\ReceiptAllocation::class)
                    ->where('status', \App\Models\Finance\ReceiptAllocation::STATUS_ACTIVE);
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
}
