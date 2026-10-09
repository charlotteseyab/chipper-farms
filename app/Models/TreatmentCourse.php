<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A treatment plan or a preventive course, with or without a health case.
 * Amending creates a new course linked to this one, so earlier instructions
 * stay readable. Prescribing never records a dose or moves stock.
 */
class TreatmentCourse extends Model
{
    use BelongsToOrganization, HasFactory;

    public const PURPOSES = ['treatment' => 'Treatment', 'vaccination' => 'Vaccination', 'deworming' => 'Deworming', 'other' => 'Other'];

    public const STATUSES = ['active' => 'Active', 'completed' => 'Completed', 'stopped' => 'Stopped'];

    protected $fillable = [
        'organization_id', 'health_record_id', 'vet_examination_id', 'purpose', 'prescriber_name', 'prescriber_user_id',
        'responsible_user_id', 'follow_up_on', 'status', 'status_reason', 'notes', 'amended_from_course_id',
        'amendment_reason', 'created_by',
    ];

    protected $casts = ['follow_up_on' => 'date'];

    public function healthRecord(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(TreatmentCourseAnimal::class);
    }

    public function medicines(): HasMany
    {
        return $this->hasMany(TreatmentCourseMedicine::class);
    }

    public function amendedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amended_from_course_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
