<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log of cost moving between biological-asset stages for a group.
 */
class BiologicalAssetStageTransfer extends Model
{
    use BelongsToOrganization;

    public const REASON_OPENING            = 'opening';
    public const REASON_FEED_ISSUE         = 'feed_issue';
    public const REASON_FEED_ISSUE_REVERSAL = 'feed_issue_reversal';
    public const REASON_STAGE_PROGRESSION  = 'stage_progression';
    public const REASON_PROMOTION          = 'promotion';
    public const REASON_ACQUISITION        = 'acquisition';
    public const REASON_BIRTH              = 'birth';
    public const REASON_WEANING            = 'weaning';
    public const REASON_SALE_COGS          = 'sale_cogs';
    public const REASON_MORTALITY          = 'mortality';

    /**
     * A sold animal came back and re-entered the herd (CF-P0-008).
     *
     * A distinct reason rather than reusing REASON_ACQUISITION: an acquisition
     * is a purchase that ADDS cost to the business, whereas a return REVERSES
     * cost that was previously charged to COGS. Conflating them would make
     * "what did we spend acquiring animals this period" wrong, and would hide
     * returns from any report that looks at disposals versus acquisitions.
     */
    public const REASON_SALES_RETURN       = 'sales_return';

    protected $fillable = [
        'organization_id',
        'group_id',
        'from_stage_key',
        'to_stage_key',
        'amount',
        'pig_count',
        'weight_kg',
        'reason',
        'journal_batch_id',
        'source_domain_event_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:4',
            'weight_kg'   => 'decimal:4',
            'pig_count'   => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }
}
