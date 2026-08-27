<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpectedWeightStandard extends Model
{
    use \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'breed',
        'gender',
        'stock_type',
        'week_number',
        'expected_min_weight',
        'expected_max_weight',
        'optimal_weight',
        'notes',
        'created_by',
        'is_active'
    ];

    protected $casts = [
        'expected_min_weight' => 'decimal:2',
        'expected_max_weight' => 'decimal:2',
        'optimal_weight' => 'decimal:2',
        'is_active' => 'boolean',
        'week_number' => 'integer'
    ];

    /**
     * Get the user who created this standard
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get breed display name
     */
    public function getBreedDisplayAttribute(): string
    {
        return Setting::breedName($this->breed) ?? $this->breed;
    }

    /**
     * Get gender display name
     */
    public function getGenderDisplayAttribute(): string
    {
        return $this->gender === 'M' ? 'Male' : 'Female';
    }

    /**
     * Get stock type display name
     */
    public function getStockTypeDisplayAttribute(): string
    {
        return ucfirst($this->stock_type);
    }

    /**
     * Get weight range display
     */
    public function getWeightRangeDisplayAttribute(): string
    {
        return number_format($this->expected_min_weight, 1) . ' - ' . number_format($this->expected_max_weight, 1) . ' kg';
    }

    /**
     * Check if a weight is within the expected range
     */
    public function isWeightInRange(float $weight): bool
    {
        return $weight >= $this->expected_min_weight && $weight <= $this->expected_max_weight;
    }

    /**
     * Calculate deviation percentage from optimal weight
     */
    public function calculateDeviation(float $actualWeight): float
    {
        if ($this->optimal_weight == 0) {
            return 0;
        }

        return (($actualWeight - $this->optimal_weight) / $this->optimal_weight) * 100;
    }

    /**
     * Determine alert type based on weight
     */
    public function determineAlertType(float $weight): string
    {
        if ($weight < $this->expected_min_weight) {
            return 'under_weight';
        } elseif ($weight > $this->expected_max_weight) {
            return 'over_weight';
        } else {
            return 'normal';
        }
    }

    /**
     * Determine severity based on deviation
     */
    public function determineSeverity(float $weight): string
    {
        $deviation = abs($this->calculateDeviation($weight));

        if ($deviation > 25) {
            return 'high';
        } elseif ($deviation > 15) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Scope for active standards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific breed, gender, stock type combination
     */
    public function scopeForPigType($query, string $breed, string $gender, string $stockType)
    {
        return $query->where('breed', $breed)
                    ->where('gender', $gender)
                    ->where('stock_type', $stockType);
    }

    /**
     * Find standard for specific pig and week
     */
    public static function findForPig(PigManagement $pig, int $weekNumber): ?self
    {
        return self::active()
                   ->forPigType($pig->breed, $pig->gender, $pig->stock_type)
                   ->where('week_number', $weekNumber)
                   ->first();
    }
}
