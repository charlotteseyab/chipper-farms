<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Finance\JournalBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 5 — feed issue.
 *
 * Records one feed-delivery event from store → pig group. Spawns two
 * ledger writes (raw inventory + group cost) and one GL batch via
 * FeedIssuedSubscriber.
 */
class FeedIssue extends Model
{
    use BelongsToOrganization;

    public const POSTING_PENDING  = 'pending';
    public const POSTING_POSTED   = 'posted';
    public const POSTING_FAILED   = 'failed';
    public const POSTING_REVERSED = 'reversed';

    protected $fillable = [
        'submission_key', 'planned_quantity_kg', 'planning_snapshot',
        'organization_id',
        'group_id',
        'feeding_stage',
        'room_id',
        'feed_type_id',
        'quantity_kg',
        'unit_cost_at_issuance',
        'total_cost',
        'issued_at',
        'issued_by_user_id',
        'reference',
        'notes',
        'posting_status',
        'posting_error',
        'journal_batch_id',
        'reversed_at', 'reversed_by', 'reversal_reason', 'reversal_journal_batch_id',
    ];

    protected $casts = [
        'planned_quantity_kg' => 'decimal:4', 'planning_snapshot' => 'array',
        'quantity_kg'           => 'decimal:4',
        'unit_cost_at_issuance' => 'decimal:6',
        'total_cost'            => 'decimal:4',
        'issued_at'             => 'datetime',
        'reversed_at'           => 'datetime',
    ];

    /** What was fed: the feeding stage, or the group for older group issues. */
    public function getRecipientLabelAttribute(): string
    {
        if ($this->feeding_stage) {
            return \App\Services\Feed\FeedStageResolver::STAGES[$this->feeding_stage] ?? $this->feeding_stage;
        }

        return $this->planning_snapshot['group_name'] ?? $this->group?->name ?? '—';
    }

    /** What was fed and, when it was recorded, the pen or room: "Grower · Barn A · sty 1". */
    public function getWhereLabelAttribute(): string
    {
        $room = $this->room;

        return $this->recipient_label.($room ? ' · '.trim(($room->structure?->name ? $room->structure->name.' · ' : '').$room->name) : '');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function scopeNotReversed($query)
    {
        return $query->whereNull('reversed_at');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }
}
