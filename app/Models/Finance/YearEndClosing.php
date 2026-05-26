<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Year-end roll-forward record: links a fiscal year to the closing journal
 * that swept P&L into retained earnings, plus the computed net profit.
 */
class YearEndClosing extends Model
{
    use BelongsToOrganization;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_REVERSED    = 'reversed';

    protected $fillable = [
        'organization_id',
        'fiscal_year_id',
        'status',
        'closing_journal_batch_id',
        'reversal_journal_batch_id',
        'total_revenue',
        'total_expense',
        'net_profit',
        'retained_earnings_account_id',
        'current_year_earnings_account_id',
        'performed_by_user_id',
        'completed_at',
        'reversed_at',
        'reversed_by_user_id',
        'reversal_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue' => 'decimal:4',
            'total_expense' => 'decimal:4',
            'net_profit'    => 'decimal:4',
            'completed_at'  => 'datetime',
            'reversed_at'   => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function closingBatch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'closing_journal_batch_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_COMPLETED);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
