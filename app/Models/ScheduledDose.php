<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SetsOrganizationFromPig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dose due for one animal. Missing or cancelling it never moves stock. */
class ScheduledDose extends Model
{
    use BelongsToOrganization, SetsOrganizationFromPig;

    public const SCHEDULED = 'scheduled';
    public const ADMINISTERED = 'administered';
    public const MISSED = 'missed';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id', 'treatment_course_medicine_id', 'pig_id', 'due_at', 'status', 'status_reason',
        'status_changed_by', 'status_changed_at', 'medication_administration_id',
    ];

    protected $casts = ['due_at' => 'datetime', 'status_changed_at' => 'datetime'];

    public function courseMedicine(): BelongsTo
    {
        return $this->belongsTo(TreatmentCourseMedicine::class, 'treatment_course_medicine_id');
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function administration(): BelongsTo
    {
        return $this->belongsTo(MedicationAdministration::class, 'medication_administration_id');
    }
}
