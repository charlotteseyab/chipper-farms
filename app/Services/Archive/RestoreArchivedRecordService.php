<?php

namespace App\Services\Archive;

use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;

/**
 * Un-archive (restore) pigs and piglet death records.
 *
 * Extracted from PigManagementController::restorePig (was line 1818) and
 * ::restorePigletDeath (was line 1811).
 *
 * Deliberately kept minimal — these are simple soft-delete reversals and
 * no domain event is emitted because restoration is a data-correction
 * action, not a lifecycle event.
 *
 * Wrapped in a transaction to match the discipline of the other services.
 */
class RestoreArchivedRecordService
{
    public function __construct(protected AuditTrailService $audit)
    {
    }

    public function restorePig(PigManagement $pig, User $actor): PigManagement
    {
        return DB::transaction(function () use ($pig, $actor) {
            if ($pig->trashed()) {
                $pig->restore();
            }

            $this->audit->log('stock', 'restored', [
                'user' => $actor,
                'auditable' => $pig,
                'organization_id' => $pig->organization_id,
                'record_label' => $pig->ear_tag_number ?: $pig->code,
                'description' => "Pig {$pig->code} was restored to active stock.",
            ]);

            return $pig;
        });
    }

    public function restorePigletDeath(PigletDeath $pigletDeath, User $actor): PigletDeath
    {
        return DB::transaction(function () use ($pigletDeath, $actor) {
            if ($pigletDeath->trashed()) {
                $pigletDeath->restore();
            }

            $this->audit->log('mortality', 'restored', [
                'user' => $actor,
                'auditable' => $pigletDeath,
                'record_label' => 'Piglet death #' . $pigletDeath->id,
                'description' => 'Archived piglet death record was restored.',
            ]);

            return $pigletDeath;
        });
    }
}
