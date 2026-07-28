<?php

namespace App\Services\Stock;

use App\Models\Farrowing;
use App\Models\PigManagement;
use App\Models\PurchaseStockIntake;
use App\Models\StockMovement;
use App\Support\CurrentOrganization;

class StockSummaryService
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    public function forDashboard(?int $weaningAgeDays = null): array
    {
        $weaningAgeDays ??= (int) config('breeding.cycle.birth_to_weaning_days', 45);
        $bornPiglets = $this->bornPiglets($weaningAgeDays);
        $purchasedPiglets = $this->purchasedPiglets();
        $activePigs = $this->activePigs();
        $piglets = $bornPiglets + $purchasedPiglets;

        return [
            'born_piglets' => $bornPiglets,
            'purchased_piglets' => $purchasedPiglets,
            'total_piglets' => $piglets,
            'active_weaned_pigs' => $activePigs,
            'active_pigs' => $activePigs,
            'total_pigs' => $activePigs + $piglets,
            'purchase_intake_count' => $this->purchaseIntakeCount(),
            'quarantine_intake_count' => $this->quarantineIntakeCount(),
        ];
    }

    public function bornPiglets(int $weaningAgeDays): int
    {
        return Farrowing::with('pigletDeaths')
            ->where('farrowing_date', '>=', now()->subDays($weaningAgeDays))
            ->where('status', 'active')
            ->get()
            ->sum(function (Farrowing $farrowing) {
                return max(0, (int) ($farrowing->live_births ?? 0) - (int) $farrowing->pigletDeaths->sum('count'));
            });
    }

    public function purchasedPiglets(): int
    {
        return StockMovement::purchasedPigletQuantity($this->currentOrganization->id());
    }

    public function activePigs(): int
    {
        return PigManagement::active()->count();
    }

    public function purchaseIntakeCount(): int
    {
        return PurchaseStockIntake::whereIn('status', [
            PurchaseStockIntake::STATUS_PENDING_RECEIVE,
            PurchaseStockIntake::STATUS_RECEIVED,
            PurchaseStockIntake::STATUS_QUARANTINED,
            PurchaseStockIntake::STATUS_APPROVED,
        ])
            ->forOrganization($this->currentOrganization->id())
            ->sum('quantity_received');
    }

    public function quarantineIntakeCount(): int
    {
        return PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_QUARANTINED)
            ->forOrganization($this->currentOrganization->id())
            ->sum('quantity_received');
    }
}
