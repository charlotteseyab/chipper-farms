<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientInventory extends Model
{
    use \App\Models\Concerns\BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'ingredient';
    }

    use HasFactory;

    protected $fillable = ['ingredient_id', 'quantity', 'unit', 'last_updated'];

    protected $casts = [
        'quantity' => 'float',
        'last_updated' => 'datetime',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
