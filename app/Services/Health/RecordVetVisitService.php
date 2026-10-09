<?php

namespace App\Services\Health;

use App\Models\HealthRecord;
use App\Models\PigManagement;
use App\Models\User;
use App\Models\VetExamination;
use App\Models\VetVisit;
use App\Services\Audit\AuditTrailService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vet visits and the animals examined on them.
 *
 * Staff enter notes on the vet's behalf and are recorded as the author
 * (decision D13). Only animals with an examination count as examined; the
 * planned list never does. A pig is examined once per visit unless the
 * examination is saved as a deliberate repeat. After a visit is finalised,
 * changes need the finalise permission and a reason, and are audited.
 */
class RecordVetVisitService
{
    public function __construct(
        protected HealthRecordService $cases,
        protected AuditTrailService $audit,
    ) {
    }

    /** @param list<int> $plannedPigIds */
    public function create(User $actor, array $data, array $plannedPigIds = []): VetVisit
    {
        $this->assertNotFuture($data['visited_at'], 'visited_at');

        return DB::transaction(function () use ($actor, $data, $plannedPigIds) {
            $visit = VetVisit::create(array_intersect_key($data, array_flip(self::VISIT_FIELDS)) + [
                'entered_by_user_id' => $actor->id, 'status' => VetVisit::STATUS_DRAFT,
            ]);
            foreach (PigManagement::whereKey($plannedPigIds)->get() as $pig) {
                $visit->plannedAnimals()->create(['pig_id' => $pig->id]);
            }
            $this->log($actor, $visit, 'vet_visit_recorded', "Vet visit by {$visit->veterinarian_name} recorded.", [], $visit->only(self::VISIT_FIELDS));

            return $visit;
        });
    }

    public function update(User $actor, VetVisit $visit, array $data, ?string $reason = null): VetVisit
    {
        $this->assertNotFuture($data['visited_at'], 'visited_at');

        return DB::transaction(function () use ($actor, $visit, $data, $reason) {
            $visit = $this->lockForChange($actor, $visit, $reason);
            $before = $visit->only(self::VISIT_FIELDS);
            $visit->update(array_intersect_key($data, array_flip(self::VISIT_FIELDS)));
            $this->log($actor, $visit, $visit->isFinalised() ? 'vet_visit_amended' : 'vet_visit_updated',
                'Vet visit #'.$visit->id.' changed'.($reason ? ": {$reason}" : '.'), $before, $visit->only(self::VISIT_FIELDS));

            return $visit;
        });
    }

    /**
     * Record one animal's examination. An abnormal finding can open a new case
     * (linked both ways) or link an existing one.
     */
    public function examine(User $actor, VetVisit $visit, PigManagement $pig, array $data, ?string $reason = null): VetExamination
    {
        return DB::transaction(function () use ($actor, $visit, $pig, $data, $reason) {
            $visit = $this->lockForChange($actor, $visit, $reason);
            $examinedAt = CarbonImmutable::parse($data['examined_at'] ?? $visit->visited_at);
            $this->assertNotFuture($examinedAt, 'examined_at');

            $previous = VetExamination::where('vet_visit_id', $visit->id)->where('pig_id', $pig->id)->orderByDesc('repeat_number')->first();
            if ($previous && ! ($data['is_repeat'] ?? false)) {
                throw ValidationException::withMessages(['pig_id' => "{$pig->code} has already been examined on this visit. Tick \"repeat examination\" if the vet checked it again."]);
            }
            if (($data['follow_up_on'] ?? null) === null && ! ($data['follow_up_not_needed'] ?? false) && $data['assessment'] !== 'no_issue_found') {
                throw ValidationException::withMessages(['follow_up_on' => 'Give a follow-up date, or tick that no follow-up is needed.']);
            }

            $exam = VetExamination::create([
                'vet_visit_id' => $visit->id, 'pig_id' => $pig->id, 'tag_label_at_visit' => $pig->tagLabelAt($examinedAt),
                'examined_at' => $examinedAt, 'assessment' => $data['assessment'],
                'findings' => $data['findings'] ?? null, 'assessment_text' => $data['assessment_text'] ?? null,
                'vet_notes' => $data['vet_notes'] ?? null, 'recommendations' => $data['recommendations'] ?? null,
                'tests_requested' => $data['tests_requested'] ?? null, 'test_results' => $data['test_results'] ?? null,
                'follow_up_on' => $data['follow_up_on'] ?? null, 'follow_up_not_needed' => (bool) ($data['follow_up_not_needed'] ?? false),
                'repeat_number' => $previous ? $previous->repeat_number + 1 : 0, 'repeat_of_examination_id' => $previous?->id,
                'entered_by_user_id' => $actor->id,
            ]);

            if (! empty($data['health_record_id'])) {
                $case = HealthRecord::findOrFail($data['health_record_id']);
                if (! $case->animals()->where('pig_id', $pig->id)->exists() && (int) $case->pig_id !== (int) $pig->id) {
                    throw ValidationException::withMessages(['health_record_id' => 'That case is for a different animal.']);
                }
                $exam->update(['health_record_id' => $case->id]);
            } elseif (($data['open_case'] ?? false) && $data['assessment'] === 'abnormal_findings') {
                $case = $this->cases->openCase($actor, [$pig], [
                    'observed_at' => $examinedAt, 'condition' => $data['case_condition'] ?? ($data['assessment_text'] ?: 'Found at vet visit'),
                    'symptoms' => $data['findings'] ?? null, 'diagnosis' => $data['assessment_text'] ?? null,
                    'diagnosis_status' => ($data['assessment_text'] ?? null) ? 'unconfirmed' : null,
                ], vetExaminationId: $exam->id);
                $exam->update(['health_record_id' => $case->id]);
            }

            $this->log($actor, $visit, 'vet_examination_recorded',
                "{$pig->code} examined on visit #{$visit->id}: {$exam->assessment}".($reason ? ". Added after finalising: {$reason}" : '.'),
                [], $exam->only(['pig_id', 'assessment', 'follow_up_on', 'health_record_id', 'repeat_number']));

            return $exam->fresh();
        });
    }

