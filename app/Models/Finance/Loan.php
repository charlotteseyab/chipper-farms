<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A loan facility. Terms here; schedule in loan_schedule_lines; posted
 * events in loan_transactions.
 */
class Loan extends Model
{
    use BelongsToOrganization;

    public const TYPE_BANK      = 'bank';
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_INVESTOR  = 'investor';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SETTLED   = 'settled';
    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_REDUCING_BALANCE = 'reducing_balance';
    public const METHOD_STRAIGHT_LINE    = 'straight_line';

    protected $fillable = [
        'organization_id',
        'reference',
        'loan_type',
        'lender_name',
        'counterparty_id',
        'principal_amount',
        'annual_interest_rate',
        'term_months',
        'amortisation_method',
        'disbursement_date',
        'first_payment_date',
        'origination_fee',
        'status',
        'bank_account_id',
        'disbursement_batch_id',
        'principal_outstanding',
        'interest_accrued_outstanding',
        'total_interest_charged',
        'total_principal_repaid',
        'created_by_user_id',
        'disbursed_at',
        'settled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount'             => 'decimal:2',
            'annual_interest_rate'         => 'decimal:6',
            'term_months'                  => 'integer',
            'disbursement_date'            => 'date',
            'first_payment_date'           => 'date',
            'origination_fee'              => 'decimal:2',
            'principal_outstanding'        => 'decimal:2',
            'interest_accrued_outstanding' => 'decimal:2',
            'total_interest_charged'       => 'decimal:2',
            'total_principal_repaid'       => 'decimal:2',
            'disbursed_at'                 => 'datetime',
            'settled_at'                   => 'datetime',
        ];
    }

    public function scheduleLines(): HasMany
    {
        return $this->hasMany(LoanScheduleLine::class)->orderBy('period_number');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoanTransaction::class)->orderBy('txn_date');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Config block for this loan's type.
     *
     * @return array<string, mixed>
     */
    public function typeConfig(): array
    {
        return (array) config("loans.types.{$this->loan_type}", []);
    }
}
