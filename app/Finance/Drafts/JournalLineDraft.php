<?php

namespace App\Finance\Drafts;

/**
 * Immutable value object representing one journal line BEFORE persistence.
 *
 * Subscribers and services build collections of these, hand them to a
 * JournalDraft, and ultimately to PostingEngine::post(). The engine
 * validates and persists.
 *
 * Why a separate type instead of just using the JournalLine model?
 *   - Drafts have no id, no journal_id, no row in the database. Using a
 *     model with null primary key invites bugs.
 *   - Immutability — once a draft is constructed, it cannot be mutated.
 *     Fixes a class of "we modified the line then validated, but persisted
 *     the original" bugs.
 *   - Type safety — the constructor enforces invariants once.
 *   - Tests can construct drafts directly without DB hits.
 *
 * Amount discipline:
 *   Either $debit > 0 with $credit = '0', OR $credit > 0 with $debit = '0'.
 *   Never both, never neither. The constructor enforces this.
 *
 *   Amounts are strings to preserve decimal precision through PHP's loose
 *   type juggling. Always pass as strings: '100.00', not 100.0.
 */
final class JournalLineDraft
{
    public function __construct(
        public readonly int $accountId,
        public readonly string $debit,
        public readonly string $credit,
        public readonly ?string $description = null,
        public readonly ?string $currencyCode = null,
        public readonly ?string $exchangeRate = null,
        public readonly ?string $debitTc = null,
        public readonly ?string $creditTc = null,

        // Dimensions
        public readonly ?int $costCentreId = null,
        public readonly ?int $salesChannelId = null,
        public readonly ?int $counterpartyId = null,
        public readonly ?int $taxCodeId = null,
        public readonly ?int $paymentMethodId = null,
        public readonly ?int $structureId = null,
        public readonly ?int $roomId = null,
        public readonly ?int $groupId = null,

        // Source
        public readonly ?string $sourceType = null,
        public readonly ?int $sourceId = null,
        public readonly ?string $reference = null,
    ) {
        // Normalise: empty/null amount strings become '0'.
        $d = $this->normalise($debit);
        $c = $this->normalise($credit);

        // Re-assign through reflection? No — readonly forbids. Validate as-is.
        if (! $this->isPositive($d) && ! $this->isPositive($c)) {
            throw new \InvalidArgumentException(
                'JournalLineDraft must have either debit > 0 or credit > 0'
            );
        }

        if ($this->isPositive($d) && $this->isPositive($c)) {
            throw new \InvalidArgumentException(
                "JournalLineDraft cannot have both debit ({$d}) and credit ({$c}) positive"
            );
        }

        if ($this->isNegative($d) || $this->isNegative($c)) {
            throw new \InvalidArgumentException(
                "JournalLineDraft amounts must be non-negative (debit={$d}, credit={$c})"
            );
        }
    }

    /**
     * Convenience: build a debit line.
     */
    public static function debit(
        int $accountId,
        string $amount,
        ?string $description = null,
        array $dimensions = [],
    ): self {
        return new self(
            accountId: $accountId,
            debit: $amount,
            credit: '0',
            description: $description,
            costCentreId: $dimensions['cost_centre_id'] ?? null,
            salesChannelId: $dimensions['sales_channel_id'] ?? null,
            counterpartyId: $dimensions['counterparty_id'] ?? null,
            taxCodeId: $dimensions['tax_code_id'] ?? null,
            paymentMethodId: $dimensions['payment_method_id'] ?? null,
            structureId: $dimensions['structure_id'] ?? null,
            roomId: $dimensions['room_id'] ?? null,
            groupId: $dimensions['group_id'] ?? null,
            sourceType: $dimensions['source_type'] ?? null,
            sourceId: $dimensions['source_id'] ?? null,
            reference: $dimensions['reference'] ?? null,
        );
    }