    public function voidExamination(User $actor, VetExamination $exam, string $reason): VetExamination
    {
        return DB::transaction(function () use ($actor, $exam, $reason) {
            $this->lockForChange($actor, $exam->visit, $reason);
            if ($exam->voided_at) {
                throw new \DomainException('This examination has already been voided.');
            }
            $exam->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);
            $this->log($actor, $exam->visit, 'vet_examination_voided', "Examination #{$exam->id} voided: {$reason}", ['voided_at' => null], ['voided_at' => (string) $exam->voided_at]);

            return $exam;
        });
    }

    public function finalise(User $actor, VetVisit $visit): VetVisit
    {
        return DB::transaction(function () use ($actor, $visit) {
            $visit = VetVisit::whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($visit->isFinalised()) {
                throw new \DomainException('This visit is already finalised.');
            }
            if (! $actor->hasPermission('finalise_vet_visits')) {
                abort(403);
            }
            $visit->update(['status' => VetVisit::STATUS_FINALISED, 'finalised_at' => now(), 'finalised_by' => $actor->id]);
            $this->log($actor, $visit, 'vet_visit_finalised', "Vet visit #{$visit->id} finalised.", ['status' => 'draft'], ['status' => 'finalised']);

            return $visit;
        });
    }

    private const VISIT_FIELDS = ['visited_at', 'veterinarian_name', 'clinic', 'contact', 'room_id', 'location', 'reason', 'general_notes', 'recommendations'];

    /** Draft visits change freely; a finalised one needs the finalise permission and a reason. */
    private function lockForChange(User $actor, VetVisit $visit, ?string $reason): VetVisit
    {
        $visit = VetVisit::whereKey($visit->id)->lockForUpdate()->firstOrFail();
        if ($visit->voided_at) {
            throw new \DomainException('This visit has been voided.');
        }
        if ($visit->isFinalised()) {
            if (! $actor->hasPermission('finalise_vet_visits')) {
                abort(403, 'Only a manager can change a finalised visit.');
            }
            if (mb_strlen(trim((string) $reason)) < 5) {
                throw ValidationException::withMessages(['amendment_reason' => 'This visit is finalised. Say why it is being changed.']);
            }
        }

        return $visit;
    }

    private function assertNotFuture(mixed $date, string $field): void
    {
        if (CarbonImmutable::parse($date)->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages([$field => 'The date cannot be in the future.']);
        }
    }

    private function log(User $actor, VetVisit $visit, string $action, string $description, array $before, array $after): void
    {
        $this->audit->log('health', $action, [
            'user' => $actor, 'auditable' => $visit, 'organization_id' => $visit->organization_id, 'description' => $description,
            'old_values' => collect($before)->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v)->all(),
            'new_values' => collect($after)->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v)->all(),
        ]);
    }
}
