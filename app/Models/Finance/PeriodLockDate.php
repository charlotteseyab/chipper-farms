<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-organisation cutoff date before which postings are forbidden.
 * Distinct from fiscal_periods.status — locks an organisation-wide date
 * boundary regardless of which period it falls in.
 *
 * Use the active() scope to find the currently-enforcing lock.
 */
class PeriodLockDate extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'lock_date',
        'is_hard',
        'is_active',
        'reason',
        'locked_by_user_id',
        'locked_at',
        'superseded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'lock_date' => 'date',
            'is_hard'   => 'boolean',
            'is_active' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function isHard(): bool
    {
        return (bool) $this->is_hard;
    }
}
