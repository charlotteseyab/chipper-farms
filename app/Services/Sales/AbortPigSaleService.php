<?php

namespace App\Services\Sales;

use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\Audit\AuditTrailService;

class AbortPigSaleService
{
    public function __construct(
        protected RecordSalesReturnService $returns,
        protected AuditTrailService $audit,
    ) {
    }

    public function execute(PigSale $sale, User $actor, string $reason): SalesReturn
    {
        $existing = $sale->salesReturns()
            ->where('status', '!=', SalesReturn::STATUS_FAILED)
            ->latest('id')
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('This sale already has an active or completed reversal.');
        }

        $return = $this->returns->record(
            sale: $sale,
            actor: $actor,
            quantity: '1',
            reason: $reason,
            restock: true,
        );

        $sale->loadMissing('pig');
        $this->audit->log('sales', 'abort_initiated', [
            'user' => $actor,
            'organization_id' => $sale->organization_id,
            'auditable' => $sale,
            'record_label' => $sale->pig?->code ?: ($sale->pig?->ear_tag_number ?: 'Sale #'.$sale->id),
            'description' => 'Sale transaction reversal initiated.',
            'old_values' => ['transaction_state' => 'sold'],
            'new_values' => [
                'transaction_state' => 'reversal_'.$return->status,
                'return_number' => $return->return_number,
                'reason' => $return->reason,
                'restock' => true,
            ],
        ]);

        return $return;
    }
}
