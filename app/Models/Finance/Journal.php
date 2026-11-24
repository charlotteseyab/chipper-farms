<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\FiscalPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'journal_batch_id',
        'journal_number',
        'journal_type',
        'posting_date',
        'fiscal_period_id',
        'reference',
        'description',
        'currency_code',
        'exchange_rate',
        'total_debit',
        'total_credit',
        'total_debit_tc',
        'total_credit_tc',
        'status',
        'source_type',
        'source_id',
        'reverses_journal_id',
    ];

    protected function casts(): array
    {
        return [
            'posting_date'    => 'date',
            'exchange_rate'   => 'decimal:8',
            'total_debit'     => 'decimal:2',
            'total_credit'    => 'decimal:2',
            'total_debit_tc'  => 'decimal:2',
            'total_credit_tc' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_number');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_id');
    }

    public function scopePosted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED);
    }

    /** Posted history remains in the ledger after reversal; rejected drafts do not. */
    public function scopeAffectingLedger(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('journals.status', self::STATUS_POSTED)
                ->orWhere(function (Builder $reversed) {
                    $reversed->where('journals.status', self::STATUS_REVERSED)
                        ->whereHas('batch', fn (Builder $batch) => $batch->whereNotNull('posted_at')->orWhereNotNull('reversed_by_batch_id'));
                });
        });
    }

    public function scopeForPeriod(Builder $q, int $periodId): Builder
    {
        return $q->where('fiscal_period_id', $periodId);
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function isBalanced(): bool
    {
        // Compare bcdiv-style to avoid float-comparison artefacts on
        // decimal:2 values. Using string equality is reliable here because
        // both columns share the same scale.
        return (string) $this->total_debit === (string) $this->total_credit;
    }
}
