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
 * A storefront order. Commerce source-of-truth and (from Phase 4) a clean
 * source module for finance. Phase 3 transitions orders but posts nothing to
 * finance.
 *
 * Order status machine (enforced in OrderService):
 *
 *   pending_payment ─(paystack verified)─▶ payment_confirmed ─┐
 *   pending_confirmation ───────────────────────────────────┤
 *                                                            ├▶ confirmed ─▶ ready_for_pickup ─▶ completed
 *                                                            │                out_for_delivery ─▶ completed
 *   (any pre-fulfilment) ─▶ cancelled
 *   pending_payment ─(gateway failed)─▶ failed
 *
 * IMPORTANT: payment_confirmed is NOT confirmed. Successful payment never
 * auto-accepts an order — staff confirm explicitly.
 */
class StorefrontOrder extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $table = 'storefront_orders';

    // --- Order status ---
    public const STATUS_DRAFT                = 'draft';
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_PENDING_PAYMENT      = 'pending_payment';
    public const STATUS_PAYMENT_SUBMITTED    = 'payment_submitted';
    public const STATUS_PAYMENT_UNDER_REVIEW = 'payment_under_review';
    public const STATUS_PAYMENT_CONFIRMED    = 'payment_confirmed';
    public const STATUS_CONFIRMED            = 'confirmed';
    public const STATUS_READY_FOR_PICKUP     = 'ready_for_pickup';
    public const STATUS_OUT_FOR_DELIVERY     = 'out_for_delivery';
    public const STATUS_COMPLETED            = 'completed';
    public const STATUS_CANCELLED            = 'cancelled';
    public const STATUS_REFUND_REQUESTED     = 'refund_requested';
    public const STATUS_REFUND_APPROVED      = 'refund_approved';
    public const STATUS_REFUNDED             = 'refunded';
    public const STATUS_FAILED               = 'failed';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_PENDING_CONFIRMATION, self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAYMENT_SUBMITTED, self::STATUS_PAYMENT_UNDER_REVIEW, self::STATUS_PAYMENT_CONFIRMED,
        self::STATUS_CONFIRMED, self::STATUS_READY_FOR_PICKUP, self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_REFUND_REQUESTED,
        self::STATUS_REFUND_APPROVED, self::STATUS_REFUNDED, self::STATUS_FAILED,
    ];

    // --- Payment status ---
    public const PAY_UNPAID         = 'unpaid';
    public const PAY_COD            = 'cash_on_delivery';
    public const PAY_PAYSTACK_PEND  = 'paystack_pending';
    public const PAY_PAYSTACK_VERIF = 'paystack_verified';
    public const PAY_PARTIAL        = 'partially_paid';
    public const PAY_PAID           = 'paid';
    public const PAY_REFUND_PENDING = 'refund_pending';
    public const PAY_REFUNDED       = 'refunded';
    public const PAY_FAILED         = 'failed';

    // --- Methods ---
    public const METHOD_COD      = 'cod';
    public const METHOD_PAYSTACK = 'paystack';

    public const FULFIL_PICKUP   = 'pickup';
    public const FULFIL_DELIVERY = 'delivery';

    protected $fillable = [
        'organization_id', 'reference', 'counterparty_id', 'customer_user_id', 'quote_id',
        'status', 'payment_status', 'payment_method', 'fulfilment_method',
        'shipping_address_id', 'ship_to_name', 'ship_to_phone',
        'ship_address_line_1', 'ship_address_line_2', 'ship_city', 'ship_region', 'ship_ghana_post_gps',
        'subtotal', 'delivery_fee', 'total', 'currency_code',
        'customer_note', 'staff_notes',
        'confirmed_by_user_id', 'confirmed_at', 'cancelled_by_user_id', 'cancelled_at', 'cancel_reason',
        'placed_at', 'invoice_id', 'receipt_id', 'finance_posting_status', 'finance_posting_error',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'     => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total'        => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'placed_at'    => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StorefrontOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StorefrontOrderPayment::class, 'order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(StorefrontOrderEvent::class, 'order_id')->latest();
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('customer_user_id', $userId);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', [
            self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_REFUNDED, self::STATUS_FAILED,
        ]);
    }

    public function isCancellable(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_REFUNDED,
            self::STATUS_OUT_FOR_DELIVERY,
        ], true);
    }

    public function isConfirmable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_CONFIRMATION,
            self::STATUS_PAYMENT_CONFIRMED,
        ], true);
    }
}
