<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedRecipeItem extends Model
{
    use HasFactory;

    protected $fillable = ['feed_recipe_id', 'ingredient_id', 'percentage'];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FeedRecipe::class, 'feed_recipe_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
