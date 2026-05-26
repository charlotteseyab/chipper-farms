<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A posted loan event (disbursement, accrual, repayment, adjustment),
 * linked to its GL journal batch.
 */
class LoanTransaction extends Model
{
    use BelongsToOrganization;

    public const TYPE_DISBURSEMENT = 'disbursement';
    public const TYPE_ACCRUAL      = 'accrual';
    public const TYPE_REPAYMENT    = 'repayment';
    public const TYPE_ADJUSTMENT   = 'adjustment';

    protected $fillable = [
        'organization_id',
        'loan_id',
        'schedule_line_id',
        'txn_type',
        'txn_date',
        'principal_amount',
        'interest_amount',
        'fee_amount',
        'penalty_amount',
        'total_amount',
        'journal_batch_id',
        'bank_account_id',
        'created_by_user_id',
        'idempotency_key',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'txn_date'         => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount'  => 'decimal:2',
            'fee_amount'       => 'decimal:2',
            'penalty_amount'   => 'decimal:2',
            'total_amount'     => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function scheduleLine(): BelongsTo
    {
        return $this->belongsTo(LoanScheduleLine::class, 'schedule_line_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
