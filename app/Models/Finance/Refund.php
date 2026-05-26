<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'refund_number',
        'refund_date',
        'counterparty_id',
        'currency_code',
        'exchange_rate',
        'amount',
        'payment_method_id',
        'source_receipt_id',
        'external_reference',
        'reference_type',
        'status',
        'journal_batch_id',
        'reversed_by_journal_batch_id',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'reason',
        'description',
        'notes',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'refund_date'   => 'date',
            'amount'        => 'decimal:2',
            'exchange_rate' => 'decimal:10',
            'approved_at'   => 'datetime',
            'reversed_at'   => 'datetime',
        ];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function sourceReceipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'source_receipt_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function reversedByJournalBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'reversed_by_journal_batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function scopeDraft(Builder $q): Builder    { return $q->where('status', self::STATUS_DRAFT); }
    public function scopePosted(Builder $q): Builder   { return $q->where('status', self::STATUS_POSTED); }
    public function scopeReversed(Builder $q): Builder { return $q->where('status', self::STATUS_REVERSED); }

    public function isDraft(): bool    { return $this->status === self::STATUS_DRAFT; }
    public function isPosted(): bool   { return $this->status === self::STATUS_POSTED; }
    public function isReversed(): bool { return $this->status === self::STATUS_REVERSED; }

    public function canPost(): bool    { return $this->isDraft(); }
    public function canCancel(): bool  { return $this->isDraft(); }
    public function canReverse(): bool
    {
        return $this->isPosted() && $this->reversed_by_journal_batch_id === null;
    }
}
