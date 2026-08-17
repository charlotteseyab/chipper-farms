<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bank account — Phase 3e.
 *
 * One row per (org, GL bank account). Adds the bank-specific metadata
 * needed for statement matching and reconciliation, on top of the
 * underlying chart-of-accounts row.
 *
 * Accessing the GL balance:
 *   This model does NOT cache live balance. Live balance comes from
 *   the GL via the existing posting engine. The cached field
 *   `last_reconciled_balance` is the closing-of-books figure from the
 *   most recent completed reconciliation — useful as a fast UI hint
 *   and as the carry-forward opening for the next period.
 */
class BankAccount extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    public const KIND_CASH = 'cash';
    public const KIND_BANK = 'bank';
    public const KIND_MOMO = 'momo';
    public const KIND_GATEWAY = 'gateway';

    public const STOREFRONT_COD = 'cod';
    public const STOREFRONT_PAYSTACK = 'paystack';
    public const STOREFRONT_MOMO = 'momo';
    public const STOREFRONT_BANK_TRANSFER = 'bank_transfer';

    protected $table = 'bank_accounts';

    protected $fillable = [
        'organization_id',
        'account_id',
        'account_kind',
        'label',
        'bank_name',
        'provider',
        'account_name',
        'account_number',
        'wallet_number',
        'swift_code',
        'branch',
        'currency_code',
        'opening_balance',
        'opening_balance_date',
        'last_reconciled_balance',
        'last_reconciled_date',
        'default_csv_format',
        'storefront_payment_method',
        'is_active',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance'         => 'decimal:2',
            'opening_balance_date'    => 'date',
            'last_reconciled_balance' => 'decimal:2',
            'last_reconciled_date'    => 'date',
            'is_active'               => 'boolean',
        ];
    }

    /* relations */

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class, 'bank_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class, 'bank_account_id');
    }

    /* scopes */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /* predicates */

    public function isUsable(): bool
    {
        return $this->is_active && $this->account_id !== null;
    }

    public function kindLabel(): string
    {
        return match ($this->account_kind) {
            self::KIND_CASH => 'Cash',
            self::KIND_MOMO => 'Mobile Money',
            self::KIND_GATEWAY => 'Payment Gateway',
            default => 'Bank Account',
        };
    }

    public function referenceLabel(): string
    {
        return match ($this->account_kind) {
            self::KIND_MOMO => $this->wallet_number ?: $this->account_number ?: 'No wallet number',
            self::KIND_GATEWAY => $this->provider ?: $this->bank_name,
            self::KIND_CASH => $this->branch ?: 'Cash on hand',
            default => $this->account_number ?: 'No account number',
        };
    }

    /**
     * Build a display name combining the practical money account + label.
     */
    public function displayName(): string
    {
        $primary = match ($this->account_kind) {
            self::KIND_MOMO => trim(($this->provider ?: $this->bank_name) . ' MoMo'),
            self::KIND_GATEWAY => $this->provider ?: $this->bank_name ?: 'Payment Gateway',
            self::KIND_CASH => 'Cash',
            default => $this->bank_name ?: 'Bank',
        };

        $bits = [$primary, $this->label];
        return implode(' — ', array_filter($bits));
    }
}
