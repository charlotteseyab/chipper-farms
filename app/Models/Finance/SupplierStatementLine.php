<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line item from a supplier's printed statement.
 *
 * Records what the supplier said (date, ref, amount, kind), plus the
 * outcome of the reconciliation match against our books.
 *
 * Match status taxonomy:
 *   unmatched         — initial state, before reconcile pass
 *   matched           — paired cleanly with one of our docs
 *   amount_mismatch   — found by ref, amounts differ
 *   not_in_our_books  — supplier's line, no matching doc on our side
 *   duplicate_match   — multiple of our docs match the ref
 *   manually_resolved — human override (e.g. accepted forex variance)
 *
 * Document kinds (what the supplier's line represents):
 *   invoice    — a bill they raised against us
 *   credit     — a credit note they issued (return, discount, refund)
 *   payment    — they're acknowledging a payment we sent
 *   adjustment — anything else (interest, manual entry, etc.)
 *
 * @see SupplierStatement
 */
class SupplierStatementLine extends Model
{
    use HasFactory;

    public const STATUS_UNMATCHED         = 'unmatched';
    public const STATUS_MATCHED           = 'matched';
    public const STATUS_AMOUNT_MISMATCH   = 'amount_mismatch';
    public const STATUS_NOT_IN_OUR_BOOKS  = 'not_in_our_books';
    public const STATUS_DUPLICATE_MATCH   = 'duplicate_match';
    public const STATUS_MANUALLY_RESOLVED = 'manually_resolved';

    public const KIND_INVOICE    = 'invoice';
    public const KIND_CREDIT     = 'credit';
    public const KIND_PAYMENT    = 'payment';
    public const KIND_ADJUSTMENT = 'adjustment';

    public const ALL_KINDS = [
        self::KIND_INVOICE,
        self::KIND_CREDIT,
        self::KIND_PAYMENT,
        self::KIND_ADJUSTMENT,
    ];

    public const MATCHED_TYPE_VENDOR_INVOICE = 'vendor_invoice';
    public const MATCHED_TYPE_PAYMENT        = 'payment';

    protected $fillable = [
        'supplier_statement_id',
        'line_number',
        'document_date',
        'document_kind',
        'document_reference',
        'amount',
        'description',
        'match_status',
        'matched_type',
        'matched_id',
        'amount_difference',
        'duplicate_candidate_ids',
        'match_notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'document_date'           => 'date',
            'amount'                  => 'decimal:2',
            'amount_difference'       => 'decimal:2',
            'duplicate_candidate_ids' => 'array',
            'resolved_at'             => 'datetime',
        ];
    }

    /* relations */

    public function statement(): BelongsTo
    {
        return $this->belongsTo(SupplierStatement::class, 'supplier_statement_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * Resolve the matched document. The `matched_type` column stores the
     * short string ('vendor_invoice' or 'payment'); we map it to the
     * concrete class here rather than rely on Eloquent's morphMap
     * (avoids needing to register a global morph map for two classes).
     */
    public function matchedDocument()
    {
        if (! $this->matched_id || ! $this->matched_type) {
            return null;
        }
        $class = match ($this->matched_type) {
            self::MATCHED_TYPE_VENDOR_INVOICE => VendorInvoice::class,
            self::MATCHED_TYPE_PAYMENT        => Payment::class,
            default => null,
        };
        if (! $class) return null;
        return $class::find($this->matched_id);
    }

    /* predicates */

    public function isMatched(): bool         { return $this->match_status === self::STATUS_MATCHED; }
    public function isUnmatched(): bool       { return $this->match_status === self::STATUS_UNMATCHED; }
    public function isAmountMismatch(): bool  { return $this->match_status === self::STATUS_AMOUNT_MISMATCH; }
    public function isNotInOurBooks(): bool   { return $this->match_status === self::STATUS_NOT_IN_OUR_BOOKS; }
    public function isDuplicate(): bool       { return $this->match_status === self::STATUS_DUPLICATE_MATCH; }
    public function isManuallyResolved(): bool{ return $this->match_status === self::STATUS_MANUALLY_RESOLVED; }

    public function isResolved(): bool
    {
        return in_array($this->match_status, [
            self::STATUS_MATCHED,
            self::STATUS_MANUALLY_RESOLVED,
        ], true);
    }

    public function isUnresolved(): bool
    {
        return ! $this->isResolved();
    }
}
