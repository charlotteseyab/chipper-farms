<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'organization_id',
        'measure',
        'unit',
        'level',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'level' => 'string', // Ensure the enum is treated as a string
    ];
}
