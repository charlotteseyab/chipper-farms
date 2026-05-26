<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single validation check within a period close run.
 *
 * The check_key values are stable machine identifiers; the close
 * validation service produces exactly this set (those that apply).
 */
class PeriodCloseCheck extends Model
{
    public const STATUS_PASS       = 'pass';
    public const STATUS_FAIL       = 'fail';
    public const STATUS_OVERRIDDEN = 'overridden';
    public const STATUS_SKIPPED    = 'skipped';

    // Hard checks — never overridable. If these fail the close cannot proceed.
    public const KEY_TB_BALANCED       = 'tb_balanced';

    // Soft checks — failure is overridable with a documented reason.
    public const KEY_OUTBOX_DRAINED    = 'outbox_drained';
    public const KEY_NO_DRAFT_JOURNALS = 'no_draft_journals';
    public const KEY_AR_RECONCILED     = 'ar_reconciled';
    public const KEY_AP_RECONCILED     = 'ap_reconciled';
    public const KEY_INVENTORY_RECONCILED = 'inventory_reconciled';
    public const KEY_GRIR_RECONCILED   = 'grir_reconciled';
    public const KEY_PIG_POOL_RECONCILED = 'pig_pool_reconciled';
    public const KEY_BANK_RECONCILED   = 'bank_reconciled';

    /**
     * Checks that may NEVER be overridden. A failure here is a hard stop.
     *
     * @var array<int, string>
     */
    public const HARD_CHECKS = [
        self::KEY_TB_BALANCED,
    ];

    protected $fillable = [
        'period_close_run_id',
        'check_key',
        'label',
        'status',
        'is_overridable',
        'detail',
        'overridden_by_user_id',
        'override_reason',
        'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'is_overridable' => 'boolean',
            'detail'         => 'array',
            'overridden_at'  => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PeriodCloseRun::class, 'period_close_run_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_user_id');
    }

    public function isPassing(): bool
    {
        return in_array($this->status, [self::STATUS_PASS, self::STATUS_OVERRIDDEN, self::STATUS_SKIPPED], true);
    }

    public function isHard(): bool
    {
        return in_array($this->check_key, self::HARD_CHECKS, true);
    }
}
