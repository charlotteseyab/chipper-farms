<?php

namespace App\Services\Finance\BiologicalAssets;

use App\Models\Finance\BiologicalAssetStageBalance;
use App\Services\Finance\BiologicalAssets\Concerns\HandlesBioAssetDecimals;
use App\Support\CurrentOrganization;
use Illuminate\Support\Collection;

/**
 * IAS 41 fair-value disclosure report (cost model + fair-value note).
 *
 * Under the cost model the balance sheet carries biological assets at cost.
 * IAS 41 still requires fair-value disclosure where it can be measured
 * reliably. This report produces, per stage:
 *
 *   - carrying value (cost)               from the stage subledger
 *   - fair value less costs to sell       weight_kg × price_per_kg × (1 - costs_to_sell_pct)
 *   - the difference (unrecognised gain/loss vs cost)
 *
 * It posts NOTHING. It is a read-only disclosure aid. The price table lives
 * in config/biological_assets.php and must be reviewed against the local
 * market by the operator.
 *
 * Because fair value depends on a market price that the farm may not be able
 * to measure reliably, each stage line carries a `reliable` flag (false when
 * no price is configured) so the note can state where fair value could not
 * be reliably measured — itself an IAS 41 disclosure requirement.
 */
class FairValueDisclosureService
{
    use HandlesBioAssetDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected BiologicalAssetStageResolver $resolver,
    ) {
    }

    /**
     * @return array{
     *   lines: array<int, array<string, mixed>>,
     *   totals: array{carrying_value:string, fair_value:string, difference:string},
     *   meta: array{currency:string, costs_to_sell_pct:string, organization_id:int|null, has_unreliable:bool}
     * }
     */
    public function generate(): array
    {
        $organizationId = $this->currentOrganization->id();
        $config = (array) config('biological_assets.fair_value', []);
        $currency       = (string) ($config['currency'] ?? 'GHS');
        $costsToSellPct = (string) ($config['costs_to_sell_pct'] ?? '0');
        $priceTable     = (array) ($config['price_per_kg'] ?? []);

        // Aggregate stage subledger by stage across all groups.
        /** @var Collection<int, BiologicalAssetStageBalance> $balances */
        $balances = BiologicalAssetStageBalance::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->get();

        $byStage = $balances->groupBy('stage_key');

        $lines = [];
        $totCarry = '0';
        $totFair  = '0';
        $hasUnreliable = false;

        foreach ($this->resolver->allStageKeys() as $stageKey) {
            $rows = $byStage->get($stageKey, collect());

            $carry  = $rows->reduce(fn (string $c, $r) => $this->decAdd($c, (string) $r->carrying_value), '0');
            $weight = $rows->reduce(fn (string $c, $r) => $this->decAdd($c, (string) $r->weight_kg), '0');
            $count  = $rows->reduce(fn (int $c, $r) => $c + (int) $r->pig_count, 0);

            // Skip stages with no balance at all.
            if ($this->decComp($carry, '0') === 0 && $this->decComp($weight, '0') === 0 && $count === 0) {
                continue;
            }

            $price = isset($priceTable[$stageKey]) ? (string) $priceTable[$stageKey] : null;
            $reliable = $price !== null && $this->decComp($price, '0') > 0;

            if (! $reliable) {
                $hasUnreliable = true;
            }

            // fair value less costs to sell = weight × price × (1 - pct)
            $grossFair = $reliable ? bcmul($this->norm($weight, 4), $price, 4) : '0';
            $netFair   = $reliable
                ? bcmul($grossFair, bcsub('1', $this->norm($costsToSellPct, 4), 4), 4)
                : '0';

            $difference = $this->decSub($netFair, $carry);

            $meta = $this->resolver->stageMeta($stageKey);

            $lines[] = [
                'stage_key'      => $stageKey,
                'label'          => $meta['label'] ?? $stageKey,
                'account_code'   => $meta['account_code'] ?? null,
                'classification' => $meta['classification'] ?? null,
                'pig_count'      => $count,
                'weight_kg'      => $this->norm($weight, 4),
                'carrying_value' => $this->norm($carry, 4),
                'price_per_kg'   => $price,
                'reliable'       => $reliable,
                'fair_value'     => $this->norm($netFair, 4),
                'difference'     => $difference,
            ];

            $totCarry = $this->decAdd($totCarry, $carry);
            $totFair  = $this->decAdd($totFair, $netFair);
        }

        return [
            'lines'  => $lines,
            'totals' => [
                'carrying_value' => $totCarry,
                'fair_value'     => $totFair,
                'difference'     => $this->decSub($totFair, $totCarry),
            ],
            'meta' => [
                'currency'          => $currency,
                'costs_to_sell_pct' => $this->norm($costsToSellPct, 4),
                'organization_id'   => $organizationId,
                'has_unreliable'    => $hasUnreliable,
            ],
        ];
    }
}
