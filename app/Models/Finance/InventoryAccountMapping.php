<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 4 — settings row driving GL account routing for inventory.
 *
 * One row per (organization_id, item_kind, category). The `category`
 * column uses the DEFAULT_CATEGORY_TOKEN sentinel when the row is the
 * org-wide default for that kind.
 *
 * is_default is the human-readable flag; the technical enforcement of
 * "exactly one default per kind" is the unique constraint on
 * (org, kind, category) PLUS the convention that the default category
 * value is the sentinel.
 *
 * The form validation layer must enforce:
 *   - is_default=true AND category=DEFAULT_CATEGORY_TOKEN  → OK
 *   - is_default=false AND category=DEFAULT_CATEGORY_TOKEN → REJECT
 *   - is_default=true  AND category!=DEFAULT_CATEGORY_TOKEN → REJECT
 *   - is_default=false AND category='grain' (any user string) → OK
 */
class InventoryAccountMapping extends Model
{
    use BelongsToOrganization;

    public const KIND_RAW_MATERIAL  = 'raw_material';
    public const KIND_FINISHED_FEED = 'finished_feed';

    public const ALLOWED_KINDS = [
        self::KIND_RAW_MATERIAL,
        self::KIND_FINISHED_FEED,
    ];

    /**
     * Sentinel value used as `category` for the org-wide default row.
     * Chosen to be visually distinct from any real user-entered category
     * and impossible to collide with by accident.
     */
    public const DEFAULT_CATEGORY_TOKEN = '__default__';

    protected $fillable = [
        'organization_id',
        'item_kind',
        'category',
        'is_default',
        'inventory_account_id',
        'gr_ir_account_id',
        'adjustment_account_id',
        'cogs_account_id',
        'ppv_account_id',
        'suspense_account_id',
        'created_by_user_id',
        'updated_by_user_id',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function grIrAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gr_ir_account_id');
    }

    public function adjustmentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'adjustment_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function ppvAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ppv_account_id');
    }

    public function suspenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'suspense_account_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function isDefaultRow(): bool
    {
        return $this->is_default && $this->category === self::DEFAULT_CATEGORY_TOKEN;
    }
}
