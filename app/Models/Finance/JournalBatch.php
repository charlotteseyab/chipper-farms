<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\DomainEvent;
use App\Models\FiscalPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalBatch extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'batch_number',
        'batch_type',
        'posting_date',
        'fiscal_period_id',
        'status',
        'description',
        'source_module',
        'source_type',
        'source_id',
        'domain_event_id',
        'reverses_batch_id',
        'reversed_by_batch_id',
        'proposed_by_user_id',
        'proposed_at',
        'posted_by_user_id',
        'posted_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'proposed_at' => 'datetime',
            'posted_at'   => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function postings(): HasMany
    {
        return $this->hasMany(JournalPosting::class);
    }

    public function domainEvent(): BelongsTo
    {
        return $this->belongsTo(DomainEvent::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_batch_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_batch_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function scopePosted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED);
    }

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }
}
