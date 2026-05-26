<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subledger row behind 3100 Investor Capital Accounts. Sum of current_balance
 * across rows must equal the 3100 GL control balance.
 */
class InvestorCapitalAccount extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'investor_id',
        'label',
        'total_contributed',
        'total_allocated',
        'total_distributed',
        'current_balance',
        'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'total_contributed' => 'decimal:2',
            'total_allocated'   => 'decimal:2',
            'total_distributed' => 'decimal:2',
            'current_balance'   => 'decimal:2',
            'last_movement_at'  => 'datetime',
        ];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
