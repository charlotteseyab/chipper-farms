<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A check on a case's progress; the closing follow-up carries the outcome. */
class HealthFollowUp extends Model
{
    use BelongsToOrganization, \App\Models\Concerns\HasAttachments;

    public const RESPONSES = ['improving' => 'Improving', 'unchanged' => 'Unchanged', 'worse' => 'Worse', 'recovered' => 'Recovered'];

    protected $fillable = [
        'organization_id', 'health_record_id', 'observed_at', 'response', 'complications', 'adverse_reaction',
        'adverse_reaction_details', 'findings', 'is_closing', 'recorded_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = ['observed_at' => 'datetime', 'adverse_reaction' => 'boolean', 'is_closing' => 'boolean', 'voided_at' => 'datetime'];

    public function healthRecord(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
