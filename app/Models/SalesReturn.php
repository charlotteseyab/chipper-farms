<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sales return — the reversal of a sale, in whole or in part (CF-P0-008).
 *
 * This is the idempotency anchor for the whole return operation. Every side
 * effect (the inventory receipt, the biological-asset entry, the COGS and
 * revenue reversals) is keyed to this row's id, so retrying a partially-failed
 * return re-attempts only what has not yet been done.
 *
 * See the migration for why a return is a transaction rather than a flag on the
 * sale.
 */
class SalesReturn extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    /** Created, not yet posted. */
    public const STATUS_DRAFT = 'draft';

    /** Reversal in progress — set before the first side effect. */
    public const STATUS_POSTING = 'posting';

    /** All required reversals completed. */
    public const STATUS_POSTED = 'posted';

    /**
     * A reversal step failed. The return is retryable: completed steps are
     * recorded on this row and are not repeated.
     */
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'return_number',
        'returnable_type',
        'returnable_id',
        'storefront_order_id',
        'quantity',
        'proportion',
        'restock',
        'reason',
        'status',
        'posting_error',
        'cogs_reversal_batch_id',
        'revenue_reversal_batch_id',
        'inventory_movement_id',
        'biological_transfer_id',
        'cogs_reversed_amount',
        'revenue_reversed_amount',
        'pig_restored',
        'returned_by_user_id',
        'returned_at',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'restock'      => 'boolean',
            'pig_restored' => 'boolean',
            'returned_at'  => 'datetime',
            'posted_at'    => 'datetime',
            // quantity, proportion and the amounts are deliberately NOT cast to
            // float. They are handled as bcmath strings throughout the finance
            // code; casting would reintroduce float rounding into money.
        ];
    }

    public function returnable(): MorphTo
    {
        return $this->morphTo();
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function storefrontOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Storefront\StorefrontOrder::class, 'storefront_order_id');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Is this a full return of the original sale?
     *
     * Compared with bccomp rather than == because proportion is a decimal
     * string; float comparison against 1.0 is exactly the kind of rounding
     * error that silently under-reverses a full return.
     */
    public function isFullReturn(): bool
    {
        return bccomp((string) $this->proportion, '1', 8) >= 0;
    }

    /**
     * The source key used for the inventory movement this return produces.
     *
     * Movements are unique on (source_type, source_id), so keying on the return
     * — not the sale — is what allows a second, partial return against the same
     * sale to record its own movement instead of colliding with the first.
     */
    public function movementSourceType(): string
    {
        return static::class;
    }
}
