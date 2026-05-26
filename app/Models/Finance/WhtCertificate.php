<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Withholding Tax certificate.
 *
 * One row per (supplier × tax-period) certificate. Generation rolls up
 * payment_allocations.withholding_amount for ACTIVE allocations whose
 * payment_date falls inside the period, grouped by counterparty.
 *
 * Lifecycle:
 *
 *   draft  — Generated. Re-running generation refreshes from live data.
 *
 *   issued — Locked snapshot. lines_snapshot captures the contributing
 *            allocation_ids at issue time. PDF rendered. Certificate
 *            number allocated. Underlying allocations cannot be reversed
 *            without first voiding the certificate.
 *
 *   void   — Issued certificate later voided. Original PDF preserved
 *            for audit. Reason required.
 *
 * @see \App\Services\Finance\WithholdingCertificates\WhtCertificateService
 * @see \App\Services\Finance\WithholdingCertificates\WhtPeriodCalculator
 */
class WhtCertificate extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOID   = 'void';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_VOID,
    ];

    public const PERIOD_KIND_QUARTER = 'quarter';
    public const PERIOD_KIND_MONTH   = 'month';
    public const PERIOD_KIND_YEAR    = 'year';

    protected $fillable = [
        'organization_id',
        'counterparty_id',
        'certificate_number',
        'period_kind',
        'period_year',
        'period_index',
        'period_from',
        'period_to',
        'currency_code',
        'total_amount_paid',
        'total_withheld',
        'payment_count',
        'allocation_count',
        'status',
        'lines_snapshot',
        'pdf_disk',
        'pdf_path',
        'supplier_name_snapshot',
        'supplier_tin_snapshot',
        'supplier_address_snapshot',
        'issuer_name_snapshot',
        'issuer_tin_snapshot',
        'notes',
        'created_by_user_id',
        'issued_at',
        'issued_by_user_id',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_year'        => 'integer',
            'period_index'       => 'integer',
            'period_from'        => 'date',
            'period_to'          => 'date',
            'total_amount_paid'  => 'decimal:2',
            'total_withheld'     => 'decimal:2',
            'payment_count'      => 'integer',
            'allocation_count'   => 'integer',
            'lines_snapshot'     => 'array',
            'issued_at'          => 'datetime',
            'voided_at'          => 'datetime',
        ];
    }

    /* relations */

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /* scopes */

    public function scopeDraft($query)  { return $query->where('status', self::STATUS_DRAFT); }
    public function scopeIssued($query) { return $query->where('status', self::STATUS_ISSUED); }
    public function scopeVoid($query)   { return $query->where('status', self::STATUS_VOID); }

    public function scopeNotVoid($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_ISSUED]);
    }

    /**
     * Active certificates covering a given (counterparty_id, date).
     * Used by the allocation-reverse guard to stop reversal of
     * allocations underlying issued certificates.
     */
    public function scopeActiveCovering($query, int $counterpartyId, \DateTimeInterface $date)
    {
        $iso = \Carbon\CarbonImmutable::instance($date)->toDateString();
        return $query
            ->where('counterparty_id', $counterpartyId)
            ->where('status', self::STATUS_ISSUED)
            ->where('period_from', '<=', $iso)
            ->where('period_to',   '>=', $iso);
    }

    /* predicates */

    public function isDraft(): bool  { return $this->status === self::STATUS_DRAFT; }
    public function isIssued(): bool { return $this->status === self::STATUS_ISSUED; }
    public function isVoid(): bool   { return $this->status === self::STATUS_VOID; }

    public function canIssue(): bool
    {
        return $this->isDraft()
            && bccomp((string) $this->total_withheld, '0', 2) > 0;
    }

    public function canRefresh(): bool
    {
        // Drafts can be refreshed from live data. Issued certificates
        // are frozen.
        return $this->isDraft();
    }

    public function canVoid(): bool
    {
        return $this->isIssued();
    }

    /**
     * Display label for the period, e.g. "Q4 2026" or "December 2026".
     */
    public function periodLabel(): string
    {
        return match ($this->period_kind) {
            self::PERIOD_KIND_QUARTER => "Q{$this->period_index} {$this->period_year}",
            self::PERIOD_KIND_MONTH   => sprintf('%s %d',
                                                 \Carbon\CarbonImmutable::createFromDate($this->period_year, $this->period_index, 1)->format('F'),
                                                 $this->period_year),
            self::PERIOD_KIND_YEAR    => (string) $this->period_year,
            default                   => "{$this->period_kind} {$this->period_index}/{$this->period_year}",
        };
    }
}
