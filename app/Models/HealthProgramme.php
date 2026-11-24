<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A routine dose the farm must give: "PCV2 vaccine at 21 days old", "deworm
 * the breeding herd every 180 days", "E. coli vaccine 14 days before
 * farrowing". HealthProgrammeService turns it into scheduled doses.
 */
class HealthProgramme extends Model
{
    use BelongsToOrganization;

    public const PURPOSES = ['vaccination' => 'Vaccination', 'deworming' => 'Deworming', 'other' => 'Other routine dose'];

    public const TRIGGERS = [
        'age'              => 'At an age',
        'interval'         => 'Every N days',
        'before_mating'    => 'Before a planned mating',
        'before_farrowing' => 'Before expected farrowing',
        'after_farrowing'  => 'After farrowing',
    ];

    public const APPLIES_TO = [
        'all'              => 'All animals',
        'growers'          => 'Growers (standard stock)',
        'breeding'         => 'Breeding stock',
        'breeding_females' => 'Breeding sows and gilts',
        'boars'            => 'Boars',
    ];

    /** Triggers that follow a sow's breeding cycle, so only she can qualify. */
    public const BREEDING_TRIGGERS = ['before_mating', 'before_farrowing', 'after_farrowing'];

    protected $fillable = [
        'organization_id', 'name', 'purpose', 'medicine_id', 'dose_amount', 'dose_unit', 'route', 'instructions',
        'trigger', 'days', 'applies_to', 'due_time', 'starts_on', 'is_active', 'paused_reason',
        'responsible_user_id', 'created_by', 'updated_by',
    ];

    protected $casts = ['dose_amount' => 'decimal:4', 'days' => 'integer', 'starts_on' => 'date', 'is_active' => 'boolean'];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function doses(): HasMany
    {
        return $this->hasMany(HealthProgrammeDose::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(TreatmentCourse::class);
    }

    /** "At 21 days old", "Every 180 days from 1 Jun 2026", "14 days before expected farrowing". */
    public function ruleText(): string
    {
        $days = $this->days.' '.str('day')->plural($this->days);

        return match ($this->trigger) {
            'age'              => "At {$days} old",
            'interval'         => "Every {$days} from ".$this->starts_on->format('j M Y'),
            'before_mating'    => "{$days} before a planned mating",
            'before_farrowing' => "{$days} before expected farrowing",
            'after_farrowing'  => "{$days} after farrowing",
            default            => $this->trigger,
        };
    }
}
