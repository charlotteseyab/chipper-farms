<?php

namespace App\Services\Health;

use App\Models\HealthRecord;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Write path for health records. Every change is audited with its before and
 * after values; nothing is deleted — a mistaken record is voided with a reason.
 */
class HealthRecordService
{
    /** Fields a record amendment may change. */
    public const AMENDABLE = [
        'condition', 'severity', 'symptoms', 'diagnosis', 'treatment_plan',
        'treatment_status', 'outcome', 'resolved_at', 'resolution_notes',
        'diagnosis_status', 'responsible_user_id',
    ];

    public function __construct(
        protected HealthEntryDateGuard $dates,
        protected AuditTrailService $audit,
    ) {
    }

    public function create(User $actor, PigManagement $pig, array $data, ?string $lateEntryReason = null): HealthRecord
    {
        return $this->openCase($actor, [$pig], $data, $lateEntryReason);
    }

    /**
     * Open a health case for one animal, or a group whose membership is frozen
     * now: later pen or room changes never alter which animals it concerns.
     *
     * @param  list<PigManagement>  $pigs
     */
    public function openCase(User $actor, array $pigs, array $data, ?string $lateEntryReason = null, ?int $vetExaminationId = null): HealthRecord
    {
        $pigs = collect($pigs)->unique('id')->values();
        if ($pigs->isEmpty()) {
            throw ValidationException::withMessages(['pig_ids' => 'Choose at least one animal.']);
        }
        $observedAt = \Carbon\CarbonImmutable::parse($data['observed_at'] ?? $data['record_date']);
        foreach ($pigs as $pig) {
            $this->dates->check($actor, $pig, $observedAt->toDateString(), $lateEntryReason, $pigs->count() > 1 ? 'pig_ids' : 'record_date');
        }
        $isGroup = $pigs->count() > 1;

        return DB::transaction(function () use ($actor, $pigs, $data, $lateEntryReason, $observedAt, $isGroup, $vetExaminationId) {
            $lead = $pigs->first();
            $record = HealthRecord::create(array_intersect_key($data, array_flip([...self::AMENDABLE, 'diagnosis_status', 'responsible_user_id'])) + [
                'pig_id'               => $lead->id,
                'record_date'          => $observedAt->toDateString(),
                'observed_at'          => $observedAt,
                'scope'                => $isGroup ? HealthRecord::SCOPE_GROUP : HealthRecord::SCOPE_INDIVIDUAL,
                'group_label_snapshot' => $isGroup ? ($data['group_label'] ?? null) : null,
                'case_status'          => 'open',
                'vet_examination_id'   => $vetExaminationId,
                'recorded_by'          => $actor->id,
            ]);
            foreach ($pigs as $pig) {
                $record->animals()->create(['pig_id' => $pig->id, 'tag_label_snapshot' => $pig->tagLabelAt($observedAt)]);
            }

            $codes = $pigs->pluck('code')->implode(', ');
            $this->audit->log('health', 'health_record_created', [
                'user'            => $actor,
                'auditable'       => $record,
                'organization_id' => $record->organization_id,
                'record_label'    => $lead->code,
                'description'     => ($isGroup ? "Group health case for {$pigs->count()} animals ({$codes})" : "Health record added for pig {$lead->code}")
                    .": {$record->condition}.".($lateEntryReason ? " Added after the pig left: {$lateEntryReason}" : ''),
                'new_values'      => $record->only(['record_date', 'observed_at', 'scope', ...self::AMENDABLE]) + ['animals' => $pigs->pluck('id')->all()],
            ]);

            return $record;
        });
    }

    /** Record how the case is going. A worsening is recorded, never auto-actioned. */
    public function addFollowUp(User $actor, HealthRecord $record, array $data): \App\Models\HealthFollowUp
    {
        return DB::transaction(function () use ($actor, $record, $data) {
            $record = $this->lockOpenCase($record);
            $observedAt = \Carbon\CarbonImmutable::parse($data['observed_at']);
            $this->assertNotBeforeCase($record, $observedAt, 'observed_at');

            $followUp = $record->followUps()->create([
                'observed_at' => $observedAt, 'response' => $data['response'] ?? null,
                'complications' => $data['complications'] ?? null, 'adverse_reaction' => (bool) ($data['adverse_reaction'] ?? false),
                'adverse_reaction_details' => $data['adverse_reaction_details'] ?? null, 'findings' => $data['findings'] ?? null,
                'recorded_by' => $actor->id,
            ]);
            $this->log($actor, $record, 'health_follow_up_recorded', "Follow-up recorded on case #{$record->id}.", [], $followUp->only(['observed_at', 'response', 'adverse_reaction']));

            return $followUp;
        });
    }

