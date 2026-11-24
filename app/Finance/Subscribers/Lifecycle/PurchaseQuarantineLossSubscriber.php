<?php

namespace App\Finance\Subscribers\Lifecycle;

use App\Domain\Events\Lifecycle\PurchaseQuarantineLoss;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\JournalPosting;
use App\Models\PurchaseStockIntake;
use Carbon\CarbonImmutable;

/**
 * Dr Mortality Loss / Cr Goods Received Not Invoiced, for animals bought
 * through an intake that died or were put down in quarantine: the supplier
 * is still owed for them, and they never became stock.
 */
class PurchaseQuarantineLossSubscriber extends Subscriber
{
    public function __construct(protected FinanceAccountResolver $accountResolver)
    {
    }

    protected function eventType(): string
    {
        return PurchaseQuarantineLoss::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $amount = (string) round((float) ($event->payload['amount'] ?? 0), 4);
        if (bccomp($amount, '0', 4) <= 0) {
            return null;
        }

        $batch = (string) ($event->payload['batch_number'] ?? 'intake#' . $event->source_id);
        $quantity = (int) ($event->payload['quantity'] ?? 0);
        $description = "Quarantine loss: {$quantity} animal(s) from {$batch}";
        $dimensions = ['source_type' => PurchaseStockIntake::class, 'source_id' => (int) $event->source_id, 'reference' => $batch];

        $journal = new JournalDraft(
            journalType: 'purchase_quarantine_loss',
            lines: [
                JournalLineDraft::debit(
                    accountId: $this->accountResolver->byCode((string) config('biological_assets.quarantine_loss_account_code', '5320'))->id,
                    amount: $amount,
                    description: $description,
                    dimensions: $dimensions,
                ),
                JournalLineDraft::credit(
                    accountId: $this->accountResolver->byCode((string) config('biological_assets.purchase_offset_account_code', '2050'))->id,
                    amount: $amount,
                    description: 'Purchased stock lost in quarantine, supplier bill to follow',
                    dimensions: $dimensions,
                ),
            ],
            reference: $batch,
            description: $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType: PurchaseStockIntake::class,
            sourceId: (int) $event->source_id,
        );

        return new JournalBatchDraft(
            batchType: 'auto_purchase_quarantine_loss',
            postingDate: CarbonImmutable::parse($event->occurred_at),
            journals: [$journal],
            description: $description,
            sourceModule: 'purchase_stock_intake',
            sourceType: PurchaseStockIntake::class,
            sourceId: (int) $event->source_id,
            domainEventId: $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type,
                PurchaseStockIntake::class,
                (int) $event->source_id,
                'quarantine_loss',
            ),
            postingPurpose: 'quarantine_loss',
            subscriberClass: static::class,
        );
    }
}
