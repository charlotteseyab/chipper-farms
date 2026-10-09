<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'counted_at', 'reference', 'notes', 'counted_by', 'submission_key'];

    protected $casts = ['counted_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
