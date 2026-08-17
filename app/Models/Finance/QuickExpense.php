<?php

namespace App\Models\Finance;

use App\Models\Counterparty;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The farm-facing record of an expense (Phase 4).
 *
 * The ledger's copy is the journal batch. This is the record a farm operator
 * recognises — see the migration for why both exist.
 *
 * Amounts are NOT cast to float. `decimal:2` keeps them as strings so bcmath
 * arithmetic downstream stays exact.
 */
class QuickExpense extends Model
{
    use SoftDeletes;

    public const SETTLEMENT_PAID_NOW  = 'paid_now';
    public const SETTLEMENT_PAY_LATER = 'pay_later';

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_FAILED = 'failed';

    public const ALLOCATION_FARM      = 'farm';
    public const ALLOCATION_PIG       = 'pig';
    public const ALLOCATION_GROUP     = 'group';
    public const ALLOCATION_STRUCTURE = 'structure';

    protected $table = 'quick_expenses';

    protected $fillable = [
        'organization_id',
        'expense_number',
        'expense_date',
        'category_key',
        'expense_account_code',
        'description',
        'amount',
        'tax_amount',
        'total_amount',
        'currency_code',
        'settlement',
        'money_account_id',
        'payment_method_key',
        'payment_account_code',
        'external_reference',
        'supplier_id',
        'allocation_type',
        'allocation_id',
        'tax_profile_id',
        'tax_snapshot',
        'status',
        'posting_error',
        'journal_batch_id',
        'vendor_invoice_id',
        'needs_reclassification',
        'recorded_by_user_id',
        'idempotency_key',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date'           => 'date',
            'amount'                 => 'decimal:2',
            'tax_amount'             => 'decimal:2',
            'total_amount'           => 'decimal:2',
            'tax_snapshot'           => 'array',
            'needs_reclassification' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'supplier_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'money_account_id');
    }

    /**
     * Receipt photos and PDFs (Phase 6, blueprint §23 item 8).
     *
     * Uses the existing finance attachment subsystem rather than a second store,
     * so private-disk placement, mime validation, size limits, organisation
     * scoping and the per-parent download authorisation in AttachmentController
     * all apply unchanged.
     */
    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(FinanceAttachment::class, 'attachable');
    }

    public function isPaidNow(): bool
    {
        return $this->settlement === self::SETTLEMENT_PAID_NOW;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /** Friendly category label, falling back to the raw key if unmapped. */
    public function categoryLabel(): string
    {
        return config("expense_categories.categories.{$this->category_key}.label")
            ?? $this->category_key;
    }

    public function paymentLabel(): ?string
    {
        if ($this->payment_method_key === null) {
            return null;
        }

        return config("expense_categories.payment_accounts.{$this->payment_method_key}.label")
            ?? $this->payment_method_key;
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
