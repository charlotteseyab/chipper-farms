<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** That a programme has scheduled an animal's dose for one occurrence. */
class HealthProgrammeDose extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'health_programme_id', 'pig_id', 'occurrence_key', 'due_on', 'scheduled_dose_id'];

    protected $casts = ['due_on' => 'date'];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(HealthProgramme::class, 'health_programme_id');
    }

    public function scheduledDose(): BelongsTo
    {
        return $this->belongsTo(ScheduledDose::class);
    }
}
