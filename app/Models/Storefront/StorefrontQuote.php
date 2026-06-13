<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A storefront quote request. Triaged by sales; priced; later (Phase 5)
 * convertible to an order. Posts nothing to finance on its own.
 *
 * Status machine (enforced in QuoteService):
 *   requested → reviewing → quoted → accepted → converted
 *                       ↘ declined        ↘ declined
 *   (any non-terminal) → expired
 */
class StorefrontQuote extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $table = 'storefront_quotes';

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_QUOTED    = 'quoted';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CONVERTED = 'converted';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_REVIEWING,
        self::STATUS_QUOTED,
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
        self::STATUS_EXPIRED,
        self::STATUS_CONVERTED,
    ];

    public const SOURCE_LISTING = 'listing';
    public const SOURCE_BULK    = 'bulk';

    protected $fillable = [
        'organization_id',
        'reference',
        'counterparty_id',
        'customer_user_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'source',
        'fulfilment_preference',
        'delivery_city',
        'delivery_region',
        'message',
        'status',
        'staff_notes',
        'quoted_total',
        'currency_code',
        'valid_until',
        'assigned_to_user_id',
        'converted_order_id',
        'created_by_user_id',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'quoted_total' => 'decimal:2',
            'valid_until'  => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StorefrontQuoteItem::class, 'quote_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_REQUESTED,
            self::STATUS_REVIEWING,
            self::STATUS_QUOTED,
        ]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DECLINED,
            self::STATUS_EXPIRED,
            self::STATUS_CONVERTED,
        ], true);
    }
}
