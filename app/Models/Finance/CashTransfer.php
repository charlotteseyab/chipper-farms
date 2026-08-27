<?php

namespace App\Models\Finance;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An internal move of money between the farm's own accounts (Phase 5).
 *
 * See the migration for why this is neither income nor expense.
 */
class CashTransfer extends Model
{
    use SoftDeletes, \App\Models\Concerns\BelongsToOrganization;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_FAILED = 'failed';

    protected $table = 'cash_transfers';

    protected $fillable = [
        'organization_id', 'transfer_number', 'transfer_date',
        'from_account_id', 'to_account_id', 'amount', 'currency_code',
        'external_reference', 'description', 'notes', 'status',
        'journal_batch_id', 'recorded_by_user_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            // decimal:2 keeps the value a string so bcmath stays exact.
            'amount'        => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
