<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bank statement (imported file) — Phase 3e.
 *
 * One row per imported file. Lifecycle:
 *
 *   imported              - just imported, lines unmatched
 *   partially_reconciled  - some lines matched, some still open
 *   fully_reconciled      - every line is matched or manually_resolved,
 *                           AND linked to a closed reconciliation
 *   void                  - withdrawn (e.g. mis-imported file).
 *                           Lines remain for audit but excluded from
 *                           reconciliation arithmetic.
 *
 * Voiding rules:
 *   - If any line on the statement is matched, voiding is blocked.
 *     Unmatch first, then void. (Matching has GL implications via
 *     reconciled_at on payments/receipts; we don't want a void to
 *     silently leave dangling reconciled markers.)
 *   - If the statement is part of a closed reconciliation, voiding
 *     is blocked. Re-open the reconciliation first.
 */
class BankStatement extends Model
{
    use BelongsToOrganization;

    protected $table = 'bank_statements';

    public const STATUS_IMPORTED              = 'imported';
    public const STATUS_PARTIALLY_RECONCILED  = 'partially_reconciled';
    public const STATUS_FULLY_RECONCILED      = 'fully_reconciled';
    public const STATUS_VOID                  = 'void';

    public const ALL_STATUSES = [
        self::STATUS_IMPORTED,
        self::STATUS_PARTIALLY_RECONCILED,
        self::STATUS_FULLY_RECONCILED,
        self::STATUS_VOID,
    ];

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'statement_number',
        'source_format',
        'period_from',
        'period_to',
        'currency_code',
        'opening_balance_per_bank',
        'closing_balance_per_bank',
        'line_count',
        'total_credits',
        'total_debits',
        'original_filename',
        'file_disk',
        'file_path',
        'file_size_bytes',
        'file_sha256',
        'status',
        'bank_reconciliation_id',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
        'notes',
        'imported_by_user_id',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'period_from'              => 'date',
            'period_to'                => 'date',
            'opening_balance_per_bank' => 'decimal:2',
            'closing_balance_per_bank' => 'decimal:2',
            'total_credits'            => 'decimal:2',
            'total_debits'             => 'decimal:2',
            'line_count'               => 'integer',
            'file_size_bytes'          => 'integer',
            'voided_at'                => 'datetime',
            'imported_at'              => 'datetime',
        ];
    }

    /* relations */

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_statement_id')
                    ->orderBy('line_number');
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /* scopes */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_IMPORTED,
            self::STATUS_PARTIALLY_RECONCILED,
        ]);
    }

    public function scopeNotVoid(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_VOID);
    }

    /* predicates */

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isFullyReconciled(): bool
    {
        return $this->status === self::STATUS_FULLY_RECONCILED;
    }

    public function canVoid(): bool
    {
        return ! $this->isVoid()
            && ! $this->isFullyReconciled()
            && $this->lines()->whereIn('match_status', [
                BankStatementLine::STATUS_MATCHED,
                BankStatementLine::STATUS_MATCHED_TO_JOURNAL,
            ])->doesntExist();
    }

    /**
     * Counts of lines by match_status. Used for the index/show UI.
     *
     * @return array<string, int>
     */
    public function matchCountsByStatus(): array
    {
        $base = [
            BankStatementLine::STATUS_UNMATCHED          => 0,
            BankStatementLine::STATUS_MATCHED            => 0,
            BankStatementLine::STATUS_MATCHED_TO_JOURNAL => 0,
            BankStatementLine::STATUS_MANUALLY_RESOLVED  => 0,
            BankStatementLine::STATUS_DUPLICATE          => 0,
        ];
        $rows = $this->lines()
            ->selectRaw('match_status, COUNT(*) as c')
            ->groupBy('match_status')
            ->pluck('c', 'match_status')
            ->all();
        foreach ($rows as $status => $count) {
            $base[$status] = (int) $count;
        }
        return $base;
    }
}
