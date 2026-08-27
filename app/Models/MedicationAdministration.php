<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToOrganizationThroughRelation;

class MedicationAdministration extends Model
{
    use HasFactory, BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'pig';
    }

    protected $fillable = [
        'pig_id',
        'administration_date',
        'medication_name',
        'dosage',
        'purpose',
        'route',
        'notes',
        'administered_by',
        // Phase 11 (§35): how long the animal must stay out of the food chain.
        'withdrawal_period_days',
        'withdrawal_ends_on',
    ];

    protected $casts = [
        'administration_date' => 'date',
        'withdrawal_ends_on'  => 'date',
        'withdrawal_period_days' => 'integer',
    ];

    /**
     * Keep the derived end date in step with what was entered.
     *
     * `withdrawal_ends_on` is stored rather than computed on read so the
     * eligibility query can be a plain indexed date comparison — the question
     * "is this pig under withdrawal" is asked while a staff member waits. The
     * cost of storing it is that the two columns can drift; this is what stops
     * them.
     */
    protected static function booted(): void
    {
        static::saving(function (self $administration): void {
            $days = $administration->withdrawal_period_days;

            if ($days === null || $administration->administration_date === null) {
                $administration->withdrawal_ends_on = null;

                return;
            }

            $administration->withdrawal_ends_on = \Illuminate\Support\Carbon::parse(
                $administration->administration_date
            )->addDays((int) $days)->toDateString();
        });
    }

    /**
     * Treatments still inside their withdrawal period on the given day.
     *
     * The comparison is `>=` so the last day of the period still counts: a
     * 28-day withdrawal recorded on the 1st clears on the 29th, not the 28th.
     */
    public function scopeUnderWithdrawal(\Illuminate\Database\Eloquent\Builder $q, ?string $asOf = null): \Illuminate\Database\Eloquent\Builder
    {
        return $q->whereNotNull('withdrawal_ends_on')
            ->whereDate('withdrawal_ends_on', '>=', $asOf ?? now()->toDateString());
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}

