<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Helpers\BreedingCalculator;

class Mating extends Model
{
    protected $fillable = [
        'male_id',
        'female_id',
        'planned_date',
        'actual_date',
        'status',
        'notes',
        'outcome_notes',
        'successful',
        'expected_farrowing_date',
        'expected_piglets',
        'created_by',
        'completed_by',
        'pregnancy_check_date',
        'pregnancy_status',
        'pregnancy_check_method',
        'pregnancy_check_notes',
        'pregnancy_confidence',
        'checked_by',
        'actual_farrowing_date',
        'total_piglets',
        'live_piglets',
        'stillborn_piglets',
        'farrowing_notes',
        'pregnancy_status_date',
        'pregnancy_status_notes',
        'expected_weaning_date'
    ];

    protected $casts = [
        'planned_date' => 'date',
        'actual_date' => 'date',
        'expected_farrowing_date' => 'date',
        'actual_farrowing_date' => 'date',
        'pregnancy_check_date' => 'date',
        'pregnancy_status_date' => 'date',
        'expected_weaning_date' => 'date',
        'successful' => 'boolean',
        'expected_piglets' => 'integer',
        'total_piglets' => 'integer',
        'live_piglets' => 'integer',
        'stillborn_piglets' => 'integer'
    ];

    // Relationships
    public function male(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'male_id');
    }

    public function female(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'female_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // Accessors and Mutators
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'planned' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'failed' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            'planned' => '📅',
            'completed' => '✅',
            'cancelled' => '❌',
            'failed' => '⚠️',
            default => ''
        };
    }

    public function getDaysUntilPlannedAttribute(): int
    {
        return $this->planned_date->diffInDays(now(), false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'planned' && $this->planned_date->isPast();
    }

    // Pregnancy-related accessors
    public function getPregnancyCheckDueAttribute(): bool
    {
        if ($this->status !== 'completed') return false;
        $checkDueDate = $this->actual_date->addDays(config('breeding.cycle.pregnancy_check_days'));
        return now()->gte($checkDueDate) && $this->pregnancy_status === 'pending';
    }

    public function getPregnancyCheckOverdueAttribute(): bool
    {
        if ($this->status !== 'completed') return false;
        $overdueDate = $this->actual_date->addDays(config('breeding.cycle.pregnancy_check_days') + 7); // 7 days grace period
        return now()->gte($overdueDate) && $this->pregnancy_status === 'pending';
    }

    public function getDaysSinceMatingAttribute(): int
    {
        if (!$this->actual_date) return 0;
        return (int) $this->actual_date->diffInDays(now());
    }

    public function getPregnancyStatusBadgeAttribute(): string
    {
        return match($this->pregnancy_status) {
            'pregnant' => 'bg-green-100 text-green-800',
            'not_pregnant' => 'bg-red-100 text-red-800',
            'uncertain' => 'bg-yellow-100 text-yellow-800',
            'pending' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getPregnancyStatusIconAttribute(): string
    {
        return match($this->pregnancy_status) {
            'pregnant' => '🤰',
            'not_pregnant' => '❌',
            'uncertain' => '❓',
            'pending' => '⏳',
            'farrowed' => '🐷',
            'lost_pregnancy' => '💔',
            'aborted' => '🚫',
            default => ''
        };
    }

    // Date accessors
    public function getMatingDateAttribute()
    {
        return $this->actual_date ?? $this->planned_date;
    }

    // Gestation-related accessors
    public function getDaysToFarrowingAttribute(): int
    {
    if (!$this->expected_farrowing_date) return 0;
    // Expected date minus today
    return now()->diffInDays($this->expected_farrowing_date, false);
    }


    public function getIsOverdueForFarrowingAttribute(): bool
    {
    if (!$this->expected_farrowing_date) {
        return false;
    }

    return $this->days_to_farrowing < 0 
        && $this->pregnancy_status === 'pregnant';
    }


    public function getGestationDaysAttribute(): int
    {
        if (!$this->actual_date) return 0;
        $endDate = $this->actual_farrowing_date ?? now();
        return $this->actual_date->diffInDays($endDate);
    }

    public function getFarrowingStatusAttribute(): string
    {
        if ($this->pregnancy_status === 'farrowed') {
            return 'Farrowed';
        } elseif ($this->is_overdue_for_farrowing) {
            return 'Overdue';
        } elseif ($this->days_to_farrowing <= 7) {
            return 'Due Soon';
        } else {
            return 'Pregnant';
        }
    }

    public function getFarrowingStatusBadgeAttribute(): string
    {
        return match($this->farrowing_status) {
            'Farrowed' => 'bg-green-100 text-green-800',
            'Overdue' => 'bg-red-100 text-red-800',
            'Due Soon' => 'bg-yellow-100 text-yellow-800',
            'Pregnant' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getSurvivalRateAttribute(): float
    {
        if (!$this->total_piglets || $this->total_piglets == 0) return 0;
        return round(($this->live_piglets / $this->total_piglets) * 100, 1);
    }

    // Scopes
    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'planned')
                    ->where('planned_date', '>=', now()->toDateString());
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'planned')
                    ->where('planned_date', '<', now()->toDateString());
    }

    public function scopePregnancyCheckDue($query)
    {
        $checkDueDate = now()->subDays(config('breeding.cycle.pregnancy_check_days'))->toDateString();
        return $query->where('status', 'completed')
                    ->where('actual_date', '<=', $checkDueDate)
                    ->where('pregnancy_status', 'pending');
    }

    public function scopePregnancyCheckOverdue($query)
    {
        $overdueDate = now()->subDays(config('breeding.cycle.pregnancy_check_days') + 7)->toDateString();
        return $query->where('status', 'completed')
                    ->where('actual_date', '<=', $overdueDate)
                    ->where('pregnancy_status', 'pending');
    }

    public function scopePregnant($query)
    {
        return $query->where('pregnancy_status', 'pregnant');
    }

    public function scopeNotPregnant($query)
    {
        return $query->where('pregnancy_status', 'not_pregnant');
    }

    // Breeding cycle calculations
    public function getPregnancyCheckDateAttribute(): Carbon
    {
        return BreedingCalculator::calculatePregnancyCheckDate($this->actual_date ?? $this->planned_date);
    }

    public function getExpectedFarrowingDateCalculatedAttribute(): Carbon
    {
        return BreedingCalculator::calculateFarrowingDate($this->actual_date ?? $this->planned_date);
    }

    public function getExpectedWeaningDateAttribute(): Carbon
    {
        $farrowingDate = $this->expected_farrowing_date ?? $this->expected_farrowing_date_calculated;
        return BreedingCalculator::calculateWeaningDate($farrowingDate);
    }

    public function getNextMatingAvailableAttribute(): Carbon
    {
        return BreedingCalculator::calculateNextMatingDate($this->expected_weaning_date);
    }

    public function getBreedingTimelineAttribute(): array
    {
        return BreedingCalculator::getBreedingTimeline($this->actual_date ?? $this->planned_date);
    }

    public function getUpcomingEventsAttribute(): array
    {
        return BreedingCalculator::getUpcomingEvents($this->actual_date ?? $this->planned_date, $this->status);
    }

    public function getCycleLengthDaysAttribute(): int
    {
        return $this->breeding_timeline['cycle_length_days'];
    }

    // Helper methods
    public function markAsCompleted($userId = null, $actualDate = null, $notes = null): bool
    {
        $actualDate = $actualDate ?? now()->toDateString();

        $this->update([
            'status' => 'completed',
            'actual_date' => $actualDate,
            'completed_by' => $userId ?? auth()->id(),
            'outcome_notes' => $notes,
            // Automatically calculate expected farrowing date using breeding calculator
            'expected_farrowing_date' => BreedingCalculator::calculateFarrowingDate($actualDate)
        ]);

        return true;
    }

    public function markAsCancelled($notes = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'outcome_notes' => $notes
        ]);
    }

    public function markAsFailed($notes = null): bool
    {
        return $this->update([
            'status' => 'failed',
            'successful' => false,
            'outcome_notes' => $notes
        ]);
    }

    public function recordPregnancyCheck($status, $method = null, $confidence = null, $notes = null, $checkDate = null, $userId = null): bool
    {
        $data = [
            'pregnancy_status' => $status,
            'pregnancy_check_date' => $checkDate ?? now()->toDateString(),
            'checked_by' => $userId ?? auth()->id(),
        ];

        if ($method) $data['pregnancy_check_method'] = $method;
        if ($confidence) $data['pregnancy_confidence'] = $confidence;
        if ($notes) $data['pregnancy_check_notes'] = $notes;

        // If pregnant, update successful flag and ensure farrowing date is set
        if ($status === 'pregnant') {
            $data['successful'] = true;
            if (!$this->expected_farrowing_date) {
                $data['expected_farrowing_date'] = BreedingCalculator::calculateFarrowingDate($this->actual_date);
            }
        } elseif ($status === 'not_pregnant') {
            $data['successful'] = false;
            // When not pregnant, the breeding process ends and both pigs become available again
            // This is handled automatically by the availability logic in PigManagement model
        }

        return $this->update($data);
    }
}
