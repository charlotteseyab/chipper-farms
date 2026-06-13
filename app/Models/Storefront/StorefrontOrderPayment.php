<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontOrderPayment extends Model
{
    use BelongsToOrganization;

    protected $table = 'storefront_order_payments';

    public const PROVIDER_PAYSTACK = 'paystack';
    public const PROVIDER_COD      = 'cod';
    public const PROVIDER_MANUAL   = 'manual';

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED  = 'verified';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REFUNDED  = 'refunded';

    protected $fillable = [
        'organization_id', 'order_id', 'provider', 'reference', 'provider_reference',
        'amount', 'currency_code', 'status', 'raw_response', 'verified_at', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'raw_response' => 'array',
            'verified_at'  => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrder::class, 'order_id');
    }
}
