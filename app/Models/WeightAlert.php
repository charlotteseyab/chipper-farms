<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Models\Concerns\BelongsToOrganizationThroughRelation;

class WeightAlert extends Model
{
    use BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'pig';
    }

    protected $fillable = [
        'pig_id',
        'weight_record_id',
        'alert_type',
        'severity',
        'actual_weight',
        'expected_min_weight',
        'expected_max_weight',
        'optimal_weight',
        'deviation_percentage',
        'pig_age_weeks',
        'target_week',
        'alert_message',
        'acknowledged',
        'acknowledged_by',
        'acknowledged_at',
        'acknowledgment_notes'
    ];

    protected $casts = [
        'actual_weight' => 'decimal:2',
        'expected_min_weight' => 'decimal:2',
        'expected_max_weight' => 'decimal:2',
        'optimal_weight' => 'decimal:2',
        'deviation_percentage' => 'decimal:2',
        'pig_age_weeks' => 'integer',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime'
    ];

    /**
     * Get the pig this alert belongs to
     */
    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class);
    }

    /**
     * Get the weight record that triggered this alert
     */
    public function weightRecord(): BelongsTo
    {
        return $this->belongsTo(PigWeight::class, 'weight_record_id');
    }

    /**
     * Get the user who acknowledged this alert
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Get alert type display name
     */
    public function getAlertTypeDisplayAttribute(): string
    {
        return match($this->alert_type) {
            'under_weight' => 'Under Weight',
            'over_weight' => 'Over Weight',
            'normal' => 'Normal',
            'no_standard' => 'No Standard Set',
            default => ucfirst(str_replace('_', ' ', $this->alert_type))
        };
    }

    /**
     * Get severity display name with color
     */
    public function getSeverityDisplayAttribute(): array
    {
        return match($this->severity) {
            'high' => ['text' => 'High', 'color' => 'red'],
            'medium' => ['text' => 'Medium', 'color' => 'yellow'],
            'low' => ['text' => 'Low', 'color' => 'blue'],
            default => ['text' => ucfirst($this->severity), 'color' => 'gray']
        };
    }

    /**
     * Get alert type color for UI
     */
    public function getAlertColorAttribute(): string
    {
        return match($this->alert_type) {
            'under_weight' => 'red',
            'over_weight' => 'orange',
            'normal' => 'green',
            'no_standard' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get expected range display
     */
    public function getExpectedRangeDisplayAttribute(): string
    {
        if ($this->expected_min_weight && $this->expected_max_weight) {
            return number_format($this->expected_min_weight, 1) . ' - ' . number_format($this->expected_max_weight, 1) . ' kg';
        }
        return 'No standard set';
    }

    /**
     * Get deviation display with sign
     */
    public function getDeviationDisplayAttribute(): string
    {
        if ($this->deviation_percentage === null) {
            return 'N/A';
        }

        $sign = $this->deviation_percentage >= 0 ? '+' : '';
        return $sign . number_format($this->deviation_percentage, 1) . '%';
    }

    /**
     * Check if alert is recent (within last 7 days)
     */
    public function getIsRecentAttribute(): bool
    {
        return $this->created_at->isAfter(Carbon::now()->subDays(7));
    }

    /**
     * Scope for unacknowledged alerts
     */
    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }

    /**
     * Scope for acknowledged alerts
     */
    public function scopeAcknowledged($query)
    {
        return $query->where('acknowledged', true);
    }

    /**
     * Scope for specific alert type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Scope for specific severity
     */
    public function scopeOfSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for recent alerts
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Acknowledge this alert
     */
    public function acknowledge(User $user, ?string $notes = null): bool
    {
        return $this->update([
            'acknowledged' => true,
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
            'acknowledgment_notes' => $notes
        ]);
    }

    /**
     * Create alert from weight record and standard
     */
    public static function createFromWeight(PigWeight $weightRecord, PigManagement $pig, ?ExpectedWeightStandard $standard = null): self
    {
        // Use target week if specified, otherwise calculate from actual age
        $targetWeek = $weightRecord->target_week;
        $actualAgeWeeks = $weightRecord->actual_age_weeks ??
            ($pig->date_of_birth ? $pig->date_of_birth->diffInWeeks($weightRecord->weigh_date) : 0);

        // If no standard provided, try to find one using target week
        if (!$standard) {
            $weekToUse = $targetWeek ?? $actualAgeWeeks;
            $standard = ExpectedWeightStandard::active()
                ->forPigType($pig->breed, $pig->gender, $pig->stock_type)
                ->where('week_number', $weekToUse)
                ->first();
        }

        if (!$standard) {
            // Try alternate exact week candidates (target vs actual)
            $candidates = array_values(array_unique(array_filter([$targetWeek, $actualAgeWeeks], function ($w) {
                return $w !== null;
            })));
            foreach ($candidates as $candidateWeek) {
                $standard = ExpectedWeightStandard::active()
                    ->forPigType($pig->breed, $pig->gender, $pig->stock_type)
                    ->where('week_number', $candidateWeek)
                    ->first();
                if ($standard) {
                    break;
                }
            }
        }

        if (!$standard) {
            // Fallback: nearest available week standard for this pig type
            $weekToUse = $targetWeek ?? $actualAgeWeeks ?? 0;
            $standard = ExpectedWeightStandard::active()
                ->forPigType($pig->breed, $pig->gender, $pig->stock_type)
                ->orderByRaw('ABS(week_number - ?)', [$weekToUse])
                ->first();
        }

        if (!$standard) {
            $weekReference = $targetWeek ? "target Week {$targetWeek}" : "{$actualAgeWeeks} weeks old";
            return self::create([
                'pig_id' => $pig->id,
                'weight_record_id' => $weightRecord->id,
                'alert_type' => 'no_standard',
                'severity' => 'low',
                'actual_weight' => $weightRecord->weight,
                'pig_age_weeks' => $actualAgeWeeks,
                'target_week' => $targetWeek,
                'alert_message' => "No weight standard set for {$pig->breed} {$pig->gender} at {$weekReference}."
            ]);
        }

        $alertType = $standard->determineAlertType($weightRecord->weight);
        $severity = $standard->determineSeverity($weightRecord->weight);
        $deviation = $standard->calculateDeviation($weightRecord->weight);

        // under_weight alerts are treated as high priority
        if ($alertType === 'under_weight') {
            $severity = 'high';
        }
        // over_weight alerts are treated as medium priority
        elseif ($alertType === 'over_weight') {
            $severity = 'medium';
        }

        $message = self::generateAlertMessage($pig, $weightRecord->weight, $standard, $alertType, $deviation);

        return self::create([
            'pig_id' => $pig->id,
            'weight_record_id' => $weightRecord->id,
            'alert_type' => $alertType,
            'severity' => $severity,
            'actual_weight' => $weightRecord->weight,
            'expected_min_weight' => $standard->expected_min_weight,
            'expected_max_weight' => $standard->expected_max_weight,
            'optimal_weight' => $standard->optimal_weight,
            'deviation_percentage' => $deviation,
            'pig_age_weeks' => $actualAgeWeeks,
            'target_week' => $targetWeek,
            'alert_message' => $message
        ]);
    }

    /**
     * Generate human-readable alert message
     */
    private static function generateAlertMessage(PigManagement $pig, float $weight, ExpectedWeightStandard $standard, string $alertType, float $deviation): string
    {
        $pigInfo = "{$pig->ear_tag_number} ({$pig->breed} {$pig->gender})";
        $weightInfo = number_format($weight, 1) . 'kg';
        $expectedRange = $standard->weight_range_display;
        $deviationText = number_format(abs($deviation), 1) . '%';

        return match($alertType) {
            'under_weight' => "{$pigInfo} is underweight at {$weightInfo}. Expected: {$expectedRange}. Deviation: -{$deviationText}",
            'over_weight' => "{$pigInfo} is overweight at {$weightInfo}. Expected: {$expectedRange}. Deviation: +{$deviationText}",
            'normal' => "{$pigInfo} weight is normal at {$weightInfo}. Expected: {$expectedRange}",
            default => "{$pigInfo} weight recorded: {$weightInfo}"
        };
    }
}
