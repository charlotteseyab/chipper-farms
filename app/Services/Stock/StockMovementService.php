<?php

namespace App\Services\Stock;

use App\Models\PurchaseStockIntake;
use App\Models\StockMovement;

class StockMovementService
{
    public function recordPurchasedPigletStockIn(PurchaseStockIntake $intake): StockMovement
    {
        return StockMovement::firstOrCreate(
            [
                'source_type' => StockMovement::SOURCE_PURCHASE_STOCK_INTAKE,
                'source_id' => $intake->id,
            ],
            [
                'organization_id' => $intake->organization_id,
                'movement_type' => StockMovement::TYPE_STOCK_IN,
                'animal_category' => StockMovement::CATEGORY_PIGLET,
                'quantity' => $intake->receivedQuantity(),
                'movement_date' => now(),
                'created_by' => auth()->id(),
                'reference' => $intake->batch_number,
                'notes' => 'Purchased piglet batch added to stock.',
            ]
        );
    }
}
