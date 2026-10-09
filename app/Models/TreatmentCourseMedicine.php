<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One medicine in a course: dose, route and the farm's own frequency. */
class TreatmentCourseMedicine extends Model
{
    use BelongsToOrganization;

    public const FREQUENCIES = ['once' => 'Once', 'every_n_hours' => 'Every N hours', 'daily' => 'Daily', 'every_n_days' => 'Every N days'];

    protected $fillable = [
        'organization_id', 'treatment_course_id', 'medicine_id', 'dose_amount', 'dose_unit', 'route',
        'frequency', 'interval', 'number_of_doses', 'start_at', 'end_at', 'instructions',
    ];

    protected $casts = ['dose_amount' => 'decimal:4', 'interval' => 'integer', 'number_of_doses' => 'integer', 'start_at' => 'datetime', 'end_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(TreatmentCourse::class, 'treatment_course_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function scheduledDoses(): HasMany
    {
        return $this->hasMany(ScheduledDose::class);
    }
}
