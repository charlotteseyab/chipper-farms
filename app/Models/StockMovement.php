<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToOrganization;

    public const TYPE_STOCK_IN = 'stock_in';
    public const SOURCE_PURCHASE_STOCK_INTAKE = 'purchase_stock_intake';
    public const CATEGORY_PIGLET = 'piglet';

    protected $fillable = [
        'organization_id',
        'movement_type',
        'source_type',
        'source_id',
        'animal_category',
        'quantity',
        'movement_date',
        'created_by',
        'reference',
        'notes',
    ];

    protected $casts = [
        'movement_date' => 'datetime',
        'quantity' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function purchasedPigletQuantity(?int $organizationId = null): int
    {
        return (int) static::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('movement_type', self::TYPE_STOCK_IN)
            ->where('source_type', self::SOURCE_PURCHASE_STOCK_INTAKE)
            ->where('animal_category', self::CATEGORY_PIGLET)
            ->sum('quantity');
    }
}
