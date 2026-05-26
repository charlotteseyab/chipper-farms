<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An investor (person/entity) holding equity or preferred-share capital.
 */
class Investor extends Model
{
    use BelongsToOrganization;

    public const CLASS_EQUITY    = 'equity';
    public const CLASS_PREFERRED = 'preferred';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'name',
        'reference',
        'investor_class',
        'counterparty_id',
        'linked_loan_id',
        'ownership_percentage',
        'preferred_share_capital',
        'preferred_return_rate',
        'status',
        'contact_email',
        'contact_phone',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percentage'    => 'decimal:6',
            'preferred_share_capital' => 'decimal:2',
            'preferred_return_rate'   => 'decimal:6',
        ];
    }

    public function capitalAccount(): HasOne
    {
        return $this->hasOne(InvestorCapitalAccount::class);
    }

    public function capitalAccounts(): HasMany
    {
        return $this->hasMany(InvestorCapitalAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestorTransaction::class)->orderBy('txn_date');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function linkedLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'linked_loan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeEquity(Builder $q): Builder
    {
        return $q->where('investor_class', self::CLASS_EQUITY);
    }

    public function isPreferred(): bool
    {
        return $this->investor_class === self::CLASS_PREFERRED;
    }
}
