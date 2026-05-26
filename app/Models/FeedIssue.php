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
        'organization_id',
        'group_id',
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
    ];

    protected $casts = [
        'quantity_kg'           => 'decimal:4',
        'unit_cost_at_issuance' => 'decimal:6',
        'total_cost'            => 'decimal:4',
        'issued_at'             => 'datetime',
    ];

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
