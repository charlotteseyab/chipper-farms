<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseStockIntake extends Model
{
    use SoftDeletes;
    use BelongsToOrganization;

    public const STATUS_PENDING_RECEIVE = 'pending_receive';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_QUARANTINED = 'quarantined';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ADDED_TO_STOCK = 'added_to_stock';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const FINANCE_NOT_CONFIGURED = 'not_configured';
    public const FINANCE_PENDING = 'pending';
    public const FINANCE_POSTED = 'posted';
    public const FINANCE_FAILED = 'failed';

    public const CATEGORY_PIGLET = 'piglet';
    public const CATEGORY_GROWER = 'grower';
    public const CATEGORY_FINISHER = 'finisher';
    public const CATEGORY_BREEDING_STOCK = 'breeding_stock';

    protected $fillable = [
        'batch_number',
        'organization_id',
        'supplier_name',
        'purchase_date',
        'document_reference',
        'animal_category',
        'breed',
        'quantity_ordered',
        'quantity_received',
        'quantity_rejected',
        'male_quantity',
        'female_quantity',
        'estimated_age_days',
        'estimated_date_of_birth',
        'average_weight_kg',
        'total_purchase_cost',
        'transport_cost',
        'status',
        'room_id',
        'group_id',
        'health_status',
        'health_notes',
        'notes',
        'created_by',
        'received_by',
        'received_at',
        'quarantined_by',
        'quarantined_at',
        'quarantine_release_date',
        'approved_by',
        'approved_at',
        'added_to_stock_by',
        'added_to_stock_at',
        'acquisition_journal_batch_id',
        'finance_posting_status',
        'finance_posting_message',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'estimated_date_of_birth' => 'date',
        'average_weight_kg' => 'decimal:2',
        'total_purchase_cost' => 'decimal:4',
        'transport_cost' => 'decimal:4',
        'received_at' => 'datetime',
        'quarantined_at' => 'datetime',
        'quarantine_release_date' => 'date',
        'approved_at' => 'datetime',
        'added_to_stock_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function pigs(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'purchase_stock_intake_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function nextBatchNumber(?int $year = null): string
    {
        $year ??= (int) now()->year;
        $prefix = "PP-{$year}-";
        $last = static::withTrashed()
            ->where('batch_number', 'like', $prefix . '%')
            ->orderByDesc('batch_number')
            ->value('batch_number');

        $next = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public static function activePigletQuantity(): int
    {
        return StockMovement::purchasedPigletQuantity();
    }

    public function isPigletBatch(): bool
    {
        return $this->animal_category === self::CATEGORY_PIGLET;
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', ucfirst($this->status));
    }

    public function receivedQuantity(): int
    {
        return (int) ($this->quantity_received ?? 0);
    }

    public function totalLandedCost(): float
    {
        return (float) ($this->total_purchase_cost ?? 0) + (float) ($this->transport_cost ?? 0);
    }

    public function costPerReceivedAnimal(): float
    {
        $received = $this->receivedQuantity();
        return $received > 0 ? $this->totalLandedCost() / $received : 0.0;
    }
}
