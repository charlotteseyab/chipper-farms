<?php

namespace App\Storefront\Services;

use App\Models\FeedSale;
use App\Models\Finance\JournalBatch;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Models\User;
use App\Services\Finance\JournalReversalService;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\Exceptions\InvalidOrderTransitionException;
use App\Storefront\Services\Exceptions\PaymentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refunds a storefront order.
 *
 * Scope boundary (deliberate — see README Phase 6):
 *   - Reverses the CUSTOMER-FACING financial legs correctly via the audited
 *     JournalReversalService: the revenue batch (which, for a cash sale, also
 *     returns the cash in one balanced reversal) and any settled cash-receipt
 *     batch. These have no inventory-subledger coupling, so the reversal is
 *     drift-free.
 *   - Restores storefront OPERATIONAL availability (product listing quantity /
 *     republish) so stock is sellable again.
 *   - Does NOT auto-reverse COGS / inventory / biological-asset legs or
 *     un-delete a sold pig: there is no drift-free primitive for a sales
 *     return, so these are explicitly HANDED TO FINANCE via a recorded
 *     finance_reversal_note rather than risk GL↔subledger drift.
 *
 * If the order was never posted to finance (finance_posting_status = pending),
 * the refund is clean — there is simply nothing to reverse.
 *
 * Authorisation (refund_storefront_orders, treasurer-grade) is enforced at the
 * controller; this service performs the reversal.
 */
class StorefrontOrderRefundService
{
    public function __construct(
        protected JournalReversalService $reversalService,
        protected ListingService $listingService,
        protected PaystackClient $paystack,
    ) {
    }

    /**
     * Refund an order.
     *
     * EXECUTION ORDER (CF-P0-007, CF-P0-008)
     * --------------------------------------
     * This method used to set status = 'refunded' and payment_status =
     * 'refunded' unconditionally, without ever contacting the gateway. A
     * customer could be shown "Refunded" while the money sat with the merchant,
     * and nothing in the system distinguished a completed refund from one that
     * had never been attempted.
     *
     * The sequence is now:
     *
     *   1. Validate that the order may be refunded at all.
     *   2. If money was taken through the gateway, SUBMIT the refund to the
     *      provider — outside any database transaction. Holding a transaction
     *      open across an HTTP call means an unresponsive provider pins database
     *      locks for the duration of the timeout.
     *   3. Only once the provider has ACCEPTED it, open a transaction and
     *      reverse the internal effects.
     *   4. Reach 'refunded' only when the provider reports the money as settled.
     *      Paystack settles asynchronously, so the normal outcome of a
     *      successful submission is payment_status = 'refund_pending', not
     *      'refunded'.
     *
     * If the provider REJECTS the submission the order does not move at all: no
     * internal reversal, no refunded status, and a recorded failure reason. That
     * is the property the finding is really about — the customer-visible state
     * must never run ahead of the money.
     *
     * Orders with nothing to reclaim from a gateway (cash on delivery, or a
     * never-paid order) skip step 2 and settle immediately, because for those
     * there is genuinely no provider leg to wait on.
     */
    public function refund(StorefrontOrder $order, User $actor, string $reason): StorefrontOrder
    {
        if (! $this->isRefundable($order)) {
            throw new InvalidOrderTransitionException(
                "Order {$order->reference} cannot be refunded from status '{$order->status}'."
            );
        }

        $gatewayPayment = $this->reclaimablePayment($order);

        // Submitted BEFORE the transaction opens. See the note above about not
        // holding locks across a network call.
        $providerResult = null;

        if ($gatewayPayment !== null) {
            $providerResult = $this->submitProviderRefund($order, $gatewayPayment, $actor, $reason);

            if ($providerResult === null) {
                // Submission failed. The order is deliberately left untouched
                // apart from the recorded failure — no reversal, no refunded
                // status. Staff can retry using the same idempotency key.
                return $order->refresh();
            }
        }

        return DB::transaction(function () use ($order, $actor, $reason, $providerResult) {
            $order->loadMissing('items');

            $reversed = [];
            $handed   = [];

            if ($order->finance_posting_status === 'posted') {
                foreach ($order->items as $item) {
                    if ($item->posted_sale_type === FeedSale::class && $item->posted_sale_id) {
                        $this->reverseFeedSale((int) $item->posted_sale_id, $actor, $order->reference, $reversed, $handed);
                    } elseif ($item->posted_sale_type === PigSale::class && $item->posted_sale_id) {
                        $handed[] = "PigSale#{$item->posted_sale_id}: invoice/COGS/biological-asset reversal + pig restore — process as a sales return";
                    }
                }
            } else {
                $reversed[] = 'No finance posting existed — clean refund, nothing to reverse';
            }

            $this->restoreOperationalAvailability($order, $actor, $handed);

            $note = 'Reversed automatically: ' . (empty($reversed) ? 'none' : implode('; ', $reversed)) . '.';
            if (! empty($handed)) {
                $note .= ' Handed to finance (sales-return): ' . implode('; ', $handed) . '.';
            }

            // Whether this refund is FINISHED depends entirely on the provider.
            //
            // settled  — money is back with the customer (or there was never a
            //            gateway leg to reclaim, e.g. cash on delivery).
            // pending  — the provider accepted the request and will settle
            //            asynchronously. The customer has NOT been paid yet, so
            //            claiming 'refunded' here would be the original defect.
            $providerStatus = $providerResult['status'] ?? null;
            $settled = $providerResult === null
                || in_array($providerStatus, self::PROVIDER_SETTLED_STATUSES, true);

            $attributes = [
                'refunded_by_user_id'   => $actor->id,
                'refund_reason'         => substr($reason, 0, 500),
                'finance_reversal_note' => $note,
            ];

            if ($providerResult !== null) {
                $attributes['refund_provider_reference'] = $providerResult['provider_reference'] ?? null;
                $attributes['refund_provider_status']    = $providerStatus;
                $attributes['refund_submitted_at']       = now();
                $attributes['refund_amount_minor']       = $providerResult['amount'] ?? null;
            }

            if ($settled) {
                $attributes['status']         = StorefrontOrder::STATUS_REFUNDED;
                $attributes['payment_status'] = StorefrontOrder::PAY_REFUNDED;
                $attributes['refunded_at']    = now();
            } else {
                // STATUS_REFUND_APPROVED and PAY_REFUND_PENDING were already
                // declared on the model and never used. This is what they are for.
                $attributes['status']         = StorefrontOrder::STATUS_REFUND_APPROVED;
                $attributes['payment_status'] = StorefrontOrder::PAY_REFUND_PENDING;
                // refunded_at stays NULL: nothing has been refunded yet.
            }

            $order->forceFill($attributes)->save();

            $this->recordEvent(
                $order,
                $actor,
                $settled
                    ? "Order refunded. {$note}"
                    : "Refund submitted to payment provider (status: {$providerStatus}); "
                      . "awaiting settlement. {$note}",
                $settled ? StorefrontOrder::STATUS_REFUNDED : StorefrontOrder::STATUS_REFUND_APPROVED,
            );

            return $order->refresh();
        });
    }

