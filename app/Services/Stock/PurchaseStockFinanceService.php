<?php

namespace App\Services\Stock;

use App\Models\PurchaseStockIntake;
use Illuminate\Support\Facades\Log;

class PurchaseStockFinanceService
{
    public function recordAcquisition(PurchaseStockIntake $intake): ?int
    {
        // Finance posting for purchased stock batches is intentionally centralized
        // here. Individual pigs still emit PigAcquired events; piglet batches need
        // a batch-level journal implementation before this can create real entries.
        if ($intake->acquisition_journal_batch_id) {
            $intake->forceFill([
                'finance_posting_status' => PurchaseStockIntake::FINANCE_POSTED,
                'finance_posting_message' => 'Finance journal already linked.',
            ])->save();

            return (int) $intake->acquisition_journal_batch_id;
        }

        $intake->forceFill([
            'finance_posting_status' => PurchaseStockIntake::FINANCE_NOT_CONFIGURED,
            'finance_posting_message' => 'Finance journal posting for purchased stock intake is pending implementation.',
        ])->save();

        Log::notice('Purchased stock acquisition finance posting is pending implementation.', [
            'purchase_stock_intake_id' => $intake->id,
            'batch_number' => $intake->batch_number,
            'animal_category' => $intake->animal_category,
            'total_landed_cost' => $intake->totalLandedCost(),
        ]);

        return null;
    }
}
