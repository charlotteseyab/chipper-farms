<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Farrowing extends Model
{
    use HasFactory;

    protected $table = 'farrowing';

    protected $fillable = [
        'sow_id',
        'boar_id',
        'farrowing_date',
        'farrowing_time',
        'total_piglets_born',
        'live_births',
        'stillbirths',
        'number_of_males',
        'number_of_females',
        'average_birth_weight',
        'total_litter_weight',
        'farrowing_type',
        'farrowing_difficulty',
        'gestation_length_days',
        'complications',
        'notes',
        'sow_condition',
        'colostrum_available',
        'functional_teats',
        'sow_weight_after_farrowing',
        'recorded_by',
        'follow_up_date',
        'status',
    ];

    protected $casts = [
        'farrowing_date' => 'date',
        'farrowing_time' => 'datetime:H:i',
        'follow_up_date' => 'datetime',
        'colostrum_available' => 'boolean',
        'average_birth_weight' => 'decimal:2',
        'total_litter_weight' => 'decimal:2',
        'sow_weight_after_farrowing' => 'decimal:2',
    ];

    // Relationships
    public function sow()
    {
        return $this->belongsTo(PigManagement::class, 'sow_id');
    }

    public function boar()
    {
        return $this->belongsTo(PigManagement::class, 'boar_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Scopes
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('farrowing_date', now()->month)
                    ->whereYear('farrowing_date', now()->year);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('farrowing_date', '>=', now()->subDays($days));
    }

    // Accessors
    public function getSurvivalRateAttribute()
    {
        if ($this->total_piglets_born == 0) {
            return 0;
        }
        return round(($this->live_births / $this->total_piglets_born) * 100, 1);
    }

    public function getGenderDistributionAttribute()
    {
        $total = $this->number_of_males + $this->number_of_females;
        if ($total == 0) {
            return ['male_percentage' => 0, 'female_percentage' => 0];
        }

        return [
            'male_percentage' => round(($this->number_of_males / $total) * 100, 1),
            'female_percentage' => round(($this->number_of_females / $total) * 100, 1),
        ];
    }

    // Badge helpers for status display
    public function getFarrowingTypeBadgeAttribute()
    {
        $badges = [
            'natural' => 'bg-green-100 text-green-800',
            'assisted' => 'bg-yellow-100 text-yellow-800',
            'cesarean' => 'bg-red-100 text-red-800',
        ];

        return $badges[$this->farrowing_type] ?? 'bg-gray-100 text-gray-800';
    }

    public function getFarrowingDifficultyBadgeAttribute()
    {
        $badges = [
            'easy' => 'bg-green-100 text-green-800',
            'moderate' => 'bg-yellow-100 text-yellow-800',
            'difficult' => 'bg-orange-100 text-orange-800',
            'complicated' => 'bg-red-100 text-red-800',
        ];

        return $badges[$this->farrowing_difficulty] ?? 'bg-gray-100 text-gray-800';
    }

    public function getSowConditionBadgeAttribute()
    {
        $badges = [
            'excellent' => 'bg-green-100 text-green-800',
            'good' => 'bg-blue-100 text-blue-800',
            'fair' => 'bg-yellow-100 text-yellow-800',
            'poor' => 'bg-red-100 text-red-800',
        ];

        return $badges[$this->sow_condition] ?? 'bg-gray-100 text-gray-800';
    }
}
