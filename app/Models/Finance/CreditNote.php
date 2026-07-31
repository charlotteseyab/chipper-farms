<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'company_id',
        'credit_note_number',
        'original_invoice_id',
        'original_order_id',
        'sales_return_id',
        'customer_id',
        'currency_code',
        'subtotal',
        'tax_total',
        'total',
        'issue_date',
        'accounting_date',
        'reason',
        'status',
        'approved_by_user_id',
        'approved_at',
        'posted_by_user_id',
        'posted_at',
        'journal_batch_id',
        'idempotency_key',
        'offset_account_id',
        'created_by_user_id',
        'updated_by_user_id',
        'reversal_of_credit_note_id',
        'reversed_by_credit_note_id',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'accounting_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'customer_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
