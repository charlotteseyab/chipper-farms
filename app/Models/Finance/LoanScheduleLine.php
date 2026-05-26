<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled instalment in a loan's amortisation schedule.
 */
class LoanScheduleLine extends Model
{
    use BelongsToOrganization;

    public const STATUS_PENDING        = 'pending';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID           = 'paid';

    protected $fillable = [
        'organization_id',
        'loan_id',
        'period_number',
        'due_date',
        'opening_balance',
        'interest_due',
        'principal_due',
        'total_due',
        'closing_balance',
        'interest_paid',
        'principal_paid',
        'interest_accrued',
        'status',
        'paid_date',
    ];

    protected function casts(): array
    {
        return [
            'period_number'    => 'integer',
            'due_date'         => 'date',
            'opening_balance'  => 'decimal:2',
            'interest_due'     => 'decimal:2',
            'principal_due'    => 'decimal:2',
            'total_due'        => 'decimal:2',
            'closing_balance'  => 'decimal:2',
            'interest_paid'    => 'decimal:2',
            'principal_paid'   => 'decimal:2',
            'interest_accrued' => 'boolean',
            'paid_date'        => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
