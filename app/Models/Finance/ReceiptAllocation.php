<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiptAllocation extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'receipt_id',
        'invoice_id',
        'amount',
        'allocation_date',
        'status',
        'reversed_by_allocation_id',
        'created_by_user_id',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocation_date' => 'date',
            'amount'          => 'decimal:2',
            'reversed_at'     => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function reversedByAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_allocation_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool   { return $this->status === self::STATUS_ACTIVE; }
    public function isReversed(): bool { return $this->status === self::STATUS_REVERSED; }
}
