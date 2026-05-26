<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header for a profit-allocation run. One completed run per (year, scope)
 * prevents double allocation.
 */
class InvestorProfitAllocationRun extends Model
{
    use BelongsToOrganization;

    public const METHOD_OWNERSHIP_PCT  = 'ownership_pct';
    public const METHOD_FIXED_SCHEDULE = 'fixed_schedule';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REVERSED  = 'reversed';

    protected $fillable = [
        'organization_id',
        'fiscal_year_id',
        'method',
        'profit_pool',
        'allocation_date',
        'status',
        'reversed_by_run_id',
        'performed_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'profit_pool'     => 'decimal:2',
            'allocation_date' => 'date',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestorTransaction::class, 'allocation_run_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
