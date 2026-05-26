<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Supplier statement reconciliation header.
 *
 * Captures one printed/emailed statement from a supplier covering a
 * period, plus the reconciliation outcome.
 *
 * Lifecycle:
 *   draft       — created, lines being added
 *   in_progress — match attempted, may have unresolved items
 *   reconciled  — all lines matched, balances agree
 *   flagged     — discrepancies remain (recorded as known issue)
 *   archived    — historical record, no longer actionable
 *
 * @see SupplierStatementLine
 * @see \App\Services\Finance\SupplierStatements\SupplierStatementService
 * @see \App\Services\Finance\SupplierStatements\StatementReconciliationService
 */
class SupplierStatement extends Model
{
    use HasFactory;
    use BelongsToOrganization;

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
        'counterparty_id',
        'statement_number',
        'statement_date',
        'period_from',
        'period_to',
        'currency_code',
        'supplier_balance',
        'our_balance',
        'balance_difference',
        'status',
        'supplier_reference',
        'notes',
        'reconciliation_notes',
        'last_reconciled_at',
        'last_reconciled_by_user_id',
        'created_by_user_id',
        'archived_by_user_id',
        'archived_at',
        'archive_reason',
    ];

    protected function casts(): array
    {
        return [
            'statement_date'      => 'date',
            'period_from'         => 'date',
            'period_to'           => 'date',
            'supplier_balance'    => 'decimal:2',
            'our_balance'         => 'decimal:2',
            'balance_difference'  => 'decimal:2',
            'last_reconciled_at'  => 'datetime',
            'archived_at'         => 'datetime',
        ];
    }

    /* relations */

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /** Convenience alias since suppliers are counterparties. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierStatementLine::class)
            ->orderBy('line_number')
            ->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lastReconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reconciled_by_user_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /* scopes */

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_IN_PROGRESS,
            self::STATUS_FLAGGED,
        ]);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    /* predicates */

    public function isDraft(): bool       { return $this->status === self::STATUS_DRAFT; }
    public function isInProgress(): bool  { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isReconciled(): bool  { return $this->status === self::STATUS_RECONCILED; }
    public function isFlagged(): bool     { return $this->status === self::STATUS_FLAGGED; }
    public function isArchived(): bool    { return $this->status === self::STATUS_ARCHIVED; }

    public function canEdit(): bool
    {
        return ! $this->isArchived();
    }

    public function canArchive(): bool
    {
        return ! $this->isArchived();
    }

    /** Has the balance section been reconciled? */
    public function balancesAgree(): bool
    {
        return bccomp((string) $this->balance_difference, '0', 2) === 0;
    }

    /**
     * Count lines per match status. Used in show view for the summary
     * banner. Returns array keyed by status with int counts (0 for any
     * status with no lines).
     */
    public function lineCountsByStatus(): array
    {
        $counts = [
            'unmatched'         => 0,
            'matched'           => 0,
            'amount_mismatch'   => 0,
            'not_in_our_books'  => 0,
            'duplicate_match'   => 0,
            'manually_resolved' => 0,
        ];

        foreach ($this->lines as $line) {
            if (isset($counts[$line->match_status])) {
                $counts[$line->match_status]++;
            }
        }
        return $counts;
    }

    /**
     * Are there any unresolved items? Used to gate the "mark reconciled"
     * action.
     */
    public function hasUnresolvedItems(): bool
    {
        $counts = $this->lineCountsByStatus();
        return $counts['unmatched'] > 0
            || $counts['amount_mismatch'] > 0
            || $counts['not_in_our_books'] > 0
            || $counts['duplicate_match'] > 0;
    }
}
