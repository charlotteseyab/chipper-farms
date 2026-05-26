<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-(group, stage) carrying value — the subledger behind the 1310–1370
 * biological-asset stage control accounts.
 *
 * Invariant: for a given group, SUM(carrying_value across stage rows) ==
 * the group's GroupCostPool.total_carrying_value. The reconciliation report
 * asserts this.
 */
class BiologicalAssetStageBalance extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'group_id',
        'stage_key',
        'carrying_value',
        'pig_count',
        'weight_kg',
        'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'carrying_value'   => 'decimal:4',
            'weight_kg'        => 'decimal:4',
            'pig_count'        => 'integer',
            'last_movement_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
