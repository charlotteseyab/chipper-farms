<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;

    /**
     * The attributes that are mass assignable.
     * Must include 'bag_weight_kg' for it to save from the controller's update/store methods.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'protein_percentage',
        'energy_kcal_per_kg',
        'fat_percentage',
        'fiber_percentage',
        'unit',
        'cost_per_unit',
        'bag_weight_kg',
    ];

    /**
     * Cast numeric fields for better handling.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'protein_percentage' => 'float',
        'energy_kcal_per_kg' => 'float',
        'fat_percentage' => 'float',
        'fiber_percentage' => 'float',
        'cost_per_unit' => 'float',
        'bag_weight_kg' => 'float', // Cast the new field
    ];
    
    // Define relationship with Inventory
    public function inventory()
    {
        return $this->hasOne(IngredientInventory::class);
    }

    // Define relationship with Recipe Items
    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }
}
