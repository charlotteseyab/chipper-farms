<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Concerns\BelongsToOrganizationThroughRelation;
class PigWeight extends Model
{
    use BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'pig';
    }

    protected $fillable = [
        'pig_id',
        'weight',
        'weigh_date',
        'target_week',
        'actual_age_weeks',
        'weight_unit',
        'notes',
        'week_notes',
        'weighed_by',
        'purpose',
        'is_estimated',
        'body_condition_score',
        'health_status',
    ];

    protected $casts = [
        'weigh_date' => 'date',
        'weight' => 'decimal:2',
        'body_condition_score' => 'decimal:1',
        'is_estimated' => 'boolean'
    ];

    // Relationships
    public function pig()
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    /**
     * Backward-compatible alias for older views/controllers.
     */
    public function pigManagement()
    {
        return $this->pig();
    }

    public function weightAlerts()
    {
        return $this->hasMany(WeightAlert::class, 'weight_record_id');
    }

    // Scopes
    public function scopeLatest($query)
    {
        return $query->orderBy('weigh_date', 'desc');
    }

    public function scopeForPurpose($query, $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('weigh_date', '>=', now()->subDays($days));
    }

    // Accessors
    public function getWeightInLbsAttribute(): float
    {
        if ($this->weight_unit === 'lbs') {
            return (float) $this->weight;
        }
        return (float) $this->weight * 2.20462; // Convert kg to lbs
    }

    public function getWeightInKgAttribute(): float
    {
        if ($this->weight_unit === 'kg') {
            return (float) $this->weight;
        }
        return (float) $this->weight / 2.20462; // Convert lbs to kg
    }

    public function getFormattedWeightAttribute(): string
    {
        return number_format($this->weight, 1) . ' ' . $this->weight_unit;
    }

    public function getAgeAtWeighingAttribute(): int
    {
        if (!$this->pig || !$this->pig->date_of_birth) {
            return 0;
        }
        return $this->pig->date_of_birth->diffInDays($this->weigh_date);
    }

    public function getDaysAgoAttribute(): int
    {
        return $this->weigh_date->diffInDays(now());
    }

    public function getPurposeIconAttribute(): string
    {
        return match($this->purpose) {
            'breeding_check' => 'Breeding',
            'health_check' => 'Health',
            'routine' => 'Analytics',
            'sale_prep' => 'Money',
            'birth' => 'Pig',
            'weaning' => 'Weaning',
            default => 'Weight'
        };
    }

    public function getPurposeBadgeAttribute(): string
    {
        return match($this->purpose) {
            'breeding_check' => 'bg-pink-100 text-pink-800',
            'health_check' => 'bg-red-100 text-red-800',
            'routine' => 'bg-blue-100 text-blue-800',
            'sale_prep' => 'bg-green-100 text-green-800',
            'birth' => 'bg-purple-100 text-purple-800',
            'weaning' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // Static methods
    public static function getLatestWeightForPig($pigId)
    {
        return static::where('pig_id', $pigId)
                    ->latest('weigh_date')
                    ->first();
    }

    public static function getWeightHistoryForPig($pigId, $limit = 10)
    {
        return static::where('pig_id', $pigId)
                    ->latest('weigh_date')
                    ->limit($limit)
                    ->get();
    }

    public static function getAverageWeightForAge($ageInDays, $gender = null, $breed = null)
    {
        $query = static::whereHas('pig', function($q) use ($ageInDays, $gender, $breed) {
            $q->whereRaw('DATEDIFF(pig_weights.weigh_date, pig_management.date_of_birth) BETWEEN ? AND ?',
                [$ageInDays - 15, $ageInDays + 15]);

            if ($gender) {
                $q->where('gender', $gender);
            }

            if ($breed) {
                $q->where('breed', $breed);
            }
        });

        return $query->avg('weight');
    }

    /**
     * Regenerate weight alerts for this weight record
     * This is useful when weight standards are added or updated
     */
    public function regenerateAlerts(): void
    {
        try {
            // Delete existing alerts for this weight record
            $this->weightAlerts()->delete();

            // Get the pig associated with this weight record
            $pig = $this->pig;
            
            if (!$pig || !$pig->date_of_birth) {
                \Log::warning("Cannot regenerate weight alert: Pig not found or missing birth date for weight record ID {$this->id}");
                return;
            }

            // Calculate pig's age in weeks at the time of weighing
            $ageInWeeks = $pig->date_of_birth->diffInWeeks($this->weigh_date);

            // Find matching weight standard
            $standard = \App\Models\ExpectedWeightStandard::active()
                ->forPigType($pig->breed, $pig->gender, $pig->stock_type)
                ->where('week_number', $ageInWeeks)
                ->first();

            // Generate new weight alert
            $alert = \App\Models\WeightAlert::createFromWeight($this, $pig, $standard);

            \Log::debug("Weight alert regenerated", [
                'pig_id' => $pig->id,
                'ear_tag' => $pig->ear_tag_number,
                'weight_record_id' => $this->id,
                'alert_id' => $alert->id,
                'alert_type' => $alert->alert_type,
                'severity' => $alert->severity,
                'actual_weight' => $this->weight,
                'age_weeks' => $ageInWeeks,
                'has_standard' => $standard ? 'yes' : 'no'
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to regenerate weight alert for weight record ID {$this->id}: " . $e->getMessage());
        }
    }

    /**
     * Reprocess all weight records for a specific pig type (breed, gender, stock type)
     * This is useful when new weight standards are added
     */
    public static function reprocessAlertsForPigType(string $breed, string $gender, string $stockType): int
    {
        $count = 0;
        
        try {
            // Get all weight records for this pig type
            $weightRecords = static::whereHas('pig', function($query) use ($breed, $gender, $stockType) {
                $query->where('breed', $breed)
                      ->where('gender', $gender)
                      ->where('stock_type', $stockType);
            })->get();

            foreach ($weightRecords as $weightRecord) {
                $weightRecord->regenerateAlerts();
                $count++;
            }

            \Log::info("Reprocessed weight alerts for pig type", [
                'breed' => $breed,
                'gender' => $gender,
                'stock_type' => $stockType,
                'records_processed' => $count
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to reprocess weight alerts for pig type {$breed} {$gender} {$stockType}: " . $e->getMessage());
        }

        return $count;
    }
}
