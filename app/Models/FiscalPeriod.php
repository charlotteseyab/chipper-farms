<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fiscal period (typically monthly). Status transitions:
 *   open   -> closed   (by accountant; can be reopened if year still open)
 *   closed -> locked   (by superuser; irreversible except via controlled override)
 *   closed -> open     (reopen; allowed only while year is open)
 *
 * Phase 0.8 provides the SCHEMA + MODEL ONLY. The transition enforcement
 * logic lives in Phase 1 when the posting engine can validate that no
 * journals have been posted into a period before closing / locking.
 */
class FiscalPeriod extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'organization_id',
        'fiscal_year_id',
        'period_number',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by_user_id',
        'locked_at',
        'locked_by_user_id',
        'notes',
        'first_journal_posted_at',
        'last_journal_posted_at',
        'posting_count',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'start_date'    => 'date',
            'end_date'      => 'date',
            'closed_at'     => 'datetime',
            'locked_at'     => 'datetime',
            'first_journal_posted_at' => 'datetime',
            'last_journal_posted_at'  => 'datetime',
            'posting_count' => 'integer',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function scopeCoveringDate(Builder $q, \DateTimeInterface|string $date): Builder
    {
        $d = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;
        return $q->whereDate('start_date', '<=', $d)->whereDate('end_date', '>=', $d);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /**
     * Can new postings land in this period?
     * Phase 1 posting engine uses this as the precondition check.
     */
    public function acceptsPostings(): bool
    {
        return $this->isOpen();
    }
}
