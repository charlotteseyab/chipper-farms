<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pig leaving the herd by transfer to another farm/owner or by culling.
 * Sales and deaths keep their own records (pig_sales, pig_deaths).
 */
class PigExit extends Model
{
    use BelongsToOrganization;

    public const TRANSFER_OUT = 'transfer_out';
    public const CULL = 'cull';

    public const TYPES = [self::TRANSFER_OUT => 'Transfer out', self::CULL => 'Cull'];

    public const REASONS = [
        self::TRANSFER_OUT => [
            'another_farm' => 'Moved to another farm we own',
            'new_owner'    => 'Given or handed to another owner',
            'loan'         => 'Loaned (e.g. boar to another farm)',
            'other'        => 'Other',
        ],
        self::CULL => [
            'poor_growth'  => 'Poor growth',
            'infertility'  => 'Infertility or repeat breeding',
            'lameness'     => 'Lameness',
            'chronic_illness' => 'Chronic illness',
            'old_age'      => 'Old age / end of productive life',
            'behaviour'    => 'Aggression or behaviour',
            'other'        => 'Other',
        ],
    ];

    /** What happened to a culled animal. A cull that is sold goes through Sales instead. */
    public const DISPOSALS = [
        'slaughtered_on_farm' => 'Slaughtered on the farm (for food)',
        'destroyed'           => 'Put down and disposed of (not for food)',
        'given_away'          => 'Given away',
        'other'               => 'Other',
    ];

    /** Exits after which the animal may enter the food chain: medicine withdrawal blocks them. */
    public const FOOD_CHAIN_DISPOSALS = ['slaughtered_on_farm'];

    protected $fillable = [
        'organization_id', 'pig_id', 'exit_type', 'exit_date', 'reason', 'reason_other',
        'destination', 'disposal', 'final_weight', 'notes', 'tag_outcome', 'recorded_by',
    ];

    protected $casts = ['exit_date' => 'date', 'final_weight' => 'decimal:2'];

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->exit_type] ?? $this->exit_type;
    }

    public function getReasonLabelAttribute(): string
    {
        $label = self::REASONS[$this->exit_type][$this->reason] ?? $this->reason;

        return $this->reason === 'other' && $this->reason_other ? $this->reason_other : $label;
    }

    public function getDisposalLabelAttribute(): ?string
    {
        return $this->disposal ? (self::DISPOSALS[$this->disposal] ?? $this->disposal) : null;
    }
}
