<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SetsOrganizationFromPig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One animal the vet actually examined on a visit, with its own findings. */
class VetExamination extends Model
{
    use BelongsToOrganization, SetsOrganizationFromPig, \App\Models\Concerns\HasAttachments;

    public const ASSESSMENTS = [
        'no_issue_found'     => 'No issue found',
        'abnormal_findings'  => 'Abnormal findings',
        'pending_assessment' => 'Assessment pending',
    ];

    protected $fillable = [
        'organization_id', 'vet_visit_id', 'pig_id', 'tag_label_at_visit', 'examined_at', 'assessment',
        'findings', 'assessment_text', 'vet_notes', 'recommendations', 'tests_requested', 'test_results',
        'follow_up_on', 'follow_up_not_needed', 'health_record_id', 'repeat_number', 'repeat_of_examination_id',
        'entered_by_user_id', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'examined_at' => 'datetime', 'follow_up_on' => 'date', 'follow_up_not_needed' => 'boolean',
        'repeat_number' => 'integer', 'voided_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VetVisit::class, 'vet_visit_id');
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function healthRecord(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class);
    }

    public function repeatOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'repeat_of_examination_id');
    }
}
