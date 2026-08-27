<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'key', 'name', 'category', 'audience', 'subject',
        'body', 'action_label', 'is_active', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
