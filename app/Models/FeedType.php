<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeedType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'protein_percent',
        'energy_mj_per_kg',
        'fiber_percent',
        'cost_per_unit',
        'unit',
    ];

    public function recipe(): HasOne
    {
        return $this->hasOne(FeedRecipe::class);
    }

    public function getComputedProteinPercentAttribute(): ?float
    {
        $recipe = $this->recipe()->with('items.ingredient')->first();
        if (!$recipe) return null;
        $sum = 0.0;
        foreach ($recipe->items as $item) {
            $sum += (float)($item->percentage) * ((float)($item->ingredient->protein_percentage ?? 0)) / 100.0;
        }
        return round($sum, 2);
    }

    public function getComputedEnergyKcalPerKgAttribute(): ?float
    {
        $recipe = $this->recipe()->with('items.ingredient')->first();
        if (!$recipe) return null;
        $sum = 0.0;
        foreach ($recipe->items as $item) {
            $sum += (float)($item->percentage) * ((float)($item->ingredient->energy_kcal_per_kg ?? 0)) / 100.0;
        }
        return round($sum, 2);
    }

    public function getComputedCostPerKgAttribute(): ?float
    {
        $recipe = $this->recipe()->with('items.ingredient')->first();
        if (!$recipe) return null;
        $sum = 0.0;
        foreach ($recipe->items as $item) {
            $sum += ((float)$item->percentage / 100.0) * (float)($item->ingredient->cost_per_unit ?? 0);
        }
        return round($sum, 2);
    }
}
