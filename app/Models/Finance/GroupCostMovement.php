<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 5 — group cost movement ledger row.
 *
 * Read-mostly. Written exclusively by GroupCostPoolService inside a
 * transaction with the snapshot update.
 */
class GroupCostMovement extends Model
{
    use BelongsToOrganization;

    public const EVENT_FEED_ISSUE         = 'feed_issue';
    public const EVENT_SALE_COGS          = 'sale_cogs';
    public const EVENT_MORTALITY_WRITEOFF = 'mortality_writeoff';
    public const EVENT_TRANSFER_IN        = 'transfer_in';
    public const EVENT_TRANSFER_OUT       = 'transfer_out';
    public const EVENT_OPENING            = 'opening';
    public const EVENT_ACQUISITION        = 'acquisition';
    public const EVENT_ADJUSTMENT_ADD     = 'adjustment_add';
    public const EVENT_ADJUSTMENT_DEDUCT  = 'adjustment_deduct';
    public const EVENT_ADJUSTMENT_REVALUE = 'adjustment_revalue';

    /**
     * Cost returning to the pool because a sale was reversed. Distinct from
     * EVENT_TRANSFER_IN and EVENT_ACQUISITION: neither of those is a
     * reversal, and reporting that asks 'what did this group cost to build'
     * must be able to tell a genuine inflow from an undone outflow.
     */
    public const EVENT_SALES_RETURN       = 'sales_return';

    public const ALLOWED_EVENTS = [
        self::EVENT_FEED_ISSUE,
        self::EVENT_SALE_COGS,
        self::EVENT_MORTALITY_WRITEOFF,
        self::EVENT_TRANSFER_IN,
        self::EVENT_TRANSFER_OUT,
        self::EVENT_OPENING,
        self::EVENT_ACQUISITION,
        self::EVENT_ADJUSTMENT_ADD,
        self::EVENT_ADJUSTMENT_DEDUCT,
        self::EVENT_ADJUSTMENT_REVALUE,
    ];

    public const POSITIVE_EVENTS = [
        self::EVENT_FEED_ISSUE,
        self::EVENT_TRANSFER_IN,
        self::EVENT_OPENING,
        self::EVENT_ACQUISITION,
        self::EVENT_ADJUSTMENT_ADD,
    ];

    public const NEGATIVE_EVENTS = [
        self::EVENT_SALE_COGS,
        self::EVENT_MORTALITY_WRITEOFF,
        self::EVENT_TRANSFER_OUT,
        self::EVENT_ADJUSTMENT_DEDUCT,
    ];

    // Short source tokens (matches Phase 4 pattern).
    public const SOURCE_FEED_ISSUE             = 'feed_issue';
    public const SOURCE_PIG_SALE               = 'pig_sale';
    public const SOURCE_PIG_DEATH              = 'pig_death';
    public const SOURCE_PIG_GROUP_TRANSFER     = 'pig_group_transfer';
    public const SOURCE_GROUP_OPENING_IMPORT   = 'group_opening_import';
    public const SOURCE_PIG_ACQUISITION        = 'pig_acquisition';
    public const SOURCE_MANUAL_ADJUSTMENT      = 'manual_adjustment';

    /**
     * A sold pig returned to the group (CF-P0-008). Keyed on the
     * SalesReturn id, so a return has its own row rather than mutating the
     * sale's — the pool must show both the outflow and its reversal.
     */
    public const SOURCE_SALES_RETURN           = 'sales_return';

    protected $fillable = [
        'organization_id',
        'group_id',
        'balance_id',
        'event_type',
        'source_type',
        'source_id',
        'pig_count_delta',
        'value_delta',
        'weight_delta',
        'pig_count_before',
        'value_before',
        'weight_before',
        'pig_count_after',
        'value_after',
        'weight_after',
        'event_pig_weight_kg',
        'event_per_kg_basis',
        'occurred_at',
        'performed_by_user_id',
        'journal_batch_id',
        'opening_import_id',
        'reference',
        'notes',
    ];

    protected $casts = [
        'pig_count_delta'      => 'integer',
        'value_delta'          => 'decimal:4',
        'weight_delta'         => 'decimal:4',
        'pig_count_before'     => 'integer',
        'value_before'         => 'decimal:4',
        'weight_before'        => 'decimal:4',
        'pig_count_after'      => 'integer',
        'value_after'          => 'decimal:4',
        'weight_after'         => 'decimal:4',
        'event_pig_weight_kg'  => 'decimal:4',
        'event_per_kg_basis'   => 'decimal:6',
        'occurred_at'          => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(GroupCostPool::class, 'balance_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function isPositive(): bool
    {
        return in_array($this->event_type, self::POSITIVE_EVENTS, true);
    }

    public function isNegative(): bool
    {
        return in_array($this->event_type, self::NEGATIVE_EVENTS, true);
    }
}
