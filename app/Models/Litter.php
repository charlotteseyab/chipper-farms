<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Litter extends Model
{
    protected $fillable = [
        'sow_id',
        'farrowing_date',
        'live_births',
        'stillbirths',
        'date_weaned',
        'notes',
    ];

    public function sow() {
        return $this->belongsTo(PigManagement::class, 'sow_id');
    }
}
