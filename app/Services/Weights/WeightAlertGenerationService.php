<?php

namespace App\Services\Weights;

use App\Models\ExpectedWeightStandard;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\WeightAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Encapsulates weight alert generation. Extracted from PigWeightObserver
 * so the alert logic can be tested without observer plumbing and reused
 * from artisan commands (e.g. ReprocessWeightAlerts).
 *
 * The observer becomes a thin shell that delegates here.
 */
class WeightAlertGenerationService
{
    /**
     * Generate (or regenerate) the weight alert for a single PigWeight row.
     *
     * If $existingAlert is provided, this performs a transactional swap:
     * the old alert is deleted and a new one is created in the same
     * transaction. Either both writes commit or both roll back — fixing
     * the gap window the observer's previous delete-then-create had.
     *
     * Returns the created WeightAlert, or null if no alert was warranted
     * (e.g. pig has no birth date).
     */
    public function generate(PigWeight $pigWeight, ?WeightAlert $existingAlert = null): ?WeightAlert
    {
        $pig = $pigWeight->pig;

        if (! $pig || ! $pig->date_of_birth) {
            Log::warning('Cannot generate weight alert: pig not found or missing birth date', [
                'pig_weight_id' => $pigWeight->id,
            ]);
            return null;
        }

        $ageInWeeks = (int) $pig->date_of_birth->diffInWeeks($pigWeight->weigh_date);

        $standard = ExpectedWeightStandard::active()
            ->forPigType($pig->breed, $pig->gender, $pig->stock_type)
            ->where('week_number', $ageInWeeks)
            ->first();

        return DB::transaction(function () use ($pigWeight, $pig, $standard, $existingAlert, $ageInWeeks) {
            if ($existingAlert) {
                $existingAlert->delete();
            }

            $alert = WeightAlert::createFromWeight($pigWeight, $pig, $standard);

            Log::info('Weight alert generated', [
                'pig_id'           => $pig->id,
                'ear_tag'          => $pig->ear_tag_number,
                'weight_record_id' => $pigWeight->id,
                'alert_id'         => $alert->id,
                'alert_type'       => $alert->alert_type ?? null,
                'severity'         => $alert->severity ?? null,
                'actual_weight'    => $pigWeight->weight,
                'age_weeks'        => $ageInWeeks,
                'has_standard'     => $standard ? 'yes' : 'no',
            ]);

            return $alert;
        });
    }

    /**
     * Delete all alerts associated with a PigWeight row. Called when the
     * weight record itself is deleted.
     */
    public function deleteAlertsFor(PigWeight $pigWeight): int
    {
        return WeightAlert::where('weight_record_id', $pigWeight->id)->delete();
    }
}
