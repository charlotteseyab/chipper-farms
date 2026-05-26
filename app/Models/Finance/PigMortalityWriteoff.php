<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Group;
use App\Models\PigDeath;
use App\Models\PigManagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PigMortalityWriteoff extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'pig_death_id',
        'pig_id',
        'group_id',
        'pig_weight_at_death_kg',
        'group_weight_before_kg',
        'group_value_before',
        'group_pig_count_before',
        'used_group_average_weight',
        'writeoff_amount',
        'occurred_at',
        'group_cost_movement_id',
        'journal_batch_id',
    ];

    protected $casts = [
        'pig_weight_at_death_kg'    => 'decimal:4',
        'group_weight_before_kg'    => 'decimal:4',
        'group_value_before'        => 'decimal:4',
        'group_pig_count_before'    => 'integer',
        'used_group_average_weight' => 'boolean',
        'writeoff_amount'           => 'decimal:4',
        'occurred_at'               => 'datetime',
    ];

    public function pigDeath(): BelongsTo
    {
        return $this->belongsTo(PigDeath::class);
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function groupCostMovement(): BelongsTo
    {
        return $this->belongsTo(GroupCostMovement::class);
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }
}
