<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\FiscalPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single attempt to close a fiscal period.
 *
 * Lifecycle:
 *   in_progress -> completed   (all checks pass or overridden; period flipped)
 *   in_progress -> failed      (a hard check failed, or operator abandoned)
 *   completed   -> reopened    (the close was reversed; period back to open)
 *
 * Only ONE run per period may be `completed` at a time. Reopening flips the
 * completed run to `reopened` and the period back to open/closed.
 */
class PeriodCloseRun extends Model
{
    use BelongsToOrganization;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_FAILED      = 'failed';
    public const STATUS_REOPENED    = 'reopened';

    public const LEVEL_SOFT = 'soft';   // -> period closed
    public const LEVEL_HARD = 'hard';   // -> period locked

    protected $fillable = [
        'organization_id',
        'fiscal_period_id',
        'status',
        'close_level',
        'initiated_by_user_id',
        'completed_at',
        'completed_by_user_id',
        'reopened_at',
        'reopened_by_user_id',
        'reopen_reason',
        'trial_balance_snapshot',
        'checks_summary',
        'override_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'completed_at'   => 'datetime',
            'reopened_at'    => 'datetime',
            'checks_summary' => 'array',
            'override_count' => 'integer',
        ];
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(PeriodCloseCheck::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(PeriodCloseStatement::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_COMPLETED);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Decode the trial-balance snapshot JSON to an array.
     *
     * @return array<string, mixed>|null
     */
    public function decodedTrialBalance(): ?array
    {
        if (empty($this->trial_balance_snapshot)) {
            return null;
        }
        $decoded = json_decode((string) $this->trial_balance_snapshot, true);
        return is_array($decoded) ? $decoded : null;
    }
}
