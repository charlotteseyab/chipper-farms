<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 4 — single ledger row representing one inventory movement.
 *
 * Replaces the Phase 0.7 raw_material_valuations table. Identical
 * column shape; broader scope (covers all item kinds, not just raw
 * materials).
 *
 * Read-mostly model. Written exclusively by InventoryService and
 * read by reports / subscribers. No public mutator API.
 */
class InventoryMovement extends Model
{
    use BelongsToOrganization;

    // Item kind discriminator — mirrors InventoryBalance constants.
    public const ITEM_KIND_RAW_MATERIAL  = 'raw_material';
    public const ITEM_KIND_FINISHED_FEED = 'finished_feed';
    // One balance per medicine batch (item_id = medicine_batches.id). Stock
    // and cost only: no GL posting until an account mapping is agreed (D10).
    public const ITEM_KIND_MEDICINE_BATCH = 'medicine_batch';

    public const ALLOWED_ITEM_KINDS = [
        self::ITEM_KIND_RAW_MATERIAL,
        self::ITEM_KIND_FINISHED_FEED,
        self::ITEM_KIND_MEDICINE_BATCH,
    ];

    // Event types — every movement classifies into one. Used by reports
    // and by subscribers to decide GL routing.
    public const EVENT_RECEIVE              = 'receive';
    public const EVENT_USAGE                = 'usage';
    public const EVENT_ADJUSTMENT_ADD       = 'adjustment_add';
    public const EVENT_ADJUSTMENT_DEDUCT    = 'adjustment_deduct';
    public const EVENT_ADJUSTMENT_SET       = 'adjustment_set';
    public const EVENT_FEED_PRODUCE         = 'feed_produce';
    public const EVENT_FEED_CONSUME_PIGS    = 'feed_consume_for_pigs';
    public const EVENT_OPENING              = 'opening';
    public const EVENT_TRANSFER_OUT         = 'transfer_out';
    public const EVENT_TRANSFER_IN          = 'transfer_in';
    // Undoes one earlier movement exactly (negated quantity and value).
    public const EVENT_REVERSAL             = 'reversal';

    public const ALLOWED_EVENTS = [
        self::EVENT_RECEIVE,
        self::EVENT_USAGE,
        self::EVENT_ADJUSTMENT_ADD,
        self::EVENT_ADJUSTMENT_DEDUCT,
        self::EVENT_ADJUSTMENT_SET,
        self::EVENT_FEED_PRODUCE,
        self::EVENT_FEED_CONSUME_PIGS,
        self::EVENT_OPENING,
        self::EVENT_TRANSFER_OUT,
        self::EVENT_TRANSFER_IN,
    ];

    // Source tokens — short stable strings, see model docblock for the
    // rationale of strings over class names.
    public const SOURCE_STOCK_LOG        = 'stock_log';
    public const SOURCE_FEED_BATCH       = 'feed_batch';
    public const SOURCE_FEED_BATCH_ITEM  = 'feed_batch_item';
    public const SOURCE_FEED_ISSUE       = 'feed_issue';
    public const SOURCE_FEED_SALE        = 'feed_sale';
    public const SOURCE_OPENING_IMPORT   = 'opening_import';
    public const SOURCE_LEGACY_VALUATION = 'legacy_valuation';
    public const SOURCE_TRANSFER         = 'inventory_transfer';
    public const SOURCE_STOCK_COUNT_LINE = 'stock_count_line';
    public const SOURCE_FEED_ISSUE_REVERSAL     = 'feed_issue_reversal';
    public const SOURCE_FEED_BATCH_REVERSAL     = 'feed_batch_reversal';
    public const SOURCE_FEED_BATCH_ITEM_REVERSAL = 'feed_batch_item_reversal';
    public const SOURCE_MEDICINE_BATCH_RECEIPT  = 'medicine_batch_receipt';
    public const SOURCE_MEDICINE_STOCK_ADJUSTMENT = 'medicine_stock_adjustment';
    public const SOURCE_MEDICATION_DOSING_ALLOCATION = 'medication_dosing_allocation';
    public const SOURCE_MEDICATION_DOSING_ALLOCATION_REVERSAL = 'medication_dosing_allocation_reversal';

    // Events that ADD to on-hand (positive quantity delta).
    public const POSITIVE_EVENTS = [
        self::EVENT_RECEIVE,
        self::EVENT_ADJUSTMENT_ADD,
        self::EVENT_FEED_PRODUCE,
        self::EVENT_OPENING,
        self::EVENT_TRANSFER_IN,
    ];

    // Events that REMOVE from on-hand (negative quantity delta).
    public const NEGATIVE_EVENTS = [
        self::EVENT_USAGE,
        self::EVENT_ADJUSTMENT_DEDUCT,
        self::EVENT_FEED_CONSUME_PIGS,
        self::EVENT_TRANSFER_OUT,
    ];

    protected $fillable = [
        'organization_id',
        'item_kind',
        'item_id',
        'balance_id',
        'event_type',
        'source_type',
        'source_id',
        'quantity_delta',
        'value_delta',
        'quantity_on_hand_before',
        'value_on_hand_before',
        'weighted_avg_cost_before',
        'quantity_on_hand_after',
        'value_on_hand_after',
        'weighted_avg_cost_after',
        'event_unit_cost',
        'occurred_at',
        'performed_by_user_id',
        'journal_batch_id',
        'opening_balance_import_id',
        'reference',
        'notes',
    ];

    protected $casts = [
        'quantity_delta'           => 'decimal:4',
        'value_delta'              => 'decimal:4',
        'quantity_on_hand_before'  => 'decimal:4',
        'value_on_hand_before'     => 'decimal:4',
        'weighted_avg_cost_before' => 'decimal:6',
        'quantity_on_hand_after'   => 'decimal:4',
        'value_on_hand_after'      => 'decimal:4',
        'weighted_avg_cost_after'  => 'decimal:6',
        'event_unit_cost'          => 'decimal:6',
        'occurred_at'              => 'datetime',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'balance_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function openingBalanceImport(): BelongsTo
    {
        return $this->belongsTo(
            InventoryOpeningBalanceImport::class,
            'opening_balance_import_id',
        );
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
