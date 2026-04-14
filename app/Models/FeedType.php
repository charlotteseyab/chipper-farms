<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedType extends Model
{
    protected $fillable = [
        'name',
        'notes',
    ];

    public function recipes()
    {
        return $this->hasMany(FeedTypeRecipe::class);
    }

    public function batches()
    {
        return $this->hasMany(FeedBatch::class);
    }
}