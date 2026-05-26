<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRunLine extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'payment_run_id',
        'vendor_invoice_id',
        'counterparty_id',
        'amount',
        'withholding_amount',
        'payment_method_id',
        'source_account_id',
        'payment_id',
        'payment_allocation_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'              => 'decimal:2',
            'withholding_amount'  => 'decimal:2',
        ];
    }

    public function paymentRun(): BelongsTo
    {
        return $this->belongsTo(PaymentRun::class);
    }

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class);
    }

    public function isExecuted(): bool
    {
        return $this->payment_id !== null;
    }

    /** Net cash for this run line — what would actually leave the bank. */
    public function netAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->withholding_amount, 2);
    }
}
