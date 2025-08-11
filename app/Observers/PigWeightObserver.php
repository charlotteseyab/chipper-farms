<?php

namespace App\Observers;

use App\Models\PigWeight;
use App\Models\WeightAlert;
use App\Models\ExpectedWeightStandard;
use App\Models\PigManagement;
use Illuminate\Support\Facades\Log;

class PigWeightObserver
{
    /**
     * Handle the PigWeight "created" event.
     * Automatically generate weight alerts when a new weight record is created.
     */
    public function created(PigWeight $pigWeight): void
    {
        try {
            // Get the pig associated with this weight record
            $pig = $pigWeight->pig;
            
            if (!$pig || !$pig->date_of_birth) {
                Log::warning("Cannot generate weight alert: Pig not found or missing birth date for weight record ID {$pigWeight->id}");
                return;
            }

            // Calculate pig's age in weeks at the time of weighing
            $ageInWeeks = $pig->date_of_birth->diffInWeeks($pigWeight->weigh_date);

            // Find matching weight standard
            $standard = ExpectedWeightStandard::active()
                ->forPigType($pig->breed, $pig->gender, $pig->stock_type)
                ->where('week_number', $ageInWeeks)
                ->first();

            // Generate weight alert
            $alert = WeightAlert::createFromWeight($pigWeight, $pig, $standard);

            Log::info("Weight alert generated", [
                'pig_id' => $pig->id,
                'ear_tag' => $pig->ear_tag_number,
                'weight_record_id' => $pigWeight->id,
                'alert_id' => $alert->id,
                'alert_type' => $alert->alert_type,
                'severity' => $alert->severity,
                'actual_weight' => $pigWeight->weight,
                'age_weeks' => $ageInWeeks,
                'has_standard' => $standard ? 'yes' : 'no'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to generate weight alert for weight record ID {$pigWeight->id}: " . $e->getMessage(), [
                'exception' => $e,
                'pig_weight_id' => $pigWeight->id,
                'pig_id' => $pigWeight->pig_id ?? 'unknown'
            ]);
        }
    }

    /**
     * Handle the PigWeight "updated" event.
     * Regenerate alerts if weight value changes significantly.
     */
    public function updated(PigWeight $pigWeight): void
    {
        // Only regenerate alert if the weight value changed
        if ($pigWeight->wasChanged('weight')) {
            try {
                // Delete existing alerts for this weight record
                WeightAlert::where('weight_record_id', $pigWeight->id)->delete();
                
                // Generate new alert with updated weight
                $this->created($pigWeight);
                
                Log::info("Weight alert regenerated due to weight update", [
                    'pig_weight_id' => $pigWeight->id,
                    'old_weight' => $pigWeight->getOriginal('weight'),
                    'new_weight' => $pigWeight->weight
                ]);
                
            } catch (\Exception $e) {
                Log::error("Failed to regenerate weight alert for updated weight record ID {$pigWeight->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the PigWeight "deleted" event.
     * Clean up associated alerts when weight record is deleted.
     */
    public function deleted(PigWeight $pigWeight): void
    {
        try {
            // Delete associated weight alerts
            $deletedCount = WeightAlert::where('weight_record_id', $pigWeight->id)->delete();
            
            if ($deletedCount > 0) {
                Log::info("Cleaned up {$deletedCount} weight alert(s) for deleted weight record ID {$pigWeight->id}");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to clean up weight alerts for deleted weight record ID {$pigWeight->id}: " . $e->getMessage());
        }
    }
}
