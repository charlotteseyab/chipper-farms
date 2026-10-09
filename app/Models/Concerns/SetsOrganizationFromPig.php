<?php

namespace App\Models\Concerns;

use App\Models\PigManagement;

/**
 * Health rows belong to their pig's organisation. Stamped from the pig on
 * create (including a sold or dead pig), so a row can never be filed under
 * a different farm from the animal it describes.
 */
trait SetsOrganizationFromPig
{
    public static function bootSetsOrganizationFromPig(): void
    {
        static::creating(function ($model): void {
            if (! $model->pig_id) {
                return;
            }
            $organizationId = PigManagement::withoutGlobalScopes()->withTrashed()->whereKey($model->pig_id)->value('organization_id');
            if ($organizationId !== null) {
                $model->organization_id = $organizationId;
            }
        });
    }
}
