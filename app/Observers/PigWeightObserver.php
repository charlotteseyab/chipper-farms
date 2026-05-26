<?php

namespace App\Observers;

use App\Models\PigWeight;
use App\Models\WeightAlert;
use App\Services\Weights\WeightAlertGenerationService;
use Illuminate\Support\Facades\Log;

/**
 * Phase E3 rewrite: thin shell delegating to WeightAlertGenerationService.
 *
 * Bug fixed: the previous version's updated() handler did
 *   1. WeightAlert::where(...)->delete()
 *   2. $this->created($pigWeight)
 * as TWO separate operations not wrapped in a transaction. If the create
 * step threw between them, the system was left with no alert at all for
 * a record that previously had one.
 *
 * The new implementation routes both delete+create through the service's
 * single transactional generate($pigWeight, $existingAlert) path so the
 * outcome is atomic.
 */
class PigWeightObserver
{
    public function __construct(protected WeightAlertGenerationService $alerts)
    {
    }

    public function created(PigWeight $pigWeight): void
    {
        try {
            $this->alerts->generate($pigWeight);
        } catch (\Throwable $e) {
            Log::error("Failed to generate weight alert for weight record ID {$pigWeight->id}: " . $e->getMessage(), [
                'exception'     => $e,
                'pig_weight_id' => $pigWeight->id,
                'pig_id'        => $pigWeight->pig_id ?? 'unknown',
            ]);
        }
    }

    public function updated(PigWeight $pigWeight): void
    {
        if (! $pigWeight->wasChanged('weight')) {
            return;
        }

        try {
            $existing = WeightAlert::where('weight_record_id', $pigWeight->id)->first();

            $this->alerts->generate($pigWeight, $existing);

            Log::info('Weight alert regenerated due to weight update', [
                'pig_weight_id' => $pigWeight->id,
                'old_weight'    => $pigWeight->getOriginal('weight'),
                'new_weight'    => $pigWeight->weight,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to regenerate weight alert for updated weight record ID {$pigWeight->id}: " . $e->getMessage());
        }
    }

    public function deleted(PigWeight $pigWeight): void
    {
        try {
            $deletedCount = $this->alerts->deleteAlertsFor($pigWeight);
            if ($deletedCount > 0) {
                Log::info("Cleaned up {$deletedCount} weight alert(s) for deleted weight record ID {$pigWeight->id}");
            }
        } catch (\Throwable $e) {
            Log::error("Failed to clean up weight alerts for deleted weight record ID {$pigWeight->id}: " . $e->getMessage());
        }
    }
}
