<?php

namespace App\Finance\Subscribers\Sales;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\CounterpartyResolver;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Resolvers\MissingAccountException;
use App\Finance\Subscribers\Subscriber;
use App\Domain\Events\Sales\FeedSaleRecorded;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Posts the revenue side of a feed sale, mirroring PigSaleRecordedSubscriber.
 *
 *   payment_status = 'paid' (cash sale):
 *     Dr cash/bank (via payment-method hint)   sale_total
 *         Cr Feed Revenue (4110 direct / 4120 storefront)  sale_total
 *
 *   payment_status = 'pending' | 'partial' (credit sale):
 *     Dr AR — Feed Sales (1120)                 sale_total
 *         Cr Feed Revenue                        sale_total
 *
 * The revenue account is chosen by the sale's channel. COGS is a separate
 * concern (FeedSaleCogsSubscriber).
 */
class FeedSaleRevenueSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected CounterpartyResolver $counterpartyResolver,
    ) {
    }

    protected function eventType(): string
    {
        return FeedSaleRecorded::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $payload = $event->payload;

        foreach (['sale_total', 'payment_status', 'channel'] as $field) {
            if (! array_key_exists($field, $payload)) {
                Log::warning("FeedSaleRevenueSubscriber: event #{$event->id} missing '{$field}', skipping");
                return null;
            }
        }

        $saleTotal = (string) ((float) $payload['sale_total']);
        if (bccomp($saleTotal, '0', 2) <= 0) {
            Log::info("FeedSaleRevenueSubscriber: event #{$event->id} non-positive sale_total, skipping");
            return null;
        }

        $sale = FeedSale::find($event->source_id);
        if (! $sale) {
            Log::error("FeedSaleRevenueSubscriber: event #{$event->id} references missing FeedSale {$event->source_id}");
            return null;
        }

        if ($sale->revenue_journal_batch_id !== null && $sale->revenue_posting_status === FeedSale::POST_POSTED) {
            return null; // already posted
        }

        $channel = (string) $payload['channel'];
        $revenueCode = config("feed_sales.revenue_accounts.{$channel}")
            ?? config('feed_sales.revenue_accounts.direct', '4110');
        $revenueAccount = $this->accountResolver->byCode($revenueCode);

        $paymentStatus = (string) $payload['payment_status'];
        $offsetAccount = $paymentStatus === FeedSale::PAYMENT_PAID
            ? $this->resolveCashAccount($sale)
            : $this->accountResolver->byCode((string) config('feed_sales.ar_account_code', '1120'));

        $counterparty = $this->counterpartyResolver->forPigSale($sale->counterparty_id);

        $description = sprintf(
            'Feed sale: %s kg @ %s/kg — %s',
            number_format((float) $sale->quantity_kg, 2),
            number_format((float) $sale->unit_price, 2),
            $sale->buyer_name ?: $counterparty->name,
        );

        $dims = [
            'counterparty_id' => $counterparty->id,
            'source_type'     => FeedSale::class,
            'source_id'       => $sale->id,
            'reference'       => 'Feed Sale #' . $sale->id,
        ];

        // IMP-091 — output tax on a feed sale.
        //
        // Feed revenue posts straight to the ledger rather than through an
        // invoice, so unlike the pig path there is no InvoicePostingService to
        // credit the tax account. Without this the buyer would be charged tax
        // at checkout that never reached the books: worse than charging none,
        // because the farm would hold money it had not recorded as owed.
        //
        // The split: the customer owes (or paid) the GROSS; revenue is the NET;
        // the difference is a liability to the revenue authority, not income.
        $tax = $sale->tax_amount !== null
            ? bcadd((string) $sale->tax_amount, '0', 2)
            : '0.00';

        $hasTax  = bccomp($tax, '0', 2) > 0;
        $gross   = bcadd($saleTotal, $tax, 2);
        $netSale = $saleTotal;

        $lines = [
            JournalLineDraft::debit(
                accountId: $offsetAccount->id,
                amount: $hasTax ? $gross : $saleTotal,
                description: $description,
                dimensions: $dims,
            ),
            JournalLineDraft::credit(
                accountId: $revenueAccount->id,
                amount: $netSale,
                description: $description,
                dimensions: $dims,
            ),
        ];

        if ($hasTax) {
            $lines[] = JournalLineDraft::credit(
                // Same account the invoice path credits, so a feed sale and a
                // pig sale under one tax regime land in one place.
                accountId: $this->accountResolver->byHint('VAT_CONTROL')->id,
                amount: $tax,
                description: $description . ' — VAT',
                dimensions: $dims,
            );
        }

        $journal = new JournalDraft(
            journalType: 'sales_revenue',
            lines: $lines,
            reference: 'Feed Sale #' . $sale->id,
            description: $description,
            currencyCode: 'GHS',
        );

        return new JournalBatchDraft(
            batchType:      'auto_feed_sale',
            postingDate:    CarbonImmutable::instance($event->occurred_at),
            journals:       [$journal],
            description:    $description,
            sourceModule:   'sales',
            sourceType:     FeedSale::class,
            sourceId:       $sale->id,
            domainEventId:  $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type, FeedSale::class, $sale->id, JournalPosting::PURPOSE_REVENUE,
            ),
            postingPurpose:  JournalPosting::PURPOSE_REVENUE,
            subscriberClass: static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        if (! $event->source_id) {
            return;
        }
        FeedSale::where('id', $event->source_id)->update([
            'revenue_journal_batch_id' => $batch->id,
            'revenue_posting_status'   => FeedSale::POST_POSTED,
            'revenue_posting_error'    => null,
        ]);
    }

    /**
     * Resolve the cash/bank account from the sale's payment method hint, with
     * sensible legacy-string fallbacks, then suspense receipts.
     */
    protected function resolveCashAccount(FeedSale $sale)
    {
        $raw = strtolower((string) ($sale->payment_method ?? ''));

        // If payment_method matches a PaymentMethod code, use its hint.
        if ($raw !== '') {
            $method = PaymentMethod::query()->whereRaw('LOWER(code) = ?', [$raw])->first();
            if ($method && $method->gl_account_hint) {
                try {
                    return $this->accountResolver->byHint($method->gl_account_hint);
                } catch (MissingAccountException $e) {
                    Log::warning("FeedSaleRevenueSubscriber: hint '{$method->gl_account_hint}' unresolved");
                }
            }

            $legacyMap = [
                'cash' => 'CASH_ON_HAND', 'bank' => 'BANK_MAIN',
                'mobile_money' => 'MOMO_MTN', 'momo' => 'MOMO_MTN',
            ];
            if (isset($legacyMap[$raw])) {
                try {
                    return $this->accountResolver->byHint($legacyMap[$raw]);
                } catch (MissingAccountException $e) {
                    // fall through
                }
            }
        }

        // Fallback to the configured default cash account.
        return $this->accountResolver->byCode((string) config('feed_sales.default_cash_account_code', '1030'));
    }
}