    /**
     * Provider statuses that mean the customer actually has their money back.
     *
     * Paystack reports 'processed' on settlement; 'pending' and 'processing'
     * mean accepted-but-not-yet-paid. Treating those as success is exactly the
     * mistake this finding is about, so the allow-list is deliberately narrow
     * and anything unrecognised counts as NOT settled.
     */
    protected const PROVIDER_SETTLED_STATUSES = ['processed', 'success', 'succeeded', 'reversed'];

    /**
     * The payment whose funds should be reclaimed from the gateway, if any.
     *
     * Only a verified Paystack payment has money at the provider. Cash on
     * delivery is settled outside the gateway, so there is nothing to submit —
     * for those the refund is an internal accounting event and a physical cash
     * return, which is why they settle immediately rather than waiting on a
     * provider that was never involved.
     */
    protected function reclaimablePayment(StorefrontOrder $order): ?StorefrontOrderPayment
    {
        return StorefrontOrderPayment::query()
            ->where('order_id', $order->id)
            ->where('provider', StorefrontOrderPayment::PROVIDER_PAYSTACK)
            ->whereNotNull('provider_reference')
            // Only a VERIFIED payment has money sitting at the provider.
            // 'initiated' and 'submitted' were never confirmed as captured, and
            // submitting a refund against those would ask Paystack to return
            // funds it never took.
            ->where('status', StorefrontOrderPayment::STATUS_VERIFIED)
            ->latest('id')
            ->first();
    }

    /**
     * Submit the refund to the gateway.
     *
     * Returns the provider result, or null when submission failed — in which
     * case the failure is recorded on the order and the caller must not proceed
     * with any internal reversal.
     *
     * The idempotency key is stable per order and stored, so a retry after a
     * timeout is recognised by the provider as the same refund rather than
     * issuing a second one. A duplicated refund is real money leaving twice.
     *
     * @return array{status:string, provider_reference:?string, amount:?int, currency:?string, raw:array<string,mixed>}|null
     */
    protected function submitProviderRefund(
        StorefrontOrder $order,
        StorefrontOrderPayment $payment,
        User $actor,
        string $reason,
    ): ?array {
        $idempotencyKey = $order->refund_idempotency_key
            ?: 'refund-order-' . $order->id . '-' . substr(sha1((string) $order->reference), 0, 12);

        if ($order->refund_idempotency_key !== $idempotencyKey) {
            $order->forceFill(['refund_idempotency_key' => $idempotencyKey])->save();
        }

        try {
            return $this->paystack->refundTransaction(
                transactionReference: (string) $payment->provider_reference,
                amountMinor: null, // full refund; see PaystackClient::refundTransaction()
                reason: substr($reason, 0, 200),
                idempotencyKey: $idempotencyKey,
            );
        } catch (PaymentException $e) {
            Log::error('storefront refund submission failed', [
                'order_id'       => $order->id,
                'order_ref'      => $order->reference,
                'correlation_id' => $e->correlationId(),
                'context'        => $e->context(),
            ]);

            $order->forceFill([
                'refund_provider_status' => 'submission_failed',
                'refund_failure_reason'  => $e->customerMessage(),
            ])->save();

            $this->recordEvent(
                $order,
                $actor,
                'Refund submission to the payment provider FAILED; no money has moved and the '
                . 'order has not been refunded. ' . $e->customerMessage(),
                $order->status,
            );

            return null;
        }
    }

