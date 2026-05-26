<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\CostCentre;
use App\Models\PaymentMethod;
use App\Models\SalesChannel;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'journal_id',
        'line_number',
        'account_id',
        'debit',
        'credit',
        'debit_tc',
        'credit_tc',
        'currency_code',
        'exchange_rate',
        'description',
        'cost_centre_id',
        'sales_channel_id',
        'counterparty_id',
        'tax_code_id',
        'payment_method_id',
        'structure_id',
        'room_id',
        'group_id',
        'source_type',
        'source_id',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'line_number'   => 'integer',
            'debit'         => 'decimal:2',
            'credit'        => 'decimal:2',
            'debit_tc'      => 'decimal:2',
            'credit_tc'     => 'decimal:2',
            'exchange_rate' => 'decimal:8',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function scopeDebits(Builder $q): Builder
    {
        return $q->where('debit', '>', 0);
    }

    public function scopeCredits(Builder $q): Builder
    {
        return $q->where('credit', '>', 0);
    }

    public function scopeForAccount(Builder $q, int $accountId): Builder
    {
        return $q->where('account_id', $accountId);
    }

    public function isDebit(): bool
    {
        return (float) $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return (float) $this->credit > 0;
    }

    /**
     * Signed amount: positive for debit, negative for credit.
     * Useful for ledger views that show running balance.
     */
    public function signedAmount(): string
    {
        return $this->isDebit() ? (string) $this->debit : '-' . $this->credit;
    }
}
