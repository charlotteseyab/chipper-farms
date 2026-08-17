<?php

namespace App\Models\Finance;

use App\Models\Counterparty;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A farm purchase (Phase 6b, blueprint §24).
 *
 * `settlement_batch_id` is the only journal this workflow owns. The inventory
 * leg belongs to RawMaterialReceivedSubscriber — see the migration.
 */
class SimplePurchase extends Model
{
    use SoftDeletes;

    public const SETTLEMENT_PAID_NOW  = 'paid_now';
    public const SETTLEMENT_PAY_LATER = 'pay_later';

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_FAILED = 'failed';

    protected $table = 'simple_purchases';

    protected $fillable = [
        'organization_id', 'purchase_number', 'purchase_date', 'supplier_id',
        'supplier_invoice_reference', 'settlement', 'money_account_id', 'payment_method_key',
        'payment_account_code', 'external_reference', 'subtotal', 'tax_amount',
        'total_amount', 'currency_code', 'tax_profile_id', 'tax_snapshot',
        'status', 'posting_error', 'settlement_batch_id', 'vendor_invoice_id',
        'recorded_by_user_id', 'idempotency_key', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal'      => 'decimal:2',
            'tax_amount'    => 'decimal:2',
            'total_amount'  => 'decimal:2',
            'tax_snapshot'  => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SimplePurchaseLine::class);
    }

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'settlement_batch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'money_account_id');
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(FinanceAttachment::class, 'attachable');
    }

    public function isPaidNow(): bool
    {
        return $this->settlement === self::SETTLEMENT_PAID_NOW;
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
