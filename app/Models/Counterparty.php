<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 2a — Counterparty extended with category, currency, discount,
 * credit-hold, sales aggregates, and relationships to addresses/contacts/
 * documents/profile.
 *
 * The base columns from Phase 0.8 are preserved exactly. New columns are
 * additive (see migration 2026_06_01_120500_extend_counterparties_for_phase_2).
 */
class Counterparty extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_SUPPLIER = 'supplier';
    public const TYPE_BOTH     = 'both';

    protected $fillable = [
        // Phase 0.8 base columns
        'organization_id',
        'type',
        'code',
        'name',
        'legal_name',
        'tax_id',
        'registration_number',
        'primary_contact_name',
        'phone',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'country',
        'default_payment_method_id',
        'default_tax_code_id',
        'payment_terms_days',
        'credit_limit',
        'credit_hold_mode',
        'is_active',
        'notes',

        // Phase 2a additions
        'customer_category_id',
        'currency_code',
        'discount_percent',
        'on_credit_hold',
        'credit_hold_reason',
        'internal_notes',
        'first_sale_at',
        'last_sale_at',
        'total_sales_value',

        // Phase 3a additions (supplier-side)
        'supplier_category_id',
        'default_expense_account_id',
        'default_payment_method_for_ap_id',
        'bank_account_name',
        'bank_account_number',
        'bank_name',
        'bank_branch',
        'bank_swift',
        'momo_provider',
        'momo_number',
        'momo_account_name',
        'withholding_percent',
        'withholding_exempt',
        'first_purchase_at',
        'last_purchase_at',
        'total_purchases_value',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms_days'  => 'integer',
            'credit_limit'        => 'decimal:2',
            'is_active'           => 'boolean',

            // Phase 2a casts
            'discount_percent'    => 'decimal:2',
            'on_credit_hold'      => 'boolean',
            'first_sale_at'       => 'datetime',
            'last_sale_at'        => 'datetime',
            'total_sales_value'   => 'decimal:2',

            // Phase 3a casts
            'withholding_percent'    => 'decimal:2',
            'withholding_exempt'     => 'boolean',
            'first_purchase_at'      => 'datetime',
            'last_purchase_at'       => 'datetime',
            'total_purchases_value'  => 'decimal:2',
        ];
    }

    /* ---------- relations ---------- */

    public function supplierCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Finance\SupplierCategory::class);
    }

    public function defaultExpenseAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Finance\Account::class, 'default_expense_account_id');
    }

    public function defaultPaymentMethodForAp(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\PaymentMethod::class, 'default_payment_method_for_ap_id');
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function defaultTaxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'default_tax_code_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CounterpartyAddress::class)->orderByDesc('is_default');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CounterpartyContact::class)->orderByDesc('is_primary');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CounterpartyDocument::class)->latest();
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    /* ---------- scopes ---------- */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeCustomers(Builder $q): Builder
    {
        return $q->whereIn('type', [self::TYPE_CUSTOMER, self::TYPE_BOTH]);
    }

    public function scopeSuppliers(Builder $q): Builder
    {
        return $q->whereIn('type', [self::TYPE_SUPPLIER, self::TYPE_BOTH]);
    }

    public function scopeOnCreditHold(Builder $q): Builder
    {
        return $q->where('on_credit_hold', true);
    }

    /* ---------- helpers ---------- */

    public function isCustomer(): bool
    {
        return in_array($this->type, [self::TYPE_CUSTOMER, self::TYPE_BOTH], true);
    }

    public function isSupplier(): bool
    {
        return in_array($this->type, [self::TYPE_SUPPLIER, self::TYPE_BOTH], true);
    }

        /**
     * Has this supplier ever been billed?
     * Useful for "active vendor" reports (suppliers with at least one
     * historical purchase, distinct from suppliers merely set up in master).
     */
    public function hasBeenBilled(): bool
    {
        return $this->first_purchase_at !== null;
    }

    /**
     * Effective withholding tax rate to apply on payments to this supplier.
     *   - If withholding_exempt: 0
     *   - If withholding_percent set: that value
     *   - Else: supplier_category.default_withholding_percent
     *   - Else: 0
     */
    public function effectiveWithholdingPercent(): string
    {
        if ($this->withholding_exempt) {
            return '0.00';
        }
        if ($this->withholding_percent !== null) {
            return (string) $this->withholding_percent;
        }
        if ($this->supplierCategory && $this->supplierCategory->default_withholding_percent !== null) {
            return (string) $this->supplierCategory->default_withholding_percent;
        }
        return '0.00';
    }

    /**
     * Resolves the GL account for an expense to this vendor when no
     * line-level or item-level override applies.
     *
     * Resolution order:
     *   1. counterparties.default_expense_account_id (this vendor's preferred)
     *   2. supplier_category.default_expense_account_id (category default)
     *   3. null (caller falls through to COA fallback '6010')
     */
    public function resolveDefaultExpenseAccountId(): ?int
    {
        if ($this->default_expense_account_id) {
            return (int) $this->default_expense_account_id;
        }
        if ($this->supplierCategory && $this->supplierCategory->default_expense_account_id) {
            return (int) $this->supplierCategory->default_expense_account_id;
        }
        return null;
    }

    /**
     * Effective discount = customer override if non-zero, else category default.
     * Phase 2b's invoice posting reads this.
     */
    public function effectiveDiscountPercent(): string
    {
        $own = (string) ($this->discount_percent ?? '0');
        if (bccomp($own, '0', 2) > 0) {
            return $own;
        }
        return (string) ($this->category?->default_discount_percent ?? '0');
    }

    /**
     * Effective payment terms days = customer override if set, else category default.
     */
    public function effectivePaymentTermsDays(): ?int
    {
        if ($this->payment_terms_days !== null) {
            return $this->payment_terms_days;
        }
        return $this->category?->default_payment_terms_days;
    }

    /**
     * Effective credit limit = customer override if set, else category default,
     * else null (no limit).
     */
    public function effectiveCreditLimit(): ?string
    {
        if ($this->credit_limit !== null) {
            return (string) $this->credit_limit;
        }
        $cat = $this->category?->default_credit_limit;
        return $cat !== null ? (string) $cat : null;
    }

    
}
