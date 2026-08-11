<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\Exceptions\InvalidOrderTransitionException;
use App\Storefront\Services\OrderService;
use App\Storefront\Services\PaystackService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Back-office order workspace. Gated by storefront order permissions.
 * Confirmation is always an explicit staff action here — never automatic.
 */
class AdminOrderController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected OrderService $orders,
        protected PaystackService $paystack,
    ) {
    }

    public function index(Request $request): View
    {
        $orgId = $this->currentOrganization->requireId();

        $query = StorefrontOrder::query()
            ->where('organization_id', $orgId)
            ->withCount('items');

        if ($status = $request->string('status')->toString()) {
            if (in_array($status, StorefrontOrder::STATUSES, true)) {
                $query->where('status', $status);
            }
        } else {
            $query->open();
        }

        if ($method = $request->string('payment_method')->toString()) {
            $query->where('payment_method', $method);
        }

        $orders = $query->latest()->paginate(20)->withQueryString();

        return view('storefront.admin.orders.index', [
            'orders'  => $orders,
            'filters' => [
                'status'         => $request->string('status')->toString(),
                'payment_method' => $request->string('payment_method')->toString(),
            ],
        ]);
    }

    public function show(StorefrontOrder $order): View
    {
        $this->assertSameOrg($order);
        $order->load(['items.listing', 'payments', 'events.actor', 'counterparty']);

        return view('storefront.admin.orders.show', [
            'order' => $order,
            // §46: "the admin order page should become the recovery console for
            // storefront states." The order already recorded whether its
            // finance posting failed or was skipped, and showed it nowhere —
            // so a line that never reached the books was invisible on the one
            // page a staff member actually opens.
            'replayableEvent' => $this->replayableEventFor($order),
            'liveHolds'       => \App\Models\Storefront\StorefrontInventoryHold::query()
                ->where('order_id', $order->id)
                ->with('listing')
                ->get(),
        ]);
    }

    /**
     * The finance event this order could sensibly be re-run from, if any.
     *
     * Only a FAILED event is offered. A processed one has already done its
     * work, and while the posting idempotency key would make a replay a no-op,
     * offering the button would suggest there is something to fix when there is
     * not.
     */
    protected function replayableEventFor(StorefrontOrder $order): ?\App\Models\DomainEvent
    {
        if ($order->finance_posting_status === 'posted') {
            return null;
        }

        return \App\Models\DomainEvent::query()
            ->where('event_type', \App\Domain\Events\Sales\StorefrontOrderConfirmed::TYPE)
            ->where('source_id', $order->id)
            ->where('status', \App\Models\DomainEvent::STATUS_FAILED)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Re-queue a failed finance posting (§46: "replay event after fixing
     * cause").
     *
     * SAFE TO PRESS TWICE, BY CONSTRUCTION
     * ------------------------------------
     * This does not post anything. It returns the outbox event to `pending` so
     * the normal claimer picks it up, and every posting downstream is guarded by
     * `journal_postings.idempotency_key` — a replay that already succeeded
     * writes no second journal. The subscribers carry their own guards too (the
     * credit-note path refuses to refund twice on a replayed event).
     *
     * Restricted to events that actually FAILED. Re-queueing a processed event
     * would be a no-op that looked like an action.
     */
    public function retryFinancePosting(StorefrontOrder $order): RedirectResponse
    {
        $this->assertSameOrg($order);

        $event = $this->replayableEventFor($order);

        if ($event === null) {
            return back()->with('error', 'There is no failed finance posting to retry on this order.');
        }

        $event->forceFill([
            'status'           => \App\Models\DomainEvent::STATUS_PENDING,
            'claimed_at'       => null,
            'claimed_by'       => null,
            'next_retry_at'    => null,
            'processing_error' => null,
        ])->save();

        $order->forceFill([
            'finance_posting_status' => 'pending',
            'finance_posting_error'  => null,
        ])->save();

        $this->orders->recordEvent(
            order: $order,
            type: 'finance',
            note: 'Finance posting re-queued by staff after a failure.',
            actorId: auth()->id(),
        );

        return back()->with(
            'success',
            'Finance posting re-queued. It will be retried automatically; check back shortly.',
        );
    }

    public function confirm(StorefrontOrder $order): RedirectResponse
    {
        $this->assertSameOrg($order);
        $this->authorizeConfirm();

        return $this->guarded(
            fn () => $this->orders->confirm($order, request()->user()),
            "Order {$order->reference} confirmed."
        );
    }

    public function cancel(Request $request, StorefrontOrder $order): RedirectResponse
    {
        $this->assertSameOrg($order);
        abort_unless($request->user()->hasPermission('cancel_storefront_orders'), 403);

        $reason = $request->string('reason')->toString() ?: null;

        return $this->guarded(
            fn () => $this->orders->cancel($order, $request->user(), $reason),
            "Order {$order->reference} cancelled."
        );
    }

    public function fulfil(StorefrontOrder $order): RedirectResponse
    {
        $this->assertSameOrg($order);
        $this->authorizeConfirm();

        return $this->guarded(
            fn () => $this->orders->markReadyOrDispatched($order, request()->user()),
            "Order {$order->reference} updated."
        );
    }

    public function complete(StorefrontOrder $order): RedirectResponse
    {
        $this->assertSameOrg($order);
        $this->authorizeConfirm();

        return $this->guarded(
            fn () => $this->orders->complete($order, request()->user()),
            "Order {$order->reference} completed."
        );
    }

    public function addNote(Request $request, StorefrontOrder $order): RedirectResponse
    {
        $this->assertSameOrg($order);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->orders->recordEvent($order, 'note', null, null, $validated['note'], $request->user()->id);

        // Keep a rolling staff_notes summary too.
        $order->forceFill([
            'staff_notes' => trim(($order->staff_notes ? $order->staff_notes . "\n" : '')
                . '[' . now()->format('Y-m-d H:i') . ' — ' . $request->user()->name . '] ' . $validated['note']),
        ])->save();

        return back()->with('success', 'Note added.');
    }

    public function refund(
        Request $request,
        StorefrontOrder $order,
        \App\Storefront\Services\StorefrontOrderRefundService $refunds
    ): RedirectResponse {
        $this->assertSameOrg($order);

        // Treasurer-grade: NOT available to the salesperson role.
        abort_unless($request->user()?->hasPermission('refund_storefront_orders'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $refunds->refund($order, $request->user(), $validated['reason']);
        } catch (InvalidOrderTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order {$order->reference} refunded.");
    }

    /**
     * Re-verify a Paystack payment from the back office (e.g. webhook missed).
     */
    public function verifyPayment(StorefrontOrder $order): RedirectResponse
    {
        $this->assertSameOrg($order);
        $this->authorizeConfirm();

        $payment = $order->payments()
            ->where('provider', 'paystack')
            ->latest()->first();

        if ($payment === null) {
            return back()->with('error', 'No Paystack payment to verify.');
        }

        try {
            $this->paystack->verify($payment->provider_reference, request()->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Verification failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Payment re-verified.');
    }

    protected function guarded(\Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidOrderTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    protected function authorizeConfirm(): void
    {
        abort_unless(request()->user()->hasPermission('confirm_storefront_orders'), 403);
    }

    /**
     * 404, not 403 (Phase 16, IMP-112).
     *
     * This answered 403 until Phase 16, which told a prober that the order
     * exists and belongs to someone else. Another farm's records should be
     * indistinguishable from records that were never there — the reference
     * itself is information, and storefront references are sequential and easy
     * to guess.
     *
     * A 403 is the right answer when the resource IS yours and you may not do
     * this to it; that is the permission layer's job, and it still answers 403.
     */
    protected function assertSameOrg(StorefrontOrder $order): void
    {
        abort_unless((int) $order->organization_id === $this->currentOrganization->requireId(), 404);
    }
}
