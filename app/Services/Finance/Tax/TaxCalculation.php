<?php

namespace App\Services\Finance\Tax;

/**
 * The result of applying a tax profile to an amount (Phase 2).
 *
 * Every value is a decimal STRING. Nothing here is a float, and nothing here
 * should be cast to one — the standing rule for this codebase is that money,
 * tax, unit cost, valuation and rates never touch binary floating point.
 *
 * This object doubles as the transaction snapshot. `toSnapshot()` produces the
 * array stored on an order line, invoice line or credit note so that a posted
 * document can be re-explained years later without consulting the profile
 * tables — which may by then have been superseded by a later effective period.
 * Blueprint §21 requires exactly this, and §29 requires credit notes to reverse
 * the ORIGINAL tax rather than recompute today's.
 */
final class TaxCalculation
{
    /**
     * @param list<array{code:string,label:string,rate_type:string,rate:string,base_mode:string,base:string,amount:string,is_recoverable:bool,gl_account_hint:?string}> $components
     */
    public function __construct(
        public readonly string $taxableBase,
        public readonly string $totalTax,
        public readonly string $grossTotal,
        public readonly array $components,
        public readonly ?int $profileId,
        public readonly string $profileCode,
        public readonly string $treatment,
        public readonly string $priceMode,
        public readonly ?string $effectiveOn,
    ) {
    }

    /**
     * Snapshot for persistence alongside the transaction.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'profile_id'    => $this->profileId,
            'profile_code'  => $this->profileCode,
            'treatment'     => $this->treatment,
            'price_mode'    => $this->priceMode,
            'effective_on'  => $this->effectiveOn,
            'taxable_base'  => $this->taxableBase,
            'total_tax'     => $this->totalTax,
            'gross_total'   => $this->grossTotal,
            'components'    => $this->components,
        ];
    }

    /**
     * Rebuild a calculation from a stored snapshot.
     *
     * This is how a credit note reverses the original tax: it reads what was
     * actually charged rather than asking the calculator what today's profile
     * would charge.
     *
     * @param array<string, mixed> $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self(
            taxableBase: (string) ($snapshot['taxable_base'] ?? '0'),
            totalTax:    (string) ($snapshot['total_tax'] ?? '0'),
            grossTotal:  (string) ($snapshot['gross_total'] ?? '0'),
            components:  $snapshot['components'] ?? [],
            profileId:   isset($snapshot['profile_id']) ? (int) $snapshot['profile_id'] : null,
            profileCode: (string) ($snapshot['profile_code'] ?? ''),
            treatment:   (string) ($snapshot['treatment'] ?? 'no_tax'),
            priceMode:   (string) ($snapshot['price_mode'] ?? 'exclusive'),
            effectiveOn: $snapshot['effective_on'] ?? null,
        );
    }

    /**
     * A proportional slice of this calculation, for a partial return.
     *
     * Each component is scaled by the same proportion and re-rounded, then the
     * total is the SUM OF ROUNDED COMPONENTS rather than a separately rounded
     * total. That keeps a credit note internally consistent: the lines a
     * customer can see add up to the total they are credited.
     */
    public function proportion(string $proportion, int $scale = 2): self
    {
        $components = [];
        $totalTax   = '0';

        foreach ($this->components as $component) {
            $amount = self::round(bcmul($component['amount'], $proportion, 8), $scale);

            $component['base']   = self::round(bcmul($component['base'], $proportion, 8), $scale);
            $component['amount'] = $amount;

            $components[] = $component;
            $totalTax     = bcadd($totalTax, $amount, $scale);
        }

        $base = self::round(bcmul($this->taxableBase, $proportion, 8), $scale);

        return new self(
            taxableBase: $base,
            totalTax:    $totalTax,
            grossTotal:  bcadd($base, $totalTax, $scale),
            components:  $components,
            profileId:   $this->profileId,
            profileCode: $this->profileCode,
            treatment:   $this->treatment,
            priceMode:   $this->priceMode,
            effectiveOn: $this->effectiveOn,
        );
    }

    /**
     * Half-up rounding on a decimal string.
     *
     * PHP's round() takes and returns a float, so it cannot be used here
     * without reintroducing exactly the representation error this class exists
     * to avoid.
     */
    public static function round(string $value, int $scale = 2): string
    {
        $negative = bccomp($value, '0', 12) < 0;
        $absolute = $negative ? bcsub('0', $value, 12) : $value;

        $increment = bcdiv('5', bcpow('10', (string) ($scale + 1), 0), 12);
        $bumped    = bcadd($absolute, $increment, 12);
        $truncated = bcadd($bumped, '0', $scale);

        return $negative ? bcsub('0', $truncated, $scale) : $truncated;
    }
}
