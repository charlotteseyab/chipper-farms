<?php

namespace App\Services\Health;

use App\Models\Finance\InventoryMovement;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\MedicationDosingAllocation;
use App\Models\MedicationDosingEvent;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PigManagement;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Finance\Inventory\InsufficientInventoryException;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place medicine stock is taken.
 *
 * One submission is one dosing event: stock is taken once for every animal
 * dosed, from the batch the user chose or from the earliest-expiring usable
 * batches (D6), never from an expired one and never below zero. Each animal
 * gets its own dose row copying the medicine's name, units and withdrawal at
 * that moment, so later catalogue edits never change it and the shared
 * withdrawal query keeps working. The form's idempotency key means a double
 * click or retry returns the first event instead of deducting twice.
 */
class RecordDoseService
{
    /** Stock quantities and per-animal shares are kept to 4 decimal places. */
    private const SCALE = 4;

    public function __construct(
        protected InventoryService $inventory,
        protected MedicineStockService $stock,
        protected HealthEntryDateGuard $dates,
        protected HealthRecordService $cases,
        protected AuditTrailService $audit,
    ) {}

    /**
     * @param  array{medicine_id: int, administered_at: string, route?: ?string, amount_basis?: string, dose_amount: string,
     *               dose_unit: string, batch_id?: ?int, purpose?: ?string, notes?: ?string, health_record_id?: ?int,
     *               scheduled_dose_ids?: list<int>, idempotency_key: string, late_entry_reason?: ?string}  $data
     * @param  iterable<PigManagement>  $pigs
     * @param  array{corrects?: MedicationDosingEvent, reason?: string, administered_by?: ?int, originals?: Collection}  $correction
     */
    public function record(User $actor, array $data, iterable $pigs, array $correction = []): MedicationDosingEvent
    {
        $pigs = collect($pigs)->unique('id')->values();
        $corrects = $correction['corrects'] ?? null;
        if (! $corrects && ($existing = $this->existing($data['idempotency_key']))) {
            return $existing;
        }

        $medicine = Medicine::findOrFail($data['medicine_id']);
        $at = CarbonImmutable::parse($data['administered_at']);
        $this->validate($actor, $data, $medicine, $pigs, $at, $corrects);
        [$perAnimal, $totalStock] = $this->amounts($data, $medicine, $pigs->count());
        $case = ! empty($data['health_record_id']) ? HealthRecord::findOrFail($data['health_record_id']) : null;
        if ($case) {
            $this->assertCaseCovers($case, $pigs, (bool) $corrects);
        }

        try {
            return DB::transaction(function () use ($actor, $data, $pigs, $medicine, $at, $perAnimal, $totalStock, $case, $corrects, $correction) {
                $scheduled = $this->lockScheduledDoses($data['scheduled_dose_ids'] ?? [], $medicine, $pigs);
                $plan = $this->planBatches($medicine, $data['batch_id'] ?? null, $totalStock, $at);
                $courseId = $scheduled->first()?->courseMedicine->treatment_course_id;
                // Who gave it can differ from who is typing it in.
                $givenBy = (int) ($data['administered_by'] ?? $correction['administered_by'] ?? $actor->id);

                $event = MedicationDosingEvent::create([
                    'medicine_id' => $medicine->id, 'administered_at' => $at,
                    'administered_by' => $givenBy, 'recorded_by' => $actor->id, 'route' => $data['route'] ?? null,
                    'total_stock_quantity' => $totalStock, 'stock_unit' => $medicine->stock_unit,
                    'idempotency_key' => $data['idempotency_key'], 'status' => MedicationAdministration::STATUS_RECORDED,
                    'corrects_event_id' => $corrects?->id, 'correction_reason' => $corrects ? $correction['reason'] : null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $taken = [];
                $cost = '0';
                foreach ($plan as [$batch, $quantity]) {
                    $allocation = MedicationDosingAllocation::create([
                        'medication_dosing_event_id' => $event->id, 'medicine_batch_id' => $batch->id,
                        'stock_quantity' => $quantity, 'expiry_snapshot' => $batch->expiry_date,
                    ]);
                    try {
                        $movement = $this->inventory->recordUsage(
                            itemKind: InventoryMovement::ITEM_KIND_MEDICINE_BATCH, itemId: $batch->id, quantity: (float) $quantity,
                            sourceType: InventoryMovement::SOURCE_MEDICATION_DOSING_ALLOCATION, sourceId: $allocation->id,
                            occurredAt: $at, actorUserId: $actor->id, reference: 'Dose #'.$event->id,
                        );
                    } catch (InsufficientInventoryException $e) {
                        throw ValidationException::withMessages(['batch_id' => "Batch {$batch->batch_number} has only {$this->qty($e->available)} {$medicine->stock_unit} left. Someone may have just used it; check the stock and try again."]);
                    }
                    $allocation->update(['inventory_movement_id' => $movement->id, 'unit_cost_snapshot' => (string) $movement->weighted_avg_cost_before]);
                    $cost = bcadd($cost, bcmul($quantity, (string) $movement->weighted_avg_cost_before, 8), 8);
                    $taken[] = "{$this->qty($quantity)} from batch {$batch->batch_number}";
                }

                $shares = $this->split($totalStock, $pigs->count());
                $costShares = $this->split($this->round($cost), $pigs->count());
                $byPig = $scheduled->keyBy('pig_id');
                $originals = ($correction['originals'] ?? collect())->keyBy('pig_id');
                foreach ($pigs as $i => $pig) {
                    $dose = MedicationAdministration::create([
                        'pig_id' => $pig->id, 'medication_dosing_event_id' => $event->id, 'medicine_id' => $medicine->id,
                        'administration_date' => $at->toDateString(), 'administered_at' => $at,
                        'medication_name' => $medicine->display_name, 'medicine_name_snapshot' => $medicine->display_name,
                        'dosage' => $this->qty($perAnimal).' '.$medicine->dose_unit,
                        'dose_amount' => $perAnimal, 'dose_unit' => $medicine->dose_unit, 'stock_quantity' => $shares[$i],
                        'dose_units_per_stock_unit_snapshot' => (string) $medicine->dose_units_per_stock_unit, 'cost_snapshot' => $costShares[$i],
                        'purpose' => $data['purpose'] ?? null, 'route' => $data['route'] ?? null, 'notes' => $data['notes'] ?? null,
                        'withdrawal_period_days' => $medicine->meat_withdrawal_days, 'withdrawal_days_snapshot' => $medicine->meat_withdrawal_days,
                        'withdrawal_basis' => MedicationAdministration::BASIS_DOSE_DATE_PLUS_DAYS,
                        'health_record_id' => $case?->id, 'treatment_course_id' => $courseId,
                        'scheduled_dose_id' => $byPig[$pig->id]->id ?? null,
                        'administered_by' => $givenBy,
                        'status' => MedicationAdministration::STATUS_RECORDED,
                        'corrects_id' => $originals[$pig->id]->id ?? null,
                        'correction_reason' => $corrects ? $correction['reason'] : null,
                    ]);
                    if (isset($byPig[$pig->id])) {
                        $byPig[$pig->id]->update(['status' => ScheduledDose::ADMINISTERED, 'medication_administration_id' => $dose->id,
                            'status_reason' => null, 'status_changed_by' => $actor->id, 'status_changed_at' => now()]);
                    }
                }

                if ($courseId) {
                    $this->completeCourseIfDone($courseId, $actor);
                }
                if ($case) {
                    $this->cases->markUnderTreatment($case);
                }

                $this->audit->log('health', $corrects ? 'medication_dose_corrected' : 'medication_dose_recorded', [
                    'user' => $actor, 'auditable' => $event, 'organization_id' => $event->organization_id, 'record_label' => $medicine->name,
                    'description' => ($corrects ? "Dose record #{$corrects->id} corrected as #{$event->id} ({$correction['reason']}): " : '')
                        ."{$medicine->display_name} {$this->qty($perAnimal)} {$medicine->dose_unit} given to {$pigs->count()} animal(s): "
                        .$pigs->pluck('code')->implode(', ').". {$this->qty($totalStock)} {$medicine->stock_unit} taken (".implode('; ', $taken).').'
                        .($givenBy !== (int) $actor->id ? ' Given by '.(User::find($givenBy)?->name ?? 'user #'.$givenBy).'.' : '')
                        .(! empty($data['late_entry_reason']) ? " Added after the pig left: {$data['late_entry_reason']}" : ''),
                    'new_values' => ['medicine' => $medicine->display_name, 'administered_at' => $at->toDateTimeString(), 'dose_amount' => $perAnimal,
                        'dose_unit' => $medicine->dose_unit, 'animals' => $pigs->pluck('id')->all(), 'stock_quantity' => $totalStock,
                        'withdrawal_days' => $medicine->meat_withdrawal_days, 'given_by' => $givenBy, 'cost' => $this->round($cost)],
                    'metadata' => array_filter(['corrects_event_id' => $corrects?->id, 'scheduled_dose_ids' => $scheduled->modelKeys() ?: null]),
                ]);

                return $event;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // Two submissions with the same key raced; the other one won.
            if (! $corrects && ($existing = $this->existing($data['idempotency_key']))) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * Per-animal dose (dose units) and total stock to take (stock units).
     * "total" is for a group dosed together, e.g. through water (D12).
     *
     * @return array{0: string, 1: string}
     */
    public function amounts(array $data, Medicine $medicine, int $animals): array
    {
        $amount = (string) $data['dose_amount'];
        $total = ($data['amount_basis'] ?? 'per_animal') === 'total' ? $amount : bcmul($amount, (string) $animals, 8);
        $perAnimal = $this->round(bcdiv($total, (string) $animals, 8));
        $stock = $this->round(bcdiv($total, (string) $medicine->dose_units_per_stock_unit, 8));
        if (bccomp($stock, '0', self::SCALE) <= 0 || bccomp($perAnimal, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages(['dose_amount' => 'This amount is too small to record.']);
        }

        return [$perAnimal, $stock];
    }

    private function validate(User $actor, array $data, Medicine $medicine, Collection $pigs, CarbonImmutable $at, ?MedicationDosingEvent $corrects): void
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);
        if ($pigs->isEmpty()) {
            $fail('pig_ids', 'Choose at least one animal.');
        }
        if (! $medicine->is_active && (! $corrects || (int) $corrects->medicine_id !== (int) $medicine->id)) {
            $fail('medicine_id', "{$medicine->name} is marked not in use.");
        }
        if (($data['dose_unit'] ?? null) !== $medicine->dose_unit) {
            $fail('dose_unit', "{$medicine->name} is dosed in {$medicine->dose_unit}. Enter the dose in {$medicine->dose_unit}; nothing is converted.");
        }
        $routes = (array) ($medicine->allowed_routes ?? []);
        if ($routes !== [] && ! empty($data['route']) && ! in_array($data['route'], $routes, true)) {
            $fail('route', "{$medicine->name} is given by: ".implode(', ', $routes).'.');
        }
        if (bccomp((string) $data['dose_amount'], '0', self::SCALE) <= 0) {
            $fail('dose_amount', 'The amount must be more than zero.');
        }
        if ($at->isFuture()) {
            $fail('administered_at', 'A dose cannot be recorded before it is given.');
        }
        foreach ($pigs as $pig) {
            $this->dates->check($actor, $pig, $at->toDateString(), $data['late_entry_reason'] ?? null, 'administered_at');
        }
    }

    /** A case's dose may only go to animals in that case. */
    private function assertCaseCovers(HealthRecord $case, Collection $pigs, bool $correcting): void
    {
        // A correction may fix a dose on a case that has since been closed.
        if ($case->isVoided() || (! $correcting && $case->case_status === 'closed')) {
            throw ValidationException::withMessages(['health_record_id' => 'That case is closed or voided. Reopen it first.']);
        }
        $members = $case->animals()->pluck('pig_id')->push($case->pig_id)->map(fn ($id) => (int) $id);
        $outside = $pigs->reject(fn ($pig) => $members->contains((int) $pig->id));
        if ($outside->isNotEmpty()) {
            throw ValidationException::withMessages(['pig_ids' => 'Not part of this case: '.$outside->pluck('code')->implode(', ').'.']);
        }
    }

    /** Scheduled doses being given now: still due, this medicine, one per animal in the list. */
    private function lockScheduledDoses(array $ids, Medicine $medicine, Collection $pigs): Collection
    {
        if ($ids === []) {
            return new \Illuminate\Database\Eloquent\Collection;
        }
        $doses = ScheduledDose::with('courseMedicine')->whereKey($ids)->lockForUpdate()->get();
        $pigIds = $pigs->pluck('id')->map(fn ($id) => (int) $id);
        if ($doses->count() !== count(array_unique($ids)) || $doses->pluck('pig_id')->duplicates()->isNotEmpty()
            || $doses->pluck('courseMedicine.treatment_course_id')->unique()->count() > 1
            || $doses->contains(fn ($dose) => (int) $dose->courseMedicine->medicine_id !== (int) $medicine->id)) {
            throw ValidationException::withMessages(['scheduled_dose_ids' => 'Record one scheduled dose per animal, from one course and medicine, at a time.']);
        }
        // The form retains the slot's IDs when an undosed animal is removed.
        // Leave those animals' schedules untouched; only selected animals were given medicine.
        $doses = $doses->filter(fn ($dose) => $pigIds->contains((int) $dose->pig_id));
        foreach ($doses as $dose) {
            if ($dose->status !== ScheduledDose::SCHEDULED) {
                throw ValidationException::withMessages(['scheduled_dose_ids' => "The dose due {$dose->due_at->format('d M H:i')} for {$dose->pig?->code} is already ".$dose->status.'.']);
            }
        }

        return $doses;
    }

    /**
     * Which batches to take from: the chosen one, or earliest expiry first
     * across as many usable batches as needed.
     *
     * @return list<array{0: MedicineBatch, 1: string}>
     */
    private function planBatches(Medicine $medicine, ?int $batchId, string $needed, CarbonImmutable $at): array
    {
        if ($batchId) {
            $batch = MedicineBatch::whereKey($batchId)->lockForUpdate()->first();
            if (! $batch || (int) $batch->medicine_id !== (int) $medicine->id) {
                throw ValidationException::withMessages(['batch_id' => 'Choose a batch of '.$medicine->name.'.']);
            }
            if ($batch->received_at->toDateString() > $at->toDateString()) {
                throw ValidationException::withMessages(['batch_id' => "Batch {$batch->batch_number} was received on {$batch->received_at->format('d M Y')}, after this dose was given. Choose a batch available on the dose date."]);
            }
            if ($batch->isExpiredOn($at)) {
                throw ValidationException::withMessages(['batch_id' => "Batch {$batch->batch_number} expired on {$batch->expiry_date->format('d M Y')} and cannot be used."]);
            }
            if (! $batch->is_active) {
                throw ValidationException::withMessages(['batch_id' => "Batch {$batch->batch_number} is not in use."]);
            }
            $onHand = $this->stock->onHand($batch);
            if (bccomp($onHand, $needed, self::SCALE) < 0) {
                throw ValidationException::withMessages(['batch_id' => "Batch {$batch->batch_number} has {$this->qty($onHand)} {$medicine->stock_unit}; this dose needs {$this->qty($needed)}. Choose automatic to use more than one batch."]);
            }

            return [[$batch, $needed]];
        }

        $batches = $this->stock->usableBatches($medicine, $at->toDateString());
        MedicineBatch::whereKey($batches->modelKeys())->orderBy('id')->lockForUpdate()->get();  // id order, as everywhere else
        $plan = [];
        $left = $needed;
        foreach ($batches as $batch) {
            if (bccomp($left, '0', self::SCALE) <= 0) {
                break;
            }
            $take = bccomp($batch->on_hand, $left, self::SCALE) >= 0 ? $left : $batch->on_hand;
            $plan[] = [$batch, $take];
            $left = bcsub($left, $take, self::SCALE);
        }
        if (bccomp($left, '0', self::SCALE) > 0) {
            $have = $batches->reduce(fn ($sum, $b) => bcadd($sum, $b->on_hand, self::SCALE), '0');
            throw ValidationException::withMessages(['dose_amount' => "Not enough {$medicine->name} in stock: this dose needs {$this->qty($needed)} {$medicine->stock_unit} and usable batches hold {$this->qty($have)}."]);
        }

        return $plan;
    }

    /** Split a total over n animals at 4 dp; the last animal takes the remainder so shares add up exactly. */
    private function split(string $total, int $n): array
    {
        $share = bcdiv($total, (string) $n, self::SCALE);
        $shares = array_fill(0, $n, $share);
        $shares[$n - 1] = bcsub($total, bcmul($share, (string) ($n - 1), self::SCALE), self::SCALE);

        return $shares;
    }

    /** A course with no doses left to give is completed. The case stays open until someone closes it (D1). */
    public function completeCourseIfDone(int $courseId, User $actor): void
    {
        $course = TreatmentCourse::find($courseId);
        if (! $course || $course->status !== 'active') {
            return;
        }
        $doses = ScheduledDose::whereIn('treatment_course_medicine_id', $course->medicines()->pluck('id'));
        if ((clone $doses)->where('status', ScheduledDose::SCHEDULED)->exists()) {
            return;
        }
        $course->update(['status' => 'completed', 'status_reason' => null]);
        $counts = $doses->get()->countBy('status');
        $this->audit->log('health', 'treatment_course_completed', [
            'user' => $actor, 'auditable' => $course, 'organization_id' => $course->organization_id,
            'description' => "Course #{$course->id} completed: ".($counts[ScheduledDose::ADMINISTERED] ?? 0).' dose(s) given, '
                .($counts[ScheduledDose::MISSED] ?? 0).' missed, '.($counts[ScheduledDose::CANCELLED] ?? 0).' cancelled.',
            'old_values' => ['status' => 'active'], 'new_values' => ['status' => 'completed'],
        ]);
    }

    private function existing(string $key): ?MedicationDosingEvent
    {
        return MedicationDosingEvent::where('idempotency_key', $key)->first();
    }

    /** Round half up to 4 dp. */
    private function round(string $value): string
    {
        $half = bccomp($value, '0', 8) >= 0 ? '0.00005' : '-0.00005';

        return bcadd($value, $half, self::SCALE);
    }

    private function qty(string|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
