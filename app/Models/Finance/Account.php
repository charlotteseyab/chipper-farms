<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 1a — Chart of Accounts entry.
 *
 * Phase 1+ posting always references account_id. Codes are user-visible
 * but not used as foreign keys (codes can theoretically be renamed in a
 * controlled migration; ids cannot).
 *
 * Stored under namespace App\Models\Finance to avoid cluttering the
 * existing App\Models root namespace, which has many operational models.
 *
 * Mass-assignment: every column except id, timestamps, and soft-delete
 * is fillable. Phase 1+ services control updates explicitly; the wide
 * fillable array is fine because routes never accept account_type or
 * is_system from user input (validation in form requests rejects them).
 */
class Account extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $table = 'chart_of_accounts';

    public const TYPE_ASSET     = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY    = 'equity';
    public const TYPE_REVENUE   = 'revenue';
    public const TYPE_EXPENSE   = 'expense';

    public const NORMAL_BALANCE_DEBIT  = 'debit';
    public const NORMAL_BALANCE_CREDIT = 'credit';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'account_type',
        'account_subtype',
        'parent_id',
        'currency_code',
        'is_control',
        'is_active',
        'is_system',
        'allow_manual_journal',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_control'           => 'boolean',
            'is_active'            => 'boolean',
            'is_system'            => 'boolean',
            'allow_manual_journal' => 'boolean',
            'display_order'        => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('display_order')
            ->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('account_type', $type);
    }

    public function scopeAcceptsManualJournal(Builder $q): Builder
    {
        return $q->where('allow_manual_journal', true);
    }

    /**
     * Asset and Expense accounts have a debit normal balance.
     * Liability, Equity, and Revenue accounts have a credit normal balance.
     */
    public function normalBalance(): string
    {
        return in_array($this->account_type, [self::TYPE_ASSET, self::TYPE_EXPENSE], true)
            ? self::NORMAL_BALANCE_DEBIT
            : self::NORMAL_BALANCE_CREDIT;
    }

    public function isDebitNormal(): bool
    {
        return $this->normalBalance() === self::NORMAL_BALANCE_DEBIT;
    }

    public function isCreditNormal(): bool
    {
        return $this->normalBalance() === self::NORMAL_BALANCE_CREDIT;
    }
}
