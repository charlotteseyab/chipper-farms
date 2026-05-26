<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'payment_id',
        'vendor_invoice_id',
        'amount',
        'withholding_amount',
        'allocation_date',
        'status',
        'reversal_of_allocation_id',
        'withholding_journal_batch_id',
        'reversed_by_journal_batch_id',
        'created_by_user_id',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'              => 'decimal:2',
            'withholding_amount'  => 'decimal:2',
            'allocation_date'     => 'date',
            'reversed_at'         => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function reversalOfAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class, 'reversal_of_allocation_id');
    }

    public function withholdingJournalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'withholding_journal_batch_id');
    }

    public function reversedByJournalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'reversed_by_journal_batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function scopeActive(Builder $q): Builder    { return $q->where('status', self::STATUS_ACTIVE); }
    public function scopeReversed(Builder $q): Builder  { return $q->where('status', self::STATUS_REVERSED); }

    public function isActive(): bool   { return $this->status === self::STATUS_ACTIVE; }
    public function isReversed(): bool { return $this->status === self::STATUS_REVERSED; }

    /** Net cash effect of this allocation on the supplier — what we actually paid them. */
    public function netAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->withholding_amount, 2);
    }
}
