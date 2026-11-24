<?php

namespace App\Observers;

use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the shop in step with a pig's health record.
 *
 * A dose with a withdrawal period, or a serious health record, makes an animal
 * unsaleable — but it may already be in a storefront pool. Without this the
 * shop would keep counting and pricing it until the next sweep.
 *
 * Observes MedicationAdministration and HealthRecord. Runs after commit, and a
 * failure is logged rather than thrown: recording a treatment must never be
 * refused because the shop could not be refreshed. The hourly sweep is the
 * safety net either way.
 */
class PigSaleabilityObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void
    {
        $this->resync($model);
    }

    public function deleted(Model $model): void
    {
        $this->resync($model);
    }

    public function restored(Model $model): void
    {
        $this->resync($model);
    }

    protected function resync(Model $model): void
    {
        $pigId = (int) $model->getAttribute('pig_id');

        if ($pigId === 0) {
            return;
        }

        try {
            app(StorefrontPigPoolService::class)->resyncListingsForPig($pigId);
        } catch (\Throwable $e) {
            Log::warning('Could not refresh storefront listings after a health change.', [
                'pig_id' => $pigId,
                'model'  => $model::class,
                'id'     => $model->getKey(),
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
