<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
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
        'payment_number',
        'payment_date',
        'currency_code',
        'exchange_rate',
        'counterparty_id',
        'amount',
        'allocated_amount',
        'withholding_amount',
        'net_amount',
        'payment_method_id',
        'source_account_id',
        'external_reference',
        'reference_type',
        'payment_run_id',
        'status',
        'journal_batch_id',
        'reversed_by_journal_batch_id',
        'is_backfilled',
        'reconciled_at',
        'reconciled_by_user_id',
        'reconciliation_reference',
        'created_by_user_id',
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
            'payment_date'        => 'date',
            'exchange_rate'       => 'decimal:10',
            'amount'              => 'decimal:2',
            'allocated_amount'    => 'decimal:2',
            'withholding_amount'  => 'decimal:2',
            'net_amount'          => 'decimal:2',
            'is_backfilled'       => 'boolean',
            'reconciled_at'       => 'datetime',
            'reversed_at'         => 'datetime',
            'cancelled_at'        => 'datetime',
        ];
    }

    /* relations */

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function paymentRun(): BelongsTo
    {
        return $this->belongsTo(PaymentRun::class);
    }

    /** Active allocations only — the default view of "what's allocated" */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)
                    ->where('status', PaymentAllocation::STATUS_ACTIVE);
    }

    /** All allocations including reversed ones — for audit views. */
    public function allAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
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

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(FinanceAttachment::class, 'attachable');
    }

    /* scopes */

    public function scopeDraft(Builder $q): Builder        { return $q->where('status', self::STATUS_DRAFT); }
    public function scopePosted(Builder $q): Builder       { return $q->where('status', self::STATUS_POSTED); }
    public function scopeCancelled(Builder $q): Builder    { return $q->where('status', self::STATUS_CANCELLED); }
    public function scopeReversed(Builder $q): Builder     { return $q->where('status', self::STATUS_REVERSED); }

    public function scopeUnallocated(Builder $q): Builder
    {
        // payment.amount = net cash leaving bank
        // payment.net_amount = sum of allocation NET (cash actually attributed)
        // Unallocated when the latter < former (suspense balance > 0).
        return $q->where('status', self::STATUS_POSTED)
                 ->whereColumn('net_amount', '<', 'amount');
    }

    public function scopeUnreconciled(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED)
                 ->whereNull('reconciled_at');
    }

    public function scopeReconciled(Builder $q): Builder
    {
        return $q->whereNotNull('reconciled_at');
    }

    /* predicates */

    public function isDraft(): bool      { return $this->status === self::STATUS_DRAFT; }
    public function isPosted(): bool     { return $this->status === self::STATUS_POSTED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }
    public function isReversed(): bool   { return $this->status === self::STATUS_REVERSED; }

    public function canReverse(): bool
    {
        if (! $this->isPosted()) {
            return false;
        }
        if ($this->is_backfilled) {
            // Backfilled payments don't have a journal to reverse.
            return false;
        }
        if ($this->reversed_by_journal_batch_id !== null) {
            return false;
        }
        return true;
    }

    public function canCancel(): bool
    {
        return $this->isDraft();
    }

    /**
     * Net cash NOT YET attributed to a vendor invoice.
     *
     * payment.amount      = net cash that left bank (suspense holds it)
     * payment.net_amount  = sum of active allocation NETs (cash claimed back from suspense)
     *
     * unallocatedAmount() = amount - net_amount
     *                     = the suspense balance still sitting against this payment.
     */
    public function unallocatedAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->net_amount, 2);
    }

    public function isFullyAllocated(): bool
    {
        return bccomp($this->unallocatedAmount(), '0', 2) === 0;
    }

    public function hasUnallocatedAmount(): bool
    {
        return bccomp($this->unallocatedAmount(), '0', 2) > 0;
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }
}
