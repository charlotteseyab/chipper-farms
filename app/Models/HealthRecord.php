<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SetsOrganizationFromPig;

/**
 * A health case: one animal, or a frozen list of animals (health_record_animals),
 * with symptoms kept apart from a confirmed or unconfirmed diagnosis. The case
 * status is separate from any treatment course. Never hard-deleted.
 */
class HealthRecord extends Model implements \App\Models\Concerns\ResolvesOrganization
{
    use HasFactory, BelongsToOrganization, SetsOrganizationFromPig, SoftDeletes, \App\Models\Concerns\HasAttachments;

    public const SCOPE_INDIVIDUAL = 'individual';
    public const SCOPE_GROUP = 'group';

    /** Case statuses (decision D1). Finishing a course does not resolve a case; a person closes it. */
    public const CASE_STATUSES = ['open' => 'Open', 'under_treatment' => 'Under treatment', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    public const DIAGNOSIS_STATUSES = ['unconfirmed' => 'Unconfirmed', 'confirmed' => 'Confirmed'];

    protected static function organizationRelation(): string
    {
        return 'pig';
    }

    /** Fixed severity list (decision D2). Older free text is kept in legacy_severity. */
    public const SEVERITIES = ['mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe'];

    public const TREATMENT_STATUSES = ['planned' => 'Planned', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'stopped' => 'Stopped'];

    /** Outcomes that fit "Resolved — recovered, keep watching". */
    public const RESOLVABLE_OUTCOMES = ['recovered', 'improved'];

    public const OUTCOMES = ['recovered' => 'Recovered', 'improved' => 'Improved', 'unchanged' => 'Unchanged', 'worsened' => 'Worsened',
        'died' => 'Died', 'left_herd' => 'Sold, culled or transferred'];

    protected $fillable = [
        'pig_id',
        'record_date',
        'condition',
        'severity',
        'symptoms',
        'diagnosis',
        'treatment_plan',
        'treatment_status',
        'outcome',
        'resolved_at',
        'check_on',
        'resolution_notes',
        'recorded_by',
        'legacy_severity',
        'voided_at',
        'voided_by',
        'void_reason',
        'organization_id',
        'observed_at',
        'scope',
        'group_label_snapshot',
        'diagnosis_status',
        'case_status',
        'responsible_user_id',
        'closed_at',
        'closure_reason',
        'vet_examination_id',
    ];

    protected $casts = [
        'check_on' => 'date',
        'record_date' => 'date',
        'resolved_at' => 'date',
        'voided_at' => 'datetime',
        'observed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Records that still count. Voided ones stay visible in history only. */
    public function scopeNotVoided(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull($query->getModel()->qualifyColumn('voided_at'));
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function resolveOrganizationId(): ?int
    {
        $organizationId = $this->organization_id ?? $this->pig?->organization_id;

        return $organizationId === null ? null : (int) $organizationId;
    }

    /** The animals this case concerns, frozen when it was recorded. */
    public function animals(): HasMany
    {
        return $this->hasMany(HealthRecordAnimal::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(HealthFollowUp::class)->orderBy('observed_at');
    }

    public function treatmentCourses(): HasMany
    {
        return $this->hasMany(TreatmentCourse::class);
    }

    public function vetExamination(): BelongsTo
    {
        return $this->belongsTo(VetExamination::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

