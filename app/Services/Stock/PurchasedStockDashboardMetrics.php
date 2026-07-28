<?php

namespace App\Services\Stock;

use App\Models\PigManagement;
use App\Models\PurchaseStockIntake;

class PurchasedStockDashboardMetrics
{
    public function summary(): array
    {
        $pendingStatuses = [
            PurchaseStockIntake::STATUS_PENDING_RECEIVE,
            PurchaseStockIntake::STATUS_RECEIVED,
            PurchaseStockIntake::STATUS_QUARANTINED,
            PurchaseStockIntake::STATUS_APPROVED,
        ];

        $addedQuery = PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_ADDED_TO_STOCK);

        return [
            'pending_intakes' => PurchaseStockIntake::whereIn('status', $pendingStatuses)->count(),
            'pending_receive' => PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_PENDING_RECEIVE)->count(),
            'received' => PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_RECEIVED)->count(),
            'quarantined' => PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_QUARANTINED)->count(),
            'approved_waiting_stock' => PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_APPROVED)->count(),
            'added_intakes' => (clone $addedQuery)->count(),
            'added_quantity' => (int) (clone $addedQuery)->sum('quantity_received'),
            'piglet_batches_added' => (clone $addedQuery)
                ->where('animal_category', PurchaseStockIntake::CATEGORY_PIGLET)
                ->count(),
            'individual_intakes_added' => (clone $addedQuery)
                ->whereIn('animal_category', [
                    PurchaseStockIntake::CATEGORY_GROWER,
                    PurchaseStockIntake::CATEGORY_FINISHER,
                    PurchaseStockIntake::CATEGORY_BREEDING_STOCK,
                ])
                ->count(),
            'individual_pigs_created' => PigManagement::whereNotNull('purchase_stock_intake_id')->count(),
            'active_purchased_pigs' => PigManagement::active()
                ->where('acquisition_source', 'purchased')
                ->count(),
            'recent_intakes' => PurchaseStockIntake::where('created_at', '>=', now()->subDays(7))->count(),
            'total_investment' => (float) (clone $addedQuery)->sum('total_purchase_cost')
                + (float) (clone $addedQuery)->sum('transport_cost'),
        ];
    }
}
