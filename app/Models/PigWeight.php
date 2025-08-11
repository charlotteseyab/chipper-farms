<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PigWeight extends Model
{
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
        'body_condition_score'
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
        return $this->belongsTo(PigManagement::class, 'pig_id');
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
            'breeding_check' => '💕',
            'health_check' => '🏥',
            'routine' => '📊',
            'sale_prep' => '💰',
            'birth' => '🐷',
            'weaning' => '🍼',
            default => '⚖️'
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
}
