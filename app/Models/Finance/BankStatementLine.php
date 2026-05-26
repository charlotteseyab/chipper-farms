<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per bank-statement transaction — Phase 3e.
 *
 * Match status taxonomy:
 *   unmatched              just imported
 *   matched                exactly one of our payments / receipts matches
 *   matched_to_journal     matched to a manual GL journal_batch entry
 *                          (bank fees, interest, internal transfers)
 *   manually_resolved      explicitly accepted as ignorable with a note
 *                          (e.g. test transactions reversed by the bank)
 *   duplicate              multiple of our docs match the bank's ref;
 *                          duplicate_candidate_ids holds the list
 *
 * matched_type values:
 *   'payment'      → matched_id references payments.id
 *   'receipt'      → matched_id references receipts.id
 *   'journal_line' → matched_id references journal_batches.id
 *
 * We intentionally do NOT use Laravel morphTo because the existing
 * codebase doesn't register a global morph map, and adding one
 * partway through is invasive. The matchedDocument() accessor below
 * does runtime resolution based on matched_type. Same pattern as
 * SupplierStatementLine in Phase 3d.
 */
class BankStatementLine extends Model
{
    use BelongsToOrganization;

    protected $table = 'bank_statement_lines';

    public const STATUS_UNMATCHED          = 'unmatched';
    public const STATUS_MATCHED            = 'matched';
    public const STATUS_MATCHED_TO_JOURNAL = 'matched_to_journal';
    public const STATUS_MANUALLY_RESOLVED  = 'manually_resolved';
    public const STATUS_DUPLICATE          = 'duplicate';

    public const ALL_STATUSES = [
        self::STATUS_UNMATCHED,
        self::STATUS_MATCHED,
        self::STATUS_MATCHED_TO_JOURNAL,
        self::STATUS_MANUALLY_RESOLVED,
        self::STATUS_DUPLICATE,
    ];

    public const MATCHED_TYPE_PAYMENT      = 'payment';
    public const MATCHED_TYPE_RECEIPT      = 'receipt';
    public const MATCHED_TYPE_JOURNAL_LINE = 'journal_line';

    public const ALL_MATCHED_TYPES = [
        self::MATCHED_TYPE_PAYMENT,
        self::MATCHED_TYPE_RECEIPT,
        self::MATCHED_TYPE_JOURNAL_LINE,
    ];

    protected $fillable = [
        'organization_id',
        'bank_statement_id',
        'line_number',
        'value_date',
        'transaction_date',
        'description',
        'bank_reference',
        'counterparty_reference',
        'debit_amount',
        'credit_amount',
        'running_balance',
        'match_status',
        'matched_type',
        'matched_id',
        'amount_difference',
        'duplicate_candidate_ids',
        'match_notes',
        'matched_at',
        'matched_by_user_id',
        'raw_row',
    ];

    protected function casts(): array
    {
        return [
            'value_date'              => 'date',
            'transaction_date'        => 'date',
            'debit_amount'            => 'decimal:2',
            'credit_amount'           => 'decimal:2',
            'running_balance'         => 'decimal:2',
            'amount_difference'       => 'decimal:2',
            'duplicate_candidate_ids' => 'array',
            'raw_row'                 => 'array',
            'matched_at'              => 'datetime',
            'line_number'             => 'integer',
        ];
    }

    /* relations */

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }

    /**
     * Resolve the matched document at runtime based on matched_type.
     * Returns null if not matched or if the target row is missing.
     */
    public function matchedDocument(): ?Model
    {
        if (! $this->matched_id || ! $this->matched_type) {
            return null;
        }
        $modelClass = match ($this->matched_type) {
            self::MATCHED_TYPE_PAYMENT      => \App\Models\Finance\Payment::class,
            self::MATCHED_TYPE_RECEIPT      => \App\Models\Finance\Receipt::class,
            self::MATCHED_TYPE_JOURNAL_LINE => \App\Models\Finance\JournalBatch::class,
            default                         => null,
        };
        if (! $modelClass) return null;
        return $modelClass::find($this->matched_id);
    }

    /* scopes */

    public function scopeUnmatched(Builder $q): Builder
    {
        return $q->where('match_status', self::STATUS_UNMATCHED);
    }

    public function scopeMatched(Builder $q): Builder
    {
        return $q->whereIn('match_status', [
            self::STATUS_MATCHED,
            self::STATUS_MATCHED_TO_JOURNAL,
        ]);
    }

    public function scopeOpenForMatching(Builder $q): Builder
    {
        return $q->whereIn('match_status', [
            self::STATUS_UNMATCHED,
            self::STATUS_DUPLICATE,
        ]);
    }

    /* predicates */

    public function isUnmatched(): bool
    {
        return $this->match_status === self::STATUS_UNMATCHED;
    }

    public function isMatched(): bool
    {
        return in_array($this->match_status, [
            self::STATUS_MATCHED,
            self::STATUS_MATCHED_TO_JOURNAL,
        ], true);
    }

    public function isManuallyResolved(): bool
    {
        return $this->match_status === self::STATUS_MANUALLY_RESOLVED;
    }

    public function isDuplicate(): bool
    {
        return $this->match_status === self::STATUS_DUPLICATE;
    }

    /**
     * The signed amount: negative for debits (money out of our account),
     * positive for credits (money in). Useful for arithmetic.
     */
    public function signedAmount(): string
    {
        // bcsub will produce a properly-signed string at scale 2.
        return bcsub((string) $this->credit_amount, (string) $this->debit_amount, 2);
    }

    /**
     * The (positive) amount, regardless of direction. Used for matching.
     */
    public function absoluteAmount(): string
    {
        return bccomp((string) $this->debit_amount, '0', 2) > 0
            ? (string) $this->debit_amount
            : (string) $this->credit_amount;
    }

    public function isCredit(): bool
    {
        return bccomp((string) $this->credit_amount, '0', 2) > 0;
    }

    public function isDebit(): bool
    {
        return bccomp((string) $this->debit_amount, '0', 2) > 0;
    }
}
