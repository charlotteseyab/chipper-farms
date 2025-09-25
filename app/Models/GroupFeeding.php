<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupFeeding extends Model
{
    protected $fillable = [
        'group_id',
        'feed_type_id',
        'amount',
        'unit',
        'date',
        'wastage',
        'recorded_by',
    ];

    protected static function booted(): void
    {
        static::created(function (GroupFeeding $feeding) {
            $feeding->deductIngredientInventory();
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function deductIngredientInventory(): void
    {
        $feedType = $this->feedType()->with('recipe.items.ingredient')->first();
        if (!$feedType || !$feedType->recipe) return;
        $amountKg = (float)$this->amount; // assume kg for now
        foreach ($feedType->recipe->items as $item) {
            $ingredient = $item->ingredient;
            if (!$ingredient) continue;
            $ingredientShareKg = $amountKg * ((float)$item->percentage / 100.0);
            $inv = IngredientInventory::firstOrCreate(
                ['ingredient_id' => $ingredient->id],
                ['quantity' => 0, 'unit' => $ingredient->unit ?? 'kg']
            );
            $inv->quantity = max(0, (float)$inv->quantity - $ingredientShareKg);
            $inv->last_updated = now();
            $inv->save();
        }
    }
}
