<?php

namespace App\Services\Health;

use App\Models\ScheduledDose;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;

/**
 * Marks a scheduled dose missed or cancelled, with a reason. Neither moves
 * stock: stock is taken only when a dose is recorded as given.
 */
class ScheduledDoseService
{
    public function __construct(
        protected RecordDoseService $doses,
        protected AuditTrailService $audit,
    ) {
    }

    public function close(User $actor, ScheduledDose $dose, string $status, string $reason): ScheduledDose
    {
        if (! in_array($status, [ScheduledDose::MISSED, ScheduledDose::CANCELLED], true)) {
            throw new \InvalidArgumentException("A scheduled dose can only be marked missed or cancelled, not {$status}.");
        }

        return DB::transaction(function () use ($actor, $dose, $status, $reason) {
            $dose = ScheduledDose::with(['courseMedicine.medicine', 'pig'])->whereKey($dose->id)->lockForUpdate()->firstOrFail();
            if ($dose->status !== ScheduledDose::SCHEDULED) {
                throw new \DomainException("This dose is already {$dose->status}.");
            }
            $dose->update(['status' => $status, 'status_reason' => $reason, 'status_changed_by' => $actor->id, 'status_changed_at' => now()]);
            $this->doses->completeCourseIfDone((int) $dose->courseMedicine->treatment_course_id, $actor);

            $this->audit->log('health', 'scheduled_dose_'.$status, [
                'user' => $actor, 'auditable' => $dose, 'organization_id' => $dose->organization_id, 'record_label' => $dose->pig?->code,
                'description' => ucfirst($status)." dose: {$dose->courseMedicine->medicine?->name} for {$dose->pig?->code}, due {$dose->due_at->format('d M Y H:i')}. {$reason}",
                'old_values' => ['status' => ScheduledDose::SCHEDULED], 'new_values' => ['status' => $status, 'reason' => $reason],
            ]);

            return $dose;
        });
    }
}
