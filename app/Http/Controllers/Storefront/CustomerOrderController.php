<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer "My orders". A customer may only ever see their own orders.
 */
class CustomerOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = StorefrontOrder::query()
            ->forUser($request->user()->id)
            ->latest()
            ->paginate(15);

        return view('storefront.account.orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, StorefrontOrder $order): View
    {
        abort_unless((int) $order->customer_user_id === (int) $request->user()->id, 403);

        $order->load(['items.listing', 'payments', 'events.actor']);

        return view('storefront.account.orders.show', ['order' => $order]);
    }
}
