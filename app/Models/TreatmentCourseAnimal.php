<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SetsOrganizationFromPig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One animal a course is for, frozen when the course was saved. */
class TreatmentCourseAnimal extends Model
{
    use BelongsToOrganization, SetsOrganizationFromPig;

    protected $fillable = ['organization_id', 'treatment_course_id', 'pig_id', 'tag_label_snapshot'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(TreatmentCourse::class, 'treatment_course_id');
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }
}
