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

        return view('storefront.admin.orders.show', ['order' => $order]);
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

    protected function assertSameOrg(StorefrontOrder $order): void
    {
        abort_unless((int) $order->organization_id === $this->currentOrganization->requireId(), 403);
    }
}
