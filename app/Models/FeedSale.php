<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Finance\JournalBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A feed sale. Mirrors PigSale's role: sale terms + finance-readiness columns.
 */
class FeedSale extends Model
{
    use BelongsToOrganization;

    public const CHANNEL_DIRECT     = 'direct';
    public const CHANNEL_STOREFRONT = 'storefront';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID    = 'paid';
    public const PAYMENT_PARTIAL = 'partial';

    public const POST_PENDING = 'pending';
    public const POST_POSTED  = 'posted';
    public const POST_SKIPPED = 'skipped';
    public const POST_FAILED  = 'failed';

    protected $fillable = [
        'organization_id',
        'feed_type_id',
        'quantity_kg',
        'unit_price',
        'sale_total',
        'sale_date',
        'channel',
        'payment_status',
        'payment_method',
        'counterparty_id',
        'buyer_name',
        'buyer_contact',
        'revenue_journal_batch_id',
        'revenue_posting_status',
        'revenue_posting_error',
        'cogs_amount',
        'cogs_journal_batch_id',
        'inventory_movement_id',
        'cogs_posting_status',
        'cogs_posting_error',
        'sold_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:4',
            'unit_price'  => 'decimal:4',
            'sale_total'  => 'decimal:2',
            'cogs_amount' => 'decimal:2',
            'sale_date'   => 'date',
        ];
    }

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function revenueBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'revenue_journal_batch_id');
    }

    public function cogsBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'cogs_journal_batch_id');
    }

    public function isCreditSale(): bool
    {
        return $this->payment_status !== self::PAYMENT_PAID;
    }
}
