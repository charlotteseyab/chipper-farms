<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Weaning extends Model
{
    use HasFactory;

    protected $fillable = [
        'pig_id',
        'mother_id',
        'weaning_date',
        'age_at_weaning_days',
        'weaning_weight',
        'weaning_method',
        'weaning_reason',
        'notes',
        'post_weaning_status',
        'weight_loss_percentage',
        'follow_up_date',
        'recorded_by',
        'maturity_completed',
        'maturity_completion_date',
        'age_at_maturity_days',
        'final_weight',
        'health_status_at_maturity',
        'body_condition_score',
        'next_destination',
        'growth_performance',
        'maturity_notes',
        'completed_by'
    ];

    protected $casts = [
        'weaning_date' => 'date',
        'follow_up_date' => 'date',
        'weaning_weight' => 'decimal:2',
        'weight_loss_percentage' => 'decimal:2',
        'maturity_completed' => 'boolean',
        'maturity_completion_date' => 'date',
        'final_weight' => 'decimal:2',
    ];

    // Relationships
    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'mother_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // Scopes
    public function scopeRecentlyWeaned($query, $days = 30)
    {
        return $query->where('weaning_date', '>=', now()->subDays($days));
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('weaning_date', now()->month)
                    ->whereYear('weaning_date', now()->year);
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('weaning_method', $method);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('post_weaning_status', $status);
    }

    // Accessors
    public function getWeaningAgeFormattedAttribute(): string
    {
        return $this->age_at_weaning_days . ' days';
    }

    public function getWeaningWeightFormattedAttribute(): string
    {
        return $this->weaning_weight ? $this->weaning_weight . ' kg' : 'Not recorded';
    }

    public function getPostWeaningStatusBadgeAttribute(): string
    {
        return match($this->post_weaning_status) {
            'healthy' => 'bg-green-100 text-green-800',
            'stressed' => 'bg-yellow-100 text-yellow-800',
            'sick' => 'bg-red-100 text-red-800',
            'monitoring' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getWeaningMethodBadgeAttribute(): string
    {
        return match($this->weaning_method) {
            'natural' => 'bg-green-100 text-green-800',
            'forced' => 'bg-red-100 text-red-800',
            'gradual' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // Helper methods
    public function isEarlyWeaning(): bool
    {
        $minWeaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
        return $this->age_at_weaning_days < $minWeaningAge;
    }

    public function isLateWeaning(): bool
    {
        $maxWeaningAge = config('breeding.cycle.birth_to_weaning_days', 45) + 14; // 2 weeks grace
        return $this->age_at_weaning_days > $maxWeaningAge;
    }

    public function needsFollowUp(): bool
    {
        return $this->follow_up_date && $this->follow_up_date->isPast() &&
               in_array($this->post_weaning_status, ['stressed', 'sick', 'monitoring']);
    }
}
