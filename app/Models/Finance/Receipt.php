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
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'receipt_number',
        'receipt_date',
        'counterparty_id',
        'currency_code',
        'exchange_rate',
        'amount',
        'allocated_amount',
        'payment_method_id',
        'source_account_id',
        'external_reference',
        'reference_type',
        'status',
        'journal_batch_id',
        'reversed_by_journal_batch_id',
        'is_backfilled',
        'created_by_user_id',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'description',
        'notes',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date'     => 'date',
            'amount'           => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'exchange_rate'    => 'decimal:10',
            'is_backfilled'    => 'boolean',
            'reversed_at'      => 'datetime',
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

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    /**
     * Active allocations only — used for sum-of-active and "is fully allocated" checks.
     */
    public function activeAllocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class)
                    ->where('status', ReceiptAllocation::STATUS_ACTIVE);
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

    /* scopes */

    public function scopePosted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED);
    }

    public function scopeReversed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_REVERSED);
    }

    /**
     * Receipts with cash still unallocated — show under "customer credit" reports.
     */
    public function scopeWithUnallocated(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED)
                 ->whereColumn('allocated_amount', '<', 'amount');
    }

    public function scopeBackfilled(Builder $q): Builder
    {
        return $q->where('is_backfilled', true);
    }

    /* predicates */

    public function isPosted(): bool   { return $this->status === self::STATUS_POSTED; }
    public function isReversed(): bool { return $this->status === self::STATUS_REVERSED; }

    public function canReverse(): bool
    {
        return $this->isPosted() && $this->reversed_by_journal_batch_id === null;
    }

    /**
     * Cash still unattributed to specific invoices.
     * Computed from the denormalised allocated_amount column rather than
     * SUM(active allocations) for performance — drift is caught by the
     * reconciliation job.
     */
    public function unallocatedAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->allocated_amount, 2);
    }

    public function isFullyAllocated(): bool
    {
        return bccomp($this->unallocatedAmount(), '0', 2) === 0;
    }
}
