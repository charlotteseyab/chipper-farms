<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SetsOrganizationFromPig;

/**
 * One dose given to one animal. New doses belong to a dosing event (which
 * moves stock once) and copy the medicine's name, units and withdrawal at the
 * time. Legacy rows (is_legacy) predate the upgrade and never move stock.
 */
class MedicationAdministration extends Model implements \App\Models\Concerns\ResolvesOrganization
{
    use HasFactory, BelongsToOrganization, SetsOrganizationFromPig, SoftDeletes;

    public const STATUS_RECORDED = 'recorded';
    public const STATUS_CORRECTED = 'corrected';
    public const STATUS_VOIDED = 'voided';

    /** How withdrawal_ends_on was worked out (decision D7). */
    public const BASIS_DOSE_DATE_PLUS_DAYS = 'dose_date_plus_days';

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
        'voided_at',
        'voided_by',
        'void_reason',
        'corrects_id',
        'correction_reason',
        'organization_id',
        'medication_dosing_event_id',
        'administered_at',
        'medicine_id',
        'health_record_id',
        'treatment_course_id',
        'scheduled_dose_id',
        'dose_amount',
        'dose_unit',
        'stock_quantity',
        'dose_units_per_stock_unit_snapshot',
        'cost_snapshot',
        'medicine_name_snapshot',
        'withdrawal_days_snapshot',
        'withdrawal_basis',
        'status',
        'is_legacy',
    ];

    protected $casts = [
        'administration_date' => 'date',
        'withdrawal_ends_on'  => 'date',
        'withdrawal_period_days' => 'integer',
        'voided_at' => 'datetime',
        'administered_at' => 'datetime',
        'dose_amount' => 'decimal:4',
        'stock_quantity' => 'decimal:4',
        'dose_units_per_stock_unit_snapshot' => 'decimal:4',
        'cost_snapshot' => 'decimal:4',
        'withdrawal_days_snapshot' => 'integer',
        'is_legacy' => 'boolean',
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
     * `withdrawal_ends_on` is the dose date plus the withdrawal days, and the
     * comparison is `>=`, so that date is still restricted and the pig may be
     * sold from the day after (decision D7): a 28-day withdrawal given on the
     * 1st is restricted through the 29th and clears on the 30th. Voided doses
     * never restrict.
     */
    public function scopeUnderWithdrawal(\Illuminate\Database\Eloquent\Builder $q, ?string $asOf = null): \Illuminate\Database\Eloquent\Builder
    {
        return $q->whereNull($q->getModel()->qualifyColumn('voided_at'))
            ->whereNotNull('withdrawal_ends_on')
            ->whereDate('withdrawal_ends_on', '>=', $asOf ?? now()->toDateString());
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Doses that still count. Voided and corrected ones stay visible in history only. */
    public function scopeNotVoided(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull($query->getModel()->qualifyColumn('voided_at'));
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** The record this one replaced, when it was saved as a correction. */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_id');
    }

    /** The replacement saved when this record was corrected. */
    public function correctedBy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'corrects_id');
    }

    public function resolveOrganizationId(): ?int
    {
        $organizationId = $this->organization_id ?? $this->pig?->organization_id;

        return $organizationId === null ? null : (int) $organizationId;
    }

    public function dosingEvent(): BelongsTo
    {
        return $this->belongsTo(MedicationDosingEvent::class, 'medication_dosing_event_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function healthRecord(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class);
    }

    public function treatmentCourse(): BelongsTo
    {
        return $this->belongsTo(TreatmentCourse::class);
    }

    public function scheduledDose(): BelongsTo
    {
        return $this->belongsTo(ScheduledDose::class);
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}