    /**
     * Resolve (recovered, still to be watched) or close (no further action) a
     * case with an outcome. Writes the closing follow-up. Finishing a course
     * never does this on its own (decision D1); a person decides.
     */
    public function close(User $actor, HealthRecord $record, array $data): HealthRecord
    {
        return DB::transaction(function () use ($actor, $record, $data) {
            $record = $this->lockOpenCase($record);
            $at = \Carbon\CarbonImmutable::parse($data['closed_at']);
            $this->assertNotBeforeCase($record, $at, 'closed_at');
            $status = $data['final_status'];
            $before = $record->only(['case_status', 'outcome', 'resolved_at', 'closed_at']);

            $record->followUps()->create([
                'observed_at' => $at, 'response' => $data['outcome'] === 'recovered' ? 'recovered' : null,
                'findings' => $data['closure_notes'] ?? null, 'is_closing' => true, 'recorded_by' => $actor->id,
            ]);
            $record->update([
                'case_status'      => $status,
                'outcome'          => $data['outcome'],
                'resolved_at'      => $at->toDateString(),
                'closed_at'        => $status === 'closed' ? $at : null,
                // Resolved: watched until a check on this date (worklist).
                'check_on'         => $status === 'resolved' ? ($data['check_on'] ?? $at->addDays(7)->toDateString()) : null,
                'closure_reason'   => $data['closure_notes'] ?? null,
                'resolution_notes' => $data['closure_notes'] ?? $record->resolution_notes,
            ]);
            $this->log($actor, $record, $status === 'closed' ? 'health_case_closed' : 'health_case_resolved',
                "Case #{$record->id} ".($status === 'closed' ? 'closed' : 'resolved')." with outcome {$data['outcome']}.", $before, $record->only(array_keys($before)));

            return $record->fresh();
        });
    }

    public function reopen(User $actor, HealthRecord $record, string $reason): HealthRecord
    {
        return DB::transaction(function () use ($actor, $record, $reason) {
            $record = HealthRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->isVoided() || ! in_array($record->case_status, ['resolved', 'closed'], true)) {
                throw new \DomainException('Only a resolved or closed case can be reopened.');
            }
            $before = $record->only(['case_status', 'closed_at']);
            $record->update(['case_status' => 'open', 'closed_at' => null, 'check_on' => null]);
            $this->log($actor, $record, 'health_case_reopened', "Case #{$record->id} reopened: {$reason}", $before, $record->only(array_keys($before)));

            return $record->fresh();
        });
    }

    /** A course was prescribed for an open case: it is now under treatment. */
    public function markUnderTreatment(HealthRecord $record): void
    {
        if ($record->case_status === 'open') {
            $record->update(['case_status' => 'under_treatment']);
        }
    }

    private function lockOpenCase(HealthRecord $record): HealthRecord
    {
        $record = HealthRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
        if ($record->isVoided()) {
            throw new \DomainException('This case has been voided.');
        }
        if (in_array($record->case_status, ['closed'], true)) {
            throw new \DomainException('This case is closed. Reopen it first.');
        }

        return $record;
    }

    private function assertNotBeforeCase(HealthRecord $record, \Carbon\CarbonImmutable $at, string $field): void
    {
        $start = $record->observed_at ?? $record->record_date;
        if ($at->lt(\Carbon\CarbonImmutable::parse($start)->startOfDay())) {
            throw ValidationException::withMessages([$field => 'The date cannot be before the case was recorded ('.\Carbon\CarbonImmutable::parse($start)->format('d M Y').').']);
        }
        if ($at->gt(now())) {
            throw ValidationException::withMessages([$field => 'The date cannot be in the future.']);
        }
    }

    private function log(User $actor, HealthRecord $record, string $action, string $description, array $before, array $after): void
    {
        $this->audit->log('health', $action, [
            'user' => $actor, 'auditable' => $record, 'organization_id' => $record->organization_id,
            'record_label' => $record->pig?->code, 'description' => $description,
            'old_values' => collect($before)->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v)->all(),
            'new_values' => collect($after)->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v)->all(),
        ]);
    }

    /** Amend one chosen record. Status updates use this too. */
    public function amend(User $actor, HealthRecord $record, array $data, string $action = 'health_record_amended'): HealthRecord
    {
        return DB::transaction(function () use ($actor, $record, $data, $action) {
            $record = HealthRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->isVoided()) {
                throw new \DomainException('This health record has been voided and can no longer be changed.');
            }

            $changes = array_intersect_key($data, array_flip(self::AMENDABLE));
            if (isset($changes['resolved_at']) && $changes['resolved_at'] !== null
                && $changes['resolved_at'] < $record->record_date->toDateString()) {
                throw ValidationException::withMessages(['resolved_at' => 'The resolved date cannot be before the record date ('.$record->record_date->format('d M Y').').']);
            }

            $before = $this->snapshot($record);
            $record->update($changes);

            $this->audit->logModelChanges($record, 'health', $action, $before, $this->snapshot($record->fresh()), [
                'user'            => $actor,
                'organization_id' => $record->resolveOrganizationId(),
                'record_label'    => $record->pig?->code,
                'description'     => "Health record #{$record->id} ({$record->condition}) updated.",
            ]);

            return $record->fresh();
        });
    }

    public function void(User $actor, HealthRecord $record, string $reason): HealthRecord
    {
        return DB::transaction(function () use ($actor, $record, $reason) {
            $record = HealthRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->isVoided()) {
                throw new \DomainException('This health record has already been voided.');
            }

            $record->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);

            $this->audit->log('health', 'health_record_voided', [
                'user'            => $actor,
                'auditable'       => $record,
                'organization_id' => $record->resolveOrganizationId(),
                'record_label'    => $record->pig?->code,
                'description'     => "Health record #{$record->id} ({$record->condition}) voided: {$reason}",
                'old_values'      => ['voided_at' => null],
                'new_values'      => ['voided_at' => (string) $record->voided_at, 'void_reason' => $reason],
            ]);

            return $record;
        });
    }

    private function snapshot(HealthRecord $record): array
    {
        return collect($record->only(self::AMENDABLE))
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)
            ->all();
    }
}
