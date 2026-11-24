<?php

namespace App\Services\Health;

use App\Models\HealthRecord;
use App\Models\Medicine;
use App\Models\PigManagement;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\TreatmentCourseMedicine;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prescribes treatment and preventive courses and keeps their dose schedule.
 *
 * Doses are scheduled from the course's own frequency and number of doses;
 * nothing is invented. Prescribing, amending and stopping never record a
 * dose or move stock. Amending creates a new course linked to the old one,
 * so the earlier instructions stay readable, and cancels the old course's
 * remaining scheduled doses with the reason "course amended".
 */
class TreatmentCourseService
{
    public function __construct(
        protected HealthEntryDateGuard $dates,
        protected HealthRecordService $cases,
        protected AuditTrailService $audit,
    ) {}

    /**
     * @param  list<PigManagement>  $pigs
     * @param  list<array{medicine_id: int, dose_amount: string, dose_unit: string, route: ?string, frequency: string, interval: ?int, number_of_doses: int, start_at: string, instructions: ?string}>  $medicines
     */
    public function prescribe(User $actor, array $data, array $pigs, array $medicines, ?TreatmentCourse $amends = null, ?string $amendmentReason = null): TreatmentCourse
    {
        $pigs = collect($pigs)->unique('id')->values();
        if ($pigs->isEmpty()) {
            throw ValidationException::withMessages(['pig_ids' => 'Choose at least one animal.']);
        }
        if ($medicines === []) {
            throw ValidationException::withMessages(['medicines' => 'Add at least one medicine.']);
        }
        foreach ($pigs as $pig) {
            if ($pig->trashed()) {
                throw ValidationException::withMessages(['pig_ids' => "{$pig->code} has left the farm and cannot be put on a course."]);
            }
        }
        $case = isset($data['health_record_id']) ? HealthRecord::findOrFail($data['health_record_id']) : null;
        if ($case && ($case->isVoided() || $case->case_status === 'closed')) {
            throw ValidationException::withMessages(['health_record_id' => 'This case is closed or voided. Reopen it before prescribing.']);
        }
        if ($case) {
            $members = $case->animals()->pluck('pig_id')->push($case->pig_id)->map(fn ($id) => (int) $id);
            $outside = $pigs->reject(fn ($pig) => $members->contains((int) $pig->id));
            if ($outside->isNotEmpty()) {
                throw ValidationException::withMessages(['pig_ids' => 'Not part of this case: '.$outside->pluck('code')->implode(', ').'.']);
            }
        }
        $lines = collect($medicines)->map(fn (array $line, int $i) => $this->validLine($line, $i));

        return DB::transaction(function () use ($actor, $data, $pigs, $lines, $case, $amends, $amendmentReason) {
            $course = TreatmentCourse::create([
                'health_record_id' => $case?->id ?? $amends?->health_record_id,
                'vet_examination_id' => $data['vet_examination_id'] ?? $amends?->vet_examination_id,
                'health_programme_id' => $data['health_programme_id'] ?? $amends?->health_programme_id,
                'purpose' => $data['purpose'],
                'prescriber_name' => $data['prescriber_name'] ?? null,
                'prescriber_user_id' => $data['prescriber_user_id'] ?? null,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'follow_up_on' => $data['follow_up_on'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
                'amended_from_course_id' => $amends?->id,
                'amendment_reason' => $amendmentReason,
                'created_by' => $actor->id,
            ]);
            $startedAt = CarbonImmutable::parse($lines->min('start_at'));
            foreach ($pigs as $pig) {
                $course->animals()->create(['pig_id' => $pig->id, 'tag_label_snapshot' => $pig->tagLabelAt($startedAt)]);
            }
            foreach ($lines as $line) {
                $dueTimes = $this->dueTimes($line);
                $courseMedicine = $course->medicines()->create($line + ['end_at' => end($dueTimes)]);
                foreach ($pigs as $pig) {
                    foreach ($dueTimes as $dueAt) {
                        $courseMedicine->scheduledDoses()->create(['pig_id' => $pig->id, 'due_at' => $dueAt, 'status' => ScheduledDose::SCHEDULED]);
                    }
                }
            }
            if ($course->health_record_id) {
                $this->cases->markUnderTreatment(HealthRecord::findOrFail($course->health_record_id));
            }

            $this->audit->log('health', $amends ? 'treatment_course_amended' : 'treatment_course_prescribed', [
                'user' => $actor, 'auditable' => $course, 'organization_id' => $course->organization_id,
                'record_label' => $pigs->pluck('code')->implode(', '),
                'description' => ($amends ? "Course #{$amends->id} amended as #{$course->id}: {$amendmentReason}" : "Course #{$course->id} prescribed ({$course->purpose})")
                    .' for '.$pigs->count().' animal(s).',
                'new_values' => ['medicines' => $lines->map(fn ($l) => array_intersect_key($l, array_flip(['medicine_id', 'dose_amount', 'dose_unit', 'route', 'frequency', 'interval', 'number_of_doses'])))->all(),
                    'animals' => $pigs->pluck('id')->all()],
            ]);

            return $course->load(['animals', 'medicines.scheduledDoses']);
        }, 3);
    }

    /** New version with the changes; the old course stops and its remaining doses are cancelled. */
    public function amend(User $actor, TreatmentCourse $course, array $data, array $pigs, array $medicines, string $reason): TreatmentCourse
    {
        return DB::transaction(function () use ($actor, $course, $data, $pigs, $medicines, $reason) {
            $course = $this->lockActive($course);
            $this->cancelRemaining($actor, $course, 'Course amended: '.$reason);
            $course->update(['status' => 'stopped', 'status_reason' => 'Amended: '.$reason]);

            return $this->prescribe($actor, $data + ['purpose' => $course->purpose], $pigs, $medicines, $course, $reason);
        }, 3);
    }

    public function stop(User $actor, TreatmentCourse $course, string $reason): TreatmentCourse
    {
        return DB::transaction(function () use ($actor, $course, $reason) {
            $course = $this->lockActive($course);
            $cancelled = $this->cancelRemaining($actor, $course, 'Course stopped: '.$reason);
            $course->update(['status' => 'stopped', 'status_reason' => $reason]);
            $this->audit->log('health', 'treatment_course_stopped', [
                'user' => $actor, 'auditable' => $course, 'organization_id' => $course->organization_id,
                'description' => "Course #{$course->id} stopped: {$reason}. {$cancelled} scheduled dose(s) cancelled.",
                'old_values' => ['status' => 'active'], 'new_values' => ['status' => 'stopped'],
            ]);

            return $course->fresh();
        }, 3);
    }

    /** @return list<CarbonImmutable> */
    public function dueTimes(array $line): array
    {
        $start = CarbonImmutable::parse($line['start_at']);
        $times = [];
        for ($i = 0; $i < (int) $line['number_of_doses']; $i++) {
            $times[] = match ($line['frequency']) {
                'once' => $start,
                'daily' => $start->addDays($i),
                'every_n_days' => $start->addDays($i * (int) $line['interval']),
                'every_n_hours' => $start->addHours($i * (int) $line['interval']),
            };
        }

        return $times;
    }

    private function validLine(array $line, int $i): array
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages(["medicines.$i.$field" => $message]);
        $medicine = Medicine::find($line['medicine_id'] ?? 0);
        if (! $medicine || ! $medicine->is_active) {
            $fail('medicine_id', 'Choose an active medicine from the catalogue.');
        }
        // One dose unit per medicine (decision D5): a different unit is refused, never converted.
        if (($line['dose_unit'] ?? null) !== $medicine->dose_unit) {
            $fail('dose_unit', "{$medicine->name} is dosed in {$medicine->dose_unit}.");
        }
        if (! is_numeric($line['dose_amount'] ?? null) || bccomp((string) $line['dose_amount'], '0', 4) <= 0) {
            $fail('dose_amount', 'Enter a dose above zero.');
        }
        $routes = (array) ($medicine->allowed_routes ?? []);
        if ($routes !== [] && ! empty($line['route']) && ! in_array($line['route'], $routes, true)) {
            $fail('route', "{$medicine->name} is given by: ".implode(', ', $routes).'.');
        }
        if (! array_key_exists($line['frequency'] ?? '', TreatmentCourseMedicine::FREQUENCIES)) {
            $fail('frequency', 'Choose how often the dose is given.');
        }
        if (in_array($line['frequency'], ['every_n_hours', 'every_n_days'], true) && (int) ($line['interval'] ?? 0) < 1) {
            $fail('interval', 'Enter the number of hours or days between doses.');
        }
        $count = $line['frequency'] === 'once' ? 1 : (int) ($line['number_of_doses'] ?? 0);
        if ($count < 1 || $count > 200) {
            $fail('number_of_doses', 'Enter how many doses, between 1 and 200.');
        }
        if (empty($line['start_at'])) {
            $fail('start_at', 'Enter when the first dose is due.');
        }

        return [
            'medicine_id' => $medicine->id, 'dose_amount' => (string) $line['dose_amount'], 'dose_unit' => $medicine->dose_unit,
            'route' => $line['route'] ?? null, 'frequency' => $line['frequency'],
            'interval' => in_array($line['frequency'], ['every_n_hours', 'every_n_days'], true) ? (int) $line['interval'] : null,
            'number_of_doses' => $count, 'start_at' => CarbonImmutable::parse($line['start_at']), 'instructions' => $line['instructions'] ?? null,
        ];
    }

    private function lockActive(TreatmentCourse $course): TreatmentCourse
    {
        $course = TreatmentCourse::whereKey($course->id)->lockForUpdate()->firstOrFail();
        if ($course->status !== 'active') {
            throw new \DomainException('Only an active course can be changed.');
        }

        return $course;
    }

    private function cancelRemaining(User $actor, TreatmentCourse $course, string $reason): int
    {
        return ScheduledDose::query()
            ->whereIn('treatment_course_medicine_id', $course->medicines()->pluck('id'))
            ->where('status', ScheduledDose::SCHEDULED)
            ->update(['status' => ScheduledDose::CANCELLED, 'status_reason' => $reason, 'status_changed_by' => $actor->id, 'status_changed_at' => now(), 'updated_at' => now()]);
    }
}
