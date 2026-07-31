<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\DomainEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The idempotency tracker. NEVER create rows here directly — always go
 * through PostingEngine::post(), which inserts the row atomically with
 * the journal_batch creation.
 *
 * The UNIQUE index on (organization_id, idempotency_key) is the central
 * guarantee that prevents duplicate journals from being written if a
 * domain event is delivered twice (queue retry, manual replay, etc.).
 *
 * The model is read-mostly. PostingEngine writes; reports + admin tools read.
 */
class JournalPosting extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const PURPOSE_REVENUE              = 'revenue';
    public const PURPOSE_COGS                 = 'cogs';
    public const PURPOSE_CASH_RECEIPT         = 'cash_receipt';
    public const PURPOSE_INVENTORY_RECEIPT    = 'inventory_receipt';
    public const PURPOSE_INVENTORY_CONSUMPTION= 'inventory_consumption';
    public const PURPOSE_BIOLOGICAL_DISPOSAL  = 'biological_disposal';
    public const PURPOSE_BIOLOGICAL_WRITEOFF  = 'biological_writeoff';
    public const PURPOSE_FEED_PRODUCTION      = 'feed_production';
    public const PURPOSE_FX_REVALUATION       = 'fx_revaluation';
    public const PURPOSE_LOAN_DRAWDOWN        = 'loan_drawdown';
    public const PURPOSE_LOAN_REPAYMENT       = 'loan_repayment';
    public const PURPOSE_LOAN_INTEREST_ACCRUAL= 'loan_interest_accrual';
    public const PURPOSE_INVESTOR_CONTRIBUTION= 'investor_contribution';
    public const PURPOSE_INVESTOR_DISTRIBUTION= 'investor_distribution';
    public const PURPOSE_REFUND               = 'refund';
    public const PURPOSE_VENDOR_INVOICE       = 'vendor_invoice';
    public const PURPOSE_CASH_PAYMENT         = 'cash_payment';
    public const PURPOSE_PAYMENT_ALLOCATION   = 'payment_allocation';
    public const PURPOSE_CREDIT_NOTE          = 'credit_note';

    /*
     * Sales returns (CF-P0-008).
     *
     * Distinct purposes rather than reusing PURPOSE_COGS/PURPOSE_REVENUE: the
     * idempotency key is built from (event_type, source_type, source_id,
     * purpose), and a return must be able to coexist with the original sale's
     * postings without either being mistaken for a duplicate of the other.
     * Separate purposes also make "how much did we reverse this period" a
     * single query rather than an inference.
     */
    public const PURPOSE_SALES_RETURN_COGS    = 'sales_return_cogs';
    public const PURPOSE_SALES_RETURN_REVENUE = 'sales_return_revenue';

    protected $fillable = [
        'organization_id',
        'idempotency_key',
        'journal_batch_id',
        'domain_event_id',
        'posting_purpose',
        'subscriber_class',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function domainEvent(): BelongsTo
    {
        return $this->belongsTo(DomainEvent::class);
    }

    /**
     * Build the idempotency key for a given source event + purpose.
     * Used by PostingEngine to construct the key consistently.
     */
    public static function buildIdempotencyKey(
        string $eventType,
        string $sourceType,
        int|string $sourceId,
        string $postingPurpose,
    ): string {
        // Trim namespace from sourceType for compact keys.
        $shortSourceType = class_basename($sourceType);

        return sprintf('%s:%s:%s:%s', $eventType, $shortSourceType, $sourceId, $postingPurpose);
    }
}
