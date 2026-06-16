<?php

namespace App\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\FeedSalePaymentStatusChanged;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\Finance\JournalPosting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Posts the cash receipt when a feed sale settles (pending → paid). The feed
 * analogue of PigSalePaymentReceivedSubscriber.
 *
 *   Dr Cash/Bank (default cash account)   sale_total
 *       Cr AR — Feed Sales (1120)         sale_total
 *
 * Only acts on a clean transition TO 'paid' from a non-paid, non-partial
 * state (partial→paid allocation is out of scope, matching the pig path).
 * Idempotency key includes the event id so successive transitions get their
 * own receipt entry.
 */
class FeedSalePaymentReceivedSubscriber extends Subscriber
{
    public function __construct(protected FinanceAccountResolver $accountResolver)
    {
    }

    protected function eventType(): string
    {
        return FeedSalePaymentStatusChanged::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $payload    = $event->payload;
        $fromStatus = (string) ($payload['from_status'] ?? '');
        $toStatus   = (string) ($payload['to_status'] ?? '');

        if ($toStatus !== 'paid' || $fromStatus === 'paid') {
            return null;
        }
        if ($fromStatus === 'partial') {
            Log::info("FeedSalePaymentReceivedSubscriber: event #{$event->id} partial→paid deferred (allocation), skipping");
            return null;
        }

        $saleTotal = (string) ((float) ($payload['sale_total'] ?? 0));
        if (bccomp($saleTotal, '0', 2) <= 0) {
            return null;
        }

        $sale = FeedSale::find($event->source_id);
        if (! $sale) {
            Log::error("FeedSalePaymentReceivedSubscriber: event #{$event->id} missing FeedSale {$event->source_id}");
            return null;
        }

        $cashCode = (string) config('feed_sales.default_cash_account_code', '1030');
        $arCode   = (string) config('feed_sales.ar_account_code', '1120');
        $cashAccount = $this->accountResolver->byCode($cashCode);
        $arAccount   = $this->accountResolver->byCode($arCode);

        $description = sprintf('Payment received for feed sale #%d — %s', $sale->id, $sale->buyer_name ?? 'customer');

        $dimensions = array_filter([
            'counterparty_id' => $sale->counterparty_id,
            'source_type'     => FeedSale::class,
            'source_id'       => $sale->id,
        ], fn ($v) => $v !== null);

        $journal = new JournalDraft(
            journalType: 'cash_receipt',
            lines: [
                JournalLineDraft::debit(
                    accountId:   $cashAccount->id,
                    amount:      $saleTotal,
                    description: $description,
                    dimensions:  $dimensions,
                ),
                JournalLineDraft::credit(
                    accountId:   $arAccount->id,
                    amount:      $saleTotal,
                    description: $description,
                    dimensions:  $dimensions,
                ),
            ],
            reference:    'FeedSale#' . $sale->id,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   FeedSale::class,
            sourceId:     $sale->id,
        );

        return new JournalBatchDraft(
            batchType:       'auto_feed_sale_receipt',
            postingDate:     CarbonImmutable::instance($event->occurred_at),
            journals:        [$journal],
            description:     $description,
            sourceModule:    'sales',
            sourceType:      FeedSale::class,
            sourceId:        $sale->id,
            domainEventId:   $event->id,
            idempotencyKey:  JournalPosting::buildIdempotencyKey(
                $event->event_type,
                FeedSale::class,
                $sale->id . ':e' . $event->id,
                JournalPosting::PURPOSE_CASH_RECEIPT,
            ),
            postingPurpose:  JournalPosting::PURPOSE_CASH_RECEIPT,
            subscriberClass: static::class,
        );
    }
}
