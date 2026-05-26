<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bank reconciliation — Phase 3e.
 *
 * Lifecycle:
 *
 *   draft        - just created, no matching done
 *   in_progress  - actively matching lines
 *   reconciled   - balances agree, formally closed; locks matched
 *                  payments/receipts from reversal
 *   flagged      - balances don't agree but closing anyway with reason
 *   archived     - moved out of working set
 *
 * Period-freeze invariant (analogous to D40 in Phase 3d):
 *   While status = reconciled, payments/receipts whose
 *   bank_reconciliation_id points to this row CANNOT be reversed.
 *   The corresponding services consult `isLocked()` before allowing
 *   reversal. To reverse a reconciled payment, transition the
 *   reconciliation back to in_progress first (unreconcile).
 */
class BankReconciliation extends Model
{
    use BelongsToOrganization;

    protected $table = 'bank_reconciliations';

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RECONCILED  = 'reconciled';
    public const STATUS_FLAGGED     = 'flagged';
    public const STATUS_ARCHIVED    = 'archived';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RECONCILED,
        self::STATUS_FLAGGED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'reconciliation_number',
        'period_from',
        'period_to',
        'currency_code',
        'opening_balance_per_bank',
        'closing_balance_per_bank',
        'opening_balance_per_books',
        'closing_balance_per_books',
        'outstanding_deposits',
        'outstanding_withdrawals',
        'bank_only_credits',
        'bank_only_debits',
        'reconciliation_difference',
        'status',
        'reconciled_at',
        'reconciled_by_user_id',
        'flagged_at',
        'flagged_by_user_id',
        'flag_reason',
        'archived_at',
        'archived_by_user_id',
        'archive_reason',
        'summary_snapshot',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_from'                => 'date',
            'period_to'                  => 'date',
            'opening_balance_per_bank'   => 'decimal:2',
            'closing_balance_per_bank'   => 'decimal:2',
            'opening_balance_per_books'  => 'decimal:2',
            'closing_balance_per_books'  => 'decimal:2',
            'outstanding_deposits'       => 'decimal:2',
            'outstanding_withdrawals'    => 'decimal:2',
            'bank_only_credits'          => 'decimal:2',
            'bank_only_debits'           => 'decimal:2',
            'reconciliation_difference'  => 'decimal:2',
            'reconciled_at'              => 'datetime',
            'flagged_at'                 => 'datetime',
            'archived_at'                => 'datetime',
            'summary_snapshot'           => 'array',
        ];
    }

    /* relations */

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class, 'bank_reconciliation_id');
    }

    public function clearedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'bank_reconciliation_id');
    }

    public function clearedReceipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'bank_reconciliation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function flaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by_user_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /* scopes */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    public function scopeNotArchived(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function scopeReconciled(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_RECONCILED);
    }

    /* predicates */

    public function isDraft(): bool       { return $this->status === self::STATUS_DRAFT; }
    public function isInProgress(): bool  { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isReconciled(): bool  { return $this->status === self::STATUS_RECONCILED; }
    public function isFlagged(): bool     { return $this->status === self::STATUS_FLAGGED; }
    public function isArchived(): bool    { return $this->status === self::STATUS_ARCHIVED; }

    public function canEdit(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_IN_PROGRESS,
        ], true);
    }

    /**
     * Whether this reconciliation locks its matched documents from
     * reversal. Used by Payment / Receipt reversal services.
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_RECONCILED;
    }

    public function balancesAgree(): bool
    {
        return bccomp((string) $this->reconciliation_difference, '0', 2) === 0;
    }

    public function canReconcile(): bool
    {
        return $this->canEdit() && $this->balancesAgree();
    }
}
