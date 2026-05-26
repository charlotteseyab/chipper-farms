<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 4 — audit row for an opening balance import attempt.
 *
 * Every opening balance upload (manual or CSV) creates one row here.
 * Idempotency is enforced via the (org, item_kind, import_hash) UNIQUE:
 * re-uploading the same payload returns the existing row instead of
 * duplicating.
 *
 * The row's status walks PENDING → COMPLETED on success, or
 * PENDING → FAILED on failure (transaction rolls back, error_message
 * captured for ops to investigate).
 *
 * The ARCHIVED status is set manually by an admin who decides a stuck
 * pending/failed row should be ignored — typically because the import
 * has already been retried successfully under a different hash, or
 * because the issue has been resolved out-of-band.
 */
class InventoryOpeningBalanceImport extends Model
{
    use BelongsToOrganization;

    public const KIND_RAW_MATERIAL  = 'raw_material';
    public const KIND_FINISHED_FEED = 'finished_feed';

    public const ALLOWED_KINDS = [
        self::KIND_RAW_MATERIAL,
        self::KIND_FINISHED_FEED,
    ];

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
        'item_kind',
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
        return $this->hasMany(InventoryMovement::class, 'opening_balance_import_id');
    }

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isFailed(): bool    { return $this->status === self::STATUS_FAILED; }
    public function isArchived(): bool  { return $this->status === self::STATUS_ARCHIVED; }

    /**
     * Number of actual inventory_movements rows that this import has
     * persisted. Differs from row_count (declared) only when something
     * went wrong; the discrepancy is itself the diagnostic signal.
     */
    public function persistedRowCount(): int
    {
        return $this->movements()->count();
    }
}
