<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRun extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_EXECUTED  = 'executed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED  = 'reversed';

    protected $fillable = [
        'organization_id',
        'run_number',
        'run_date',
        'currency_code',
        'default_payment_method_id',
        'default_source_account_id',
        'status',
        'total_gross',
        'total_withholding',
        'total_net',
        'payment_count',
        'created_by_user_id',
        'executed_by_user_id',
        'executed_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'run_date'           => 'date',
            'total_gross'        => 'decimal:2',
            'total_withholding'  => 'decimal:2',
            'total_net'          => 'decimal:2',
            'payment_count'      => 'integer',
            'executed_at'        => 'datetime',
            'cancelled_at'       => 'datetime',
            'reversed_at'        => 'datetime',
        ];
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function defaultSourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_source_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentRunLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function scopeDraft(Builder $q): Builder      { return $q->where('status', self::STATUS_DRAFT); }
    public function scopeExecuted(Builder $q): Builder   { return $q->where('status', self::STATUS_EXECUTED); }
    public function scopeCancelled(Builder $q): Builder  { return $q->where('status', self::STATUS_CANCELLED); }
    public function scopeReversed(Builder $q): Builder   { return $q->where('status', self::STATUS_REVERSED); }

    public function isDraft(): bool     { return $this->status === self::STATUS_DRAFT; }
    public function isExecuted(): bool  { return $this->status === self::STATUS_EXECUTED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
    public function isReversed(): bool  { return $this->status === self::STATUS_REVERSED; }

    public function canExecute(): bool
    {
        return $this->isDraft() && $this->lines()->exists();
    }

    public function canCancel(): bool
    {
        return $this->isDraft();
    }

    public function canReverse(): bool
    {
        if (! $this->isExecuted()) {
            return false;
        }
        // If any child payment was already independently reversed, the
        // run-level reverse becomes ambiguous — treat as can't reverse.
        $anyAlreadyReversed = $this->payments()
            ->whereNotIn('status', [Payment::STATUS_POSTED])
            ->exists();
        return ! $anyAlreadyReversed;
    }
}
