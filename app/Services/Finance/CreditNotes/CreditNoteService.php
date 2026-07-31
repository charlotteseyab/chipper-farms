<?php

namespace App\Services\Finance\CreditNotes;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\FeedSale;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreditNoteService
{
    public function __construct(
        protected NumberingService $numbering,
        protected FinanceAccountResolver $accounts,
        protected PostingEngine $posting,
    ) {
    }

    public function ensureForSalesReturn(
        SalesReturn $return,
        \DateTimeInterface $accountingDate,
        ?User $actor = null,
    ): CreditNote {
        if ($return->credit_note_id) {
            return CreditNote::with('lines')->findOrFail($return->credit_note_id);
        }

        return DB::transaction(function () use ($return, $accountingDate, $actor) {
            $locked = SalesReturn::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->credit_note_id) {
                return CreditNote::with('lines')->findOrFail($locked->credit_note_id);
            }

            $source = $this->sourceFor($locked);
            $this->assertOriginalRevenuePosted($source);

            $amount = $this->returnAmount($locked, $source);
            $quantitySold = $this->quantitySold($source);
            $invoice = $this->invoiceFor($source);
            $revenueAccount = $this->revenueAccountFor($source);
            $offsetAccount = $this->offsetAccountFor($source);
            $number = $this->numbering->next(
                sequenceType: 'credit_note',
                prefix: 'CN',
                year: (int) CarbonImmutable::instance($accountingDate)->format('Y'),
                organizationId: (int) $locked->organization_id,
            );

            $note = CreditNote::create([
                'organization_id' => $locked->organization_id,
                'credit_note_number' => $number,
                'original_invoice_id' => $invoice?->id,
                'original_order_id' => $locked->storefront_order_id,
                'sales_return_id' => $locked->id,
                'customer_id' => $this->customerIdFor($source),
                'currency_code' => 'GHS',
                'subtotal' => $amount,
                'tax_total' => '0.00',
                'total' => $amount,
                'issue_date' => CarbonImmutable::instance($accountingDate)->toDateString(),
                'accounting_date' => CarbonImmutable::instance($accountingDate)->toDateString(),
                'reason' => $locked->reason,
                'status' => CreditNote::STATUS_APPROVED,
                'approved_by_user_id' => $actor?->id ?? $locked->returned_by_user_id,
                'approved_at' => now(),
                'idempotency_key' => 'sales-return-credit-note-' . $locked->id,
                'offset_account_id' => $offsetAccount->id,
                'created_by_user_id' => $actor?->id ?? $locked->returned_by_user_id,
            ]);

            $note->lines()->create([
                'organization_id' => $locked->organization_id,
                'original_invoice_line_id' => $invoice?->lines()->value('id'),
                'sales_return_id' => $locked->id,
                'returnable_type' => $locked->returnable_type,
                'returnable_id' => $locked->returnable_id,
                'description' => 'Sales return ' . $locked->return_number,
                'quantity' => $locked->quantity,
                'unit_price' => $this->unitPriceFor($locked, $amount),
                'tax_amount' => '0.00',
                'line_total' => $amount,
                'revenue_account_id' => $revenueAccount->id,
                'quantity_sold' => $quantitySold,
                'quantity_previously_credited' => $this->previouslyCreditedQuantity($locked),
            ]);

            $locked->forceFill([
                'credit_note_id' => $note->id,
                'credit_note_status' => $note->status,
                'revenue_reversed_amount' => $amount,
            ])->save();

            return $note->fresh('lines');
        });
    }

    public function buildPostingDraft(CreditNote $note, ?int $domainEventId = null): JournalBatchDraft
    {
        $lines = [];
        foreach ($note->lines as $line) {
            $description = "Credit note {$note->credit_note_number} revenue adjustment";
            $lines[] = JournalLineDraft::debit(
                (int) $line->revenue_account_id,
                (string) $line->line_total,
                $description,
                $this->dimensions($note),
            );
        }

        $lines[] = JournalLineDraft::credit(
            (int) $note->offset_account_id,
            (string) $note->total,
            "Credit note {$note->credit_note_number} customer credit",
            $this->dimensions($note),
        );

        return new JournalBatchDraft(
            batchType: 'auto_credit_note',
            postingDate: CarbonImmutable::parse((string) $note->accounting_date),
            journals: [
                new JournalDraft(
                    journalType: 'credit_note',
                    lines: $lines,
                    reference: $note->credit_note_number,
                    description: "Credit note {$note->credit_note_number}",
                    currencyCode: $note->currency_code,
                ),
            ],
            description: "Credit note {$note->credit_note_number}",
            sourceModule: 'finance',
            sourceType: CreditNote::class,
            sourceId: (int) $note->id,
            domainEventId: $domainEventId,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                'CreditNotePosted',
                CreditNote::class,
                (int) $note->id,
                JournalPosting::PURPOSE_CREDIT_NOTE,
            ),
            postingPurpose: JournalPosting::PURPOSE_CREDIT_NOTE,
            subscriberClass: static::class,
        );
    }

    public function markPosted(CreditNote $note, JournalBatch $batch, ?User $actor = null): CreditNote
    {
        return DB::transaction(function () use ($note, $batch, $actor) {
            $note->forceFill([
                'status' => CreditNote::STATUS_POSTED,
                'journal_batch_id' => $batch->id,
                'posted_by_user_id' => $actor?->id,
                'posted_at' => now(),
            ])->save();

            if ($note->original_invoice_id) {
                $invoice = Invoice::find($note->original_invoice_id);
                if ($invoice) {
                    $balance = bcsub((string) $invoice->balance_due, (string) $note->total, 2);
                    if (bccomp($balance, '0', 2) < 0) {
                        $balance = '0.00';
                    }

                    $updates = ['balance_due' => $balance];
                    if (bccomp((string) $note->total, (string) $invoice->total, 2) >= 0) {
                        $updates['status'] = Invoice::STATUS_REVERSED;
                        $updates['reversed_by_journal_batch_id'] = $batch->id;
                    }

                    $invoice->forceFill($updates)->save();
                }
            }

            if ($note->sales_return_id) {
                SalesReturn::whereKey($note->sales_return_id)->update([
                    'credit_note_id' => $note->id,
                    'credit_note_status' => CreditNote::STATUS_POSTED,
                    'revenue_reversal_batch_id' => $batch->id,
                ]);
            }

            return $note->fresh('lines');
        });
    }

    public function post(CreditNote $note, ?User $actor = null): CreditNote
    {
        if ($note->journal_batch_id) {
            return $note->fresh('lines');
        }

        $batch = $this->posting->post($this->buildPostingDraft($note), $actor);

        return $this->markPosted($note, $batch, $actor);
    }

    protected function sourceFor(SalesReturn $return): FeedSale|PigSale
    {
        $source = $return->returnable_type::find($return->returnable_id);

        if (! $source instanceof FeedSale && ! $source instanceof PigSale) {
            throw new \RuntimeException('Credit note source sale is missing.');
        }

        return $source;
    }

    protected function assertOriginalRevenuePosted(FeedSale|PigSale $source): void
    {
        if ($source instanceof FeedSale) {
            if (
                $source->revenue_journal_batch_id !== null
                && $source->revenue_posting_status === FeedSale::POST_POSTED
            ) {
                return;
            }

            throw new \RuntimeException('original_revenue_not_posted');
        }

        if ((bool) $source->is_posted && ! empty($source->getAttribute('journal_batch_id'))) {
            return;
        }

        throw new \RuntimeException('original_revenue_not_posted');
    }

    protected function returnAmount(SalesReturn $return, FeedSale|PigSale $source): string
    {
        $base = $source instanceof FeedSale ? (string) $source->sale_total : (string) $source->sale_price;

        return bcmul($base, (string) $return->proportion, 2);
    }

    protected function quantitySold(FeedSale|PigSale $source): string
    {
        return $source instanceof FeedSale ? (string) $source->quantity_kg : '1.0000';
    }

    protected function invoiceFor(FeedSale|PigSale $source): ?Invoice
    {
        return $source instanceof PigSale && $source->invoice_id ? Invoice::find($source->invoice_id) : null;
    }

    protected function customerIdFor(FeedSale|PigSale $source): ?int
    {
        return $source instanceof FeedSale ? $source->counterparty_id : $source->customer_id;
    }

    protected function revenueAccountFor(FeedSale|PigSale $source)
    {
        if ($source instanceof FeedSale) {
            $channel = (string) ($source->channel ?? 'direct');
            $code = config("feed_sales.revenue_accounts.{$channel}") ?? config('feed_sales.revenue_accounts.direct', '4110');

            return $this->accounts->byCode((string) $code);
        }

        return $this->accounts->byCode((string) config('pig_sales.revenue_account_code', '4110'));
    }

    protected function offsetAccountFor(FeedSale|PigSale $source)
    {
        if ($source instanceof FeedSale) {
            $code = $source->payment_status === FeedSale::PAYMENT_PAID
                ? config('feed_sales.default_cash_account_code', '1030')
                : config('feed_sales.ar_account_code', '1120');

            return $this->accounts->byCode((string) $code);
        }

        $code = $source->payment_status === 'paid'
            ? config('pig_sales.cash_account_code', '1030')
            : config('pig_sales.ar_account_code', '1120');

        return $this->accounts->byCode((string) $code);
    }

    protected function unitPriceFor(SalesReturn $return, string $amount): string
    {
        if (bccomp((string) $return->quantity, '0', 4) <= 0) {
            return $amount;
        }

        return bcdiv($amount, (string) $return->quantity, 4);
    }

    protected function previouslyCreditedQuantity(SalesReturn $return): string
    {
        $sum = SalesReturn::query()
            ->where('organization_id', $return->organization_id)
            ->where('returnable_type', $return->returnable_type)
            ->where('returnable_id', $return->returnable_id)
            ->where('id', '<', $return->id)
            ->sum('quantity');

        return (string) $sum;
    }

    protected function dimensions(CreditNote $note): array
    {
        return [
            'counterparty_id' => $note->customer_id,
            'source_type' => CreditNote::class,
            'source_id' => $note->id,
            'reference' => $note->credit_note_number,
        ];
    }
}
