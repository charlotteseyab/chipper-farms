<?php

namespace App\Observers;

use App\Models\PigSale;
use App\Services\Audit\AuditTrailService;

class PigSaleObserver
{
    private const AUDITED_FIELDS = [
        'payment_status',
        'payment_status_updated_at',
        'payment_status_updated_by_user_id',
        'notes',
        'is_posted',
        'posted_at',
        'invoice_id',
        'cogs_amount',
        'cogs_journal_batch_id',
        'cogs_posting_status',
        'cogs_posting_error',
    ];

    public function __construct(protected AuditTrailService $audit)
    {
    }

    public function updated(PigSale $sale): void
    {
        $changes = collect($sale->getChanges())->only(self::AUDITED_FIELDS)->all();

        if ($changes === []) {
            return;
        }

        $before = [];
        foreach (array_keys($changes) as $field) {
            $before[$field] = $sale->getRawOriginal($field);
        }

        $sale->loadMissing('pig');

        $this->audit->logModelChanges(
            $sale,
            'sales',
            array_key_exists('payment_status', $changes) ? 'payment_status_updated' : 'updated',
            $before,
            $changes,
            [
                'organization_id' => $sale->organization_id,
                'record_label' => $sale->pig?->code ?: ($sale->pig?->ear_tag_number ?: 'Sale #'.$sale->id),
                'description' => array_key_exists('payment_status', $changes)
                    ? 'Sale payment status updated.'
                    : 'Sale transaction updated.',
            ]
        );
    }
}
