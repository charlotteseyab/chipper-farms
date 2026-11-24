<?php

namespace App\Services\Health;

use App\Models\Finance\InventoryMovement;
use App\Models\MedicationAdministration;
use App\Models\MedicationDosingEvent;
use App\Models\MedicineBatch;
use App\Models\PigManagement;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Finance\Inventory\InsufficientInventoryException;
use App\Services\Finance\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Corrects or voids a recorded dose. The stock it took is put back with
 * reversing ledger movements, the original stays readable (marked corrected
 * or voided, with the reason) and any scheduled doses it covered become due
 * again. A correction then records the replacement, linked to the original,
 * in the same transaction, so stock and withdrawal are never half-updated.
 */
class CorrectDoseService
{
    public function __construct(
        protected InventoryService $inventory,
        protected RecordDoseService $doses,
        protected AuditTrailService $audit,
    ) {}

    public function void(User $actor, MedicationDosingEvent $event, string $reason): MedicationDosingEvent
    {
        return DB::transaction(function () use ($actor, $event, $reason) {
            $event = $this->undo($actor, $event, MedicationAdministration::STATUS_VOIDED, $reason);
            $this->audit->log('health', 'medication_dose_voided', [
                'user' => $actor, 'auditable' => $event, 'organization_id' => $event->organization_id, 'record_label' => $event->medicine?->name,
                'description' => "Dose record #{$event->id} ({$event->medicine?->display_name}, {$event->administrations->count()} animal(s)) voided: {$reason}. "
                    ."{$this->qty($event->total_stock_quantity)} {$event->stock_unit} returned to stock; its withdrawal no longer applies.",
                'old_values' => ['status' => MedicationAdministration::STATUS_RECORDED],
                'new_values' => ['status' => MedicationAdministration::STATUS_VOIDED, 'reason' => $reason],
            ]);

            return $event;
        }, 3);
    }

    /** @param  iterable<PigManagement>  $pigs */
    public function correct(User $actor, MedicationDosingEvent $event, array $data, iterable $pigs, string $reason): MedicationDosingEvent
    {
        return DB::transaction(function () use ($actor, $event, $data, $pigs, $reason) {
            $released = ScheduledDose::whereIn('medication_administration_id', $event->administrations()->pluck('id'))->pluck('id')->all();
            $original = $this->undo($actor, $event, MedicationAdministration::STATUS_CORRECTED, $reason);
            $pigIds = collect($pigs)->pluck('id')->map(fn ($id) => (int) $id);
            $data['scheduled_dose_ids'] = ScheduledDose::whereKey($released)->whereIn('pig_id', $pigIds)
                ->where('status', ScheduledDose::SCHEDULED)
                ->whereHas('courseMedicine', fn ($q) => $q->where('medicine_id', $data['medicine_id']))
                ->pluck('id')->all();
            $data['idempotency_key'] = (string) Str::uuid();
            $data['health_record_id'] ??= $original->administrations->first()?->health_record_id;

            return $this->doses->record($actor, $data, $pigs, [
                'corrects' => $original, 'reason' => $reason,
                'administered_by' => $original->administered_by, 'originals' => $original->administrations,
            ]);
        }, 3);
    }

    /** Put the stock back, mark the event and its doses, and release scheduled doses. */
    private function undo(User $actor, MedicationDosingEvent $event, string $status, string $reason): MedicationDosingEvent
    {
        $event = MedicationDosingEvent::with(['allocations.batch', 'administrations', 'medicine'])->whereKey($event->id)->lockForUpdate()->firstOrFail();
        if ($event->status !== MedicationAdministration::STATUS_RECORDED) {
            throw new \DomainException('This dose record has already been '.$event->status.'.');
        }

        // Same lock order as recording a dose (batch rows by id, then ledger
        // balances), so a correction and a dose on the same batch cannot deadlock.
        MedicineBatch::whereKey($event->allocations->pluck('medicine_batch_id')->unique()->all())->orderBy('id')->lockForUpdate()->get();
        foreach ($event->allocations as $allocation) {
            $movement = InventoryMovement::find($allocation->inventory_movement_id);
            if (! $movement) {
                continue;
            }
            try {
                $this->inventory->recordReversal(
                    $movement, InventoryMovement::SOURCE_MEDICATION_DOSING_ALLOCATION_REVERSAL, $allocation->id,
                    actorUserId: $actor->id, reference: 'Dose #'.$event->id.' '.$status, notes: $reason,
                );
            } catch (InsufficientInventoryException) {
                throw ValidationException::withMessages(['reason' => "Batch {$allocation->batch?->batch_number} could not take the stock back."]);
            }
        }

        $event->update(['status' => $status, 'correction_reason' => $reason]);
        $label = $status === MedicationAdministration::STATUS_CORRECTED ? 'Corrected: '.$reason : $reason;
        MedicationAdministration::where('medication_dosing_event_id', $event->id)
            ->update(['status' => $status, 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $label, 'updated_at' => now()]);

        $released = ScheduledDose::with('courseMedicine')->whereIn('medication_administration_id', $event->administrations->modelKeys())->get();
        foreach ($released as $dose) {
            $dose->update(['status' => ScheduledDose::SCHEDULED, 'medication_administration_id' => null,
                'status_reason' => 'Dose record '.$status.': '.$reason, 'status_changed_by' => $actor->id, 'status_changed_at' => now()]);
        }
        // A completed course has doses due again, so it is active again.
        foreach (TreatmentCourse::whereKey($released->pluck('courseMedicine.treatment_course_id')->unique())->where('status', 'completed')->get() as $course) {
            $course->update(['status' => 'active']);
            $this->audit->log('health', 'treatment_course_reactivated', [
                'user' => $actor, 'auditable' => $course, 'organization_id' => $course->organization_id,
                'description' => "Course #{$course->id} active again: dose record #{$event->id} was {$status}, so its scheduled doses are due again.",
                'old_values' => ['status' => 'completed'], 'new_values' => ['status' => 'active'],
            ]);
        }

        return $event->fresh(['allocations.batch', 'administrations', 'medicine']);
    }

    private function qty(string|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
