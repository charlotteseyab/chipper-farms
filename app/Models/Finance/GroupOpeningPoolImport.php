<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 5 — group opening pool import audit row.
 *
 * Cutover audit. Each import attempt for the pig pools gets one row
 * here, mirroring Phase 4's InventoryOpeningBalanceImport pattern
 * exactly.
 *
 * Lifecycle: pending → completed | failed | archived.
 */
class GroupOpeningPoolImport extends Model
{
    use BelongsToOrganization;

    public const MODE_MANUAL = 'manual';
    public const MODE_CSV    = 'csv';

    public const ALLOWED_MODES = [
        self::MODE_MANUAL,
        self::MODE_CSV,
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_ARCHIVED  = 'archived';

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'organization_id',
        'import_mode',
        'import_hash',
        'original_filename',
        'cutover_date',
        'row_count',
        'status',
        'error_message',
        'performed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'cutover_date' => 'date',
        'row_count'    => 'integer',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(GroupCostMovement::class, 'opening_import_id');
    }

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isFailed(): bool    { return $this->status === self::STATUS_FAILED; }
    public function isArchived(): bool  { return $this->status === self::STATUS_ARCHIVED; }

    /**
     * Number of group_cost_movements rows actually persisted by this
     * import. Differs from row_count (declared) only when something
     * went wrong; the gap is the diagnostic signal.
     */
    public function persistedRowCount(): int
    {
        return $this->movements()->count();
    }
}