    /**
     * Convenience: build a credit line.
     */
    public static function credit(
        int $accountId,
        string $amount,
        ?string $description = null,
        array $dimensions = [],
    ): self {
        return new self(
            accountId: $accountId,
            debit: '0',
            credit: $amount,
            description: $description,
            costCentreId: $dimensions['cost_centre_id'] ?? null,
            salesChannelId: $dimensions['sales_channel_id'] ?? null,
            counterpartyId: $dimensions['counterparty_id'] ?? null,
            taxCodeId: $dimensions['tax_code_id'] ?? null,
            paymentMethodId: $dimensions['payment_method_id'] ?? null,
            structureId: $dimensions['structure_id'] ?? null,
            roomId: $dimensions['room_id'] ?? null,
            groupId: $dimensions['group_id'] ?? null,
            sourceType: $dimensions['source_type'] ?? null,
            sourceId: $dimensions['source_id'] ?? null,
            reference: $dimensions['reference'] ?? null,
        );
    }

    public function isDebit(): bool
    {
        return $this->isPositive($this->normalise($this->debit));
    }

    public function isCredit(): bool
    {
        return $this->isPositive($this->normalise($this->credit));
    }

    /**
     * The signed amount: positive for debit, negative for credit.
     * Used by the journal balance check (sum should be 0 for balanced).
     */
    public function signedAmount(): string
    {
        return $this->isDebit()
            ? $this->normalise($this->debit)
            : '-' . $this->normalise($this->credit);
    }

    /** The line's amount, on whichever side it sits. */
    public function amount(): string
    {
        return $this->isDebit() ? $this->normalise($this->debit) : $this->normalise($this->credit);
    }

    /** This line with its amount replaced, on the same side, every other field kept. */
    public function withAmount(string $amount): self
    {
        $isDebit = $this->isDebit();

        return new self(
            accountId: $this->accountId,
            debit: $isDebit ? $amount : '0',
            credit: $isDebit ? '0' : $amount,
            description: $this->description,
            currencyCode: $this->currencyCode,
            exchangeRate: $this->exchangeRate,
            debitTc: $this->debitTc,
            creditTc: $this->creditTc,
            costCentreId: $this->costCentreId,
            salesChannelId: $this->salesChannelId,
            counterpartyId: $this->counterpartyId,
            taxCodeId: $this->taxCodeId,
            paymentMethodId: $this->paymentMethodId,
            structureId: $this->structureId,
            roomId: $this->roomId,
            groupId: $this->groupId,
            sourceType: $this->sourceType,
            sourceId: $this->sourceId,
            reference: $this->reference,
        );
    }

    /**
     * Render as the array shape expected by JournalLine::create().
     * Called by PostingEngine when persisting.
     */
    public function toPersistedArray(int $journalId, int $lineNumber, ?int $organizationId, string $defaultCurrency, string $defaultRate): array
    {
        $debit  = $this->normalise($this->debit);
        $credit = $this->normalise($this->credit);

        return [
            'organization_id' => $organizationId,
            'journal_id'      => $journalId,
            'line_number'     => $lineNumber,
            'account_id'      => $this->accountId,
            'debit'           => $debit,
            'credit'          => $credit,
            'debit_tc'        => $this->debitTc  ?? $debit,
            'credit_tc'       => $this->creditTc ?? $credit,
            'currency_code'   => $this->currencyCode ?? $defaultCurrency,
            'exchange_rate'   => $this->exchangeRate ?? $defaultRate,
            'description'     => $this->description,
            'cost_centre_id'  => $this->costCentreId,
            'sales_channel_id'=> $this->salesChannelId,
            'counterparty_id' => $this->counterpartyId,
            'tax_code_id'     => $this->taxCodeId,
            'payment_method_id' => $this->paymentMethodId,
            'structure_id'    => $this->structureId,
            'room_id'         => $this->roomId,
            'group_id'        => $this->groupId,
            'source_type'     => $this->sourceType,
            'source_id'       => $this->sourceId,
            'reference'       => $this->reference,
        ];
    }

    private function normalise(?string $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        // Strip leading zeros but keep '0' for zero. Trim whitespace.
        return trim($value);
    }

    private function isPositive(string $value): bool
    {
        return $this->decComp($value, '0', 8) === 1;
    }

    private function isNegative(string $value): bool
    {
        return $this->decComp($value, '0', 8) === -1;
    }

    private function decComp(string $left, string $right, int $scale = 8): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, $scale);
        }

        $a = round((float) $left, $scale);
        $b = round((float) $right, $scale);

        return $a <=> $b;
    }
}
