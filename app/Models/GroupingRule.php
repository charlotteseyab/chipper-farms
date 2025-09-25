<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'conditions',
        'conditions_text',
        'feeding_standard_id',
        'active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'active' => 'boolean',
    ];

    public function feedingStandard()
    {
        return $this->belongsTo(FeedingStandard::class);
    }
}
