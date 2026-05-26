<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A posted investor event linked to its GL journal batch.
 */
class InvestorTransaction extends Model
{
    use BelongsToOrganization;

    public const TYPE_CONTRIBUTION      = 'contribution';
    public const TYPE_ALLOCATION        = 'allocation';
    public const TYPE_DISTRIBUTION      = 'distribution';
    public const TYPE_PREFERRED_ACCRUAL = 'preferred_accrual';
    public const TYPE_PREFERRED_PAYMENT = 'preferred_payment';

    protected $fillable = [
        'organization_id',
        'investor_id',
        'capital_account_id',
        'txn_type',
        'txn_date',
        'amount',
        'journal_batch_id',
        'allocation_run_id',
        'bank_account_id',
        'created_by_user_id',
        'idempotency_key',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'amount'   => 'decimal:2',
        ];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function capitalAccount(): BelongsTo
    {
        return $this->belongsTo(InvestorCapitalAccount::class, 'capital_account_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function allocationRun(): BelongsTo
    {
        return $this->belongsTo(InvestorProfitAllocationRun::class, 'allocation_run_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
