<?php

namespace App\Services\Archive;

use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Models\User;
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
    public function restorePig(PigManagement $pig, User $actor): PigManagement
    {
        return DB::transaction(function () use ($pig) {
            if ($pig->trashed()) {
                $pig->restore();
            }
            return $pig;
        });
    }

    public function restorePigletDeath(PigletDeath $pigletDeath, User $actor): PigletDeath
    {
        return DB::transaction(function () use ($pigletDeath) {
            if ($pigletDeath->trashed()) {
                $pigletDeath->restore();
            }
            return $pigletDeath;
        });
    }
}