    protected function isRefundable(StorefrontOrder $order): bool
    {
        return in_array($order->status, [
            StorefrontOrder::STATUS_CONFIRMED,
            StorefrontOrder::STATUS_READY_FOR_PICKUP,
            StorefrontOrder::STATUS_OUT_FOR_DELIVERY,
            StorefrontOrder::STATUS_COMPLETED,
            StorefrontOrder::STATUS_REFUND_REQUESTED,
            StorefrontOrder::STATUS_REFUND_APPROVED,
        ], true);
    }

    protected function reverseFeedSale(int $saleId, User $actor, string $orderRef, array &$reversed, array &$handed): void
    {
        $sale = FeedSale::find($saleId);
        if (! $sale) {
            return;
        }

        // Revenue (cash sale: this leg also returns the cash).
        if ($sale->revenue_journal_batch_id) {
            $batch = JournalBatch::find($sale->revenue_journal_batch_id);
            if ($this->reversible($batch)) {
                $this->reversalService->reverse($batch, $actor, "Storefront refund: order {$orderRef}");
                $reversed[] = "FeedSale#{$sale->id} revenue batch #{$batch->id}";
            }
        }

        // Settled cash receipt (COD collected at completion) — returns the cash.
        $receipt = JournalBatch::query()
            ->where('source_type', FeedSale::class)
            ->where('source_id', $sale->id)
            ->where('batch_type', 'auto_feed_sale_receipt')
            ->where('status', JournalBatch::STATUS_POSTED)
            ->first();
        if ($this->reversible($receipt)) {
            $this->reversalService->reverse($receipt, $actor, "Storefront refund (receipt): order {$orderRef}");
            $reversed[] = "FeedSale#{$sale->id} receipt batch #{$receipt->id}";
        }

        // COGS / inventory: no drift-free sales-return primitive — hand to finance.
        if ($sale->cogs_journal_batch_id) {
            $handed[] = "FeedSale#{$sale->id} COGS/inventory (batch #{$sale->cogs_journal_batch_id}) — process as a sales return";
        }
    }

    protected function reversible(?JournalBatch $batch): bool
    {
        return $batch !== null
            && $batch->isPosted()
            && $batch->reversed_by_batch_id === null;
    }

    /**
     * Make stock sellable again (storefront-side only). Product listings have
     * their quantity returned and are republished; pig listings whose pig was
     * sold/soft-deleted are left sold_out and flagged for the sales return.
     */
    protected function restoreOperationalAvailability(StorefrontOrder $order, User $actor, array &$handed): void
    {
        foreach ($order->items as $item) {
            if (! $item->listing_id) {
                continue;
            }
            $listing = StorefrontListing::find($item->listing_id);
            if (! $listing) {
                continue;
            }

            $isPig = in_array($listing->kind, [StorefrontListing::KIND_LIVE_PIG, StorefrontListing::KIND_BREEDING_STOCK], true);

            if ($isPig) {
                // The pig was soft-deleted by the sale spine; restoring it is
                // part of the finance sales return, not a storefront action.
                $handed[] = "Listing #{$listing->id} (pig) remains sold_out pending pig restore";
                continue;
            }

            // Product (feed/manure/etc.): return the quantity and republish.
            if ($listing->quantity_available !== null) {
                $listing->quantity_available = bcadd((string) $listing->quantity_available, (string) $item->quantity, 4);
            }
            if (in_array($listing->status, [StorefrontListing::STATUS_SOLD_OUT, StorefrontListing::STATUS_RESERVED], true)) {
                $listing->status = StorefrontListing::STATUS_PUBLISHED;
            }
            $listing->save();
        }
    }

    /**
     * $toStatus is explicit because this method is now called for outcomes other
     * than a completed refund — a submission failure, or an accepted-but-pending
     * refund. Hard-coding STATUS_REFUNDED meant the audit trail claimed the
     * order was refunded even when it had not been.
     */
    protected function recordEvent(
        StorefrontOrder $order,
        User $actor,
        string $note,
        string $toStatus = StorefrontOrder::STATUS_REFUNDED,
    ): void {
        \App\Models\Storefront\StorefrontOrderEvent::create([
            'organization_id' => $order->organization_id,
            'order_id'        => $order->id,
            'type'            => 'status_change',
            'from_status'     => null,
            'to_status'       => $toStatus,
            'note'            => $note,
            'actor_user_id'   => $actor->id,
        ]);
    }
}
