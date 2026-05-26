<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Categorises suppliers by procurement function.
 *
 * Provides:
 *   - Default GL coding for vendor invoices (default_expense_account_id)
 *   - Optional override of AP control account (ap_account_id)
 *   - Default payment terms in days (suppliers can override)
 *   - Default withholding tax treatment (suppliers can override)
 *
 * Standard categories (loaded by SupplierCategoriesSeeder):
 *   FEED_INGREDIENTS  inventory items → Dr Inventory
 *   VETERINARY        services + drugs → Dr Vet Expense
 *   UTILITIES         electricity/water → Dr Utilities Expense
 *   CAPEX             equipment, buildings → Dr Fixed Assets
 *   PROFESSIONAL      audit, legal → Dr Professional Fees
 *   GENERAL           catch-all → Dr Sundry Expense
 *
 * @see Phase 3a README §2 for the resolution hierarchy.
 */
class SupplierCategory extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'default_expense_account_id',
        'ap_account_id',
        'payment_terms_days',
        'default_withholding_percent',
        'default_withholding_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms_days'           => 'integer',
            'default_withholding_percent'  => 'decimal:2',
            'is_active'                    => 'boolean',
        ];
    }

    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_expense_account_id');
    }

    public function apAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ap_account_id');
    }

    public function defaultWithholdingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_withholding_account_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Counterparty::class, 'supplier_category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
