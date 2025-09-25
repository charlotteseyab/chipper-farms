<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedRecipe extends Model
{
    use HasFactory;

    protected $fillable = ['feed_type_id', 'name'];

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedRecipeItem::class);
    }
}
