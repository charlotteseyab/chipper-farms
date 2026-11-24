@php
    $money = fn ($value) => number_format((float) $value, 2);
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    $statusCopy = match ($order->status) {
        'pending_payment' => ['Payment required', 'Complete your online payment so the farm can continue processing this order.', 'amber'],
        'pending_confirmation' => ['Awaiting farm confirmation', 'The farm is checking availability and will confirm your order shortly.', 'amber'],
        'payment_submitted' => ['Payment submitted', 'Your payment information has been submitted and is awaiting review.', 'amber'],
        'payment_under_review' => ['Payment under review', 'The farm is reviewing your submitted payment.', 'amber'],
        'payment_confirmed' => ['Payment received', 'Thank you — your order is paid and reserved for you. We\'re getting it ready and will be in touch shortly.', 'green'],
        'confirmed' => ['Order confirmed', 'Your order has been accepted and is being prepared.', 'green'],
        'ready_for_pickup' => ['Ready for pickup', 'Your order is ready to collect from the farm.', 'green'],
        'out_for_delivery' => ['Out for delivery', 'Your order is currently on its way to you.', 'green'],
        'completed' => ['Order completed', 'This order has been successfully completed.', 'green'],
        'cancelled' => ['Order cancelled', 'This order was cancelled. Contact the farm if you need assistance.', 'red'],
        'failed' => ['Payment unsuccessful', 'Online payment was not completed. You can contact the farm for help.', 'red'],
        'refund_requested' => ['Refund requested', 'Your refund request is being reviewed.', 'amber'],
        'refund_approved' => ['Refund approved', 'The approved refund is being processed.', 'green'],
        'refunded' => ['Order refunded', 'The payment for this order has been refunded.', 'slate'],
        default => [ucfirst(str_replace('_', ' ', $order->status)), 'Open this page anytime to check the latest update.', 'slate'],
    };
    $stateClasses = match ($statusCopy[2]) {
        'green' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-950',
        'red' => 'border-red-200 bg-red-50 text-red-950',
        default => 'border-slate-200 bg-slate-50 text-slate-950',
    };
    $paymentLabels = ['unpaid' => 'Unpaid', 'cash_on_delivery' => 'Cash on delivery', 'paystack_pending' => 'Paystack pending', 'paystack_verified' => 'Paid with Paystack', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'refund_pending' => 'Refund pending', 'refunded' => 'Refunded', 'failed' => 'Payment failed'];
    $needsPayment = $order->payment_method === 'paystack' && $order->payment_status === 'paystack_pending' && $order->status === 'pending_payment';
    $isQuoteOrder = $order->quote_id !== null;
@endphp

<x-storefront.layout :title="'Order ' . $order->reference">
    <style>
        .buyer-order-hero { background: #064e3b !important; color: #fff !important; }
        .buyer-order-hero * { color: inherit; }
        .buyer-order-hero .buyer-order-kicker { color: #a7f3d0 !important; }
        .buyer-order-back { background: #fff !important; color: #064e3b !important; }
        .buyer-paystack-button {
            display: inline-flex !important;
            min-height: 56px;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #064e3b !important;
            color: #fff !important;
            border: 2px solid #064e3b !important;
            border-radius: 14px;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 900;
            box-shadow: 0 10px 24px rgba(6, 78, 59, .24);
        }
        .buyer-paystack-button:hover { background: #022c22 !important; border-color: #022c22 !important; }
        @media (max-width: 639px) { .buyer-paystack-button { width: 100%; } }
    </style>
    <main class="mx-auto max-w-6xl px-4 py-7 sm:px-6 sm:py-12">
        <section class="buyer-order-hero overflow-hidden rounded-3xl px-5 py-7 shadow-md sm:px-8 sm:py-9">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="buyer-order-kicker text-sm font-extrabold uppercase tracking-[0.18em]">Buyer order</p><h1 class="mt-2 break-words text-3xl font-black tracking-tight sm:text-4xl">{{ $order->reference }}</h1><p class="mt-3 text-base font-medium">Placed {{ optional($order->placed_at)->format('d M Y, g:i A') ?? $order->created_at->format('d M Y, g:i A') }}</p></div>
                <div class="flex flex-col items-stretch gap-3 sm:items-end"><div class="sm:text-right"><p class="text-sm font-semibold text-emerald-100">Order total</p><p class="mt-1 text-3xl font-black">{{ $order->currency_code ?: 'GHS' }} {{ $money($order->total) }}</p></div><a href="{{ route('storefront.buyer.orders.index') }}" class="buyer-order-back rounded-xl px-5 py-3 text-center text-sm font-extrabold">← Back to my orders</a></div>
            </div>
        </section>

        <section class="mt-5 rounded-2xl border p-5 sm:p-6 {{ $stateClasses }}"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-lg font-extrabold">{{ $statusCopy[0] }}</p><p class="mt-1 max-w-2xl text-sm font-medium leading-6">{{ $statusCopy[1] }}</p></div><span class="w-fit rounded-full bg-white/80 px-3 py-1.5 text-sm font-extrabold">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></div></section>

        @if($needsPayment)
            <section class="mt-5 rounded-2xl border-2 border-amber-400 bg-amber-100 p-5 shadow-sm sm:p-6"><div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-black uppercase tracking-[0.16em] text-amber-800">Action required</p><h2 class="mt-1 text-xl font-black text-amber-950">Complete your online payment</h2><p class="mt-2 text-sm font-semibold leading-6 text-amber-900">Pay securely through Paystack to continue this order. Retrying will not create another order.</p></div><form method="POST" action="{{ route('storefront.buyer.orders.pay', $order) }}" class="w-full shrink-0 sm:w-auto">@csrf<button class="buyer-paystack-button"><span>Pay {{ $order->currency_code ?: 'GHS' }} {{ $money($order->total) }}</span><span aria-hidden="true">→</span></button></form></div></section>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,.8fr)]">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-3xl border-2 border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-5 sm:px-6"><h2 class="text-xl font-black text-slate-950">Order items</h2><p class="mt-1 text-sm font-medium text-slate-600">{{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }} included in this order.</p></div>
                    <div class="divide-y divide-slate-200">
                        @forelse($order->items as $item)
                            <div class="p-5 sm:p-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><p class="text-base font-extrabold text-slate-950">{{ $item->description }}</p><p class="mt-1 text-sm font-semibold text-slate-600">{{ $quantity($item->quantity) }} {{ $item->displayQuantityUnit() }}</p></div><div class="sm:text-right">@if($isQuoteOrder && bccomp((string) $item->unit_price, '0', 2) === 0)<p class="text-sm font-bold text-emerald-700">Included in quoted total</p>@else<p class="text-sm font-semibold text-slate-600">{{ $order->currency_code ?: 'GHS' }} {{ $money($item->unit_price) }} each</p><p class="mt-1 text-lg font-black text-slate-950">{{ $order->currency_code ?: 'GHS' }} {{ $money($item->line_total) }}</p>@endif</div></div></div>
                        @empty
                            <div class="p-6 text-sm font-medium text-slate-600">No item details are available.</div>
                        @endforelse
                    </div>
                    <div class="border-t-2 border-slate-200 bg-slate-50 px-5 py-5 sm:px-6"><dl class="ml-auto max-w-sm space-y-3 text-sm"><div class="flex justify-between gap-5 font-semibold text-slate-700"><dt>Subtotal</dt><dd>{{ $order->currency_code ?: 'GHS' }} {{ $money($order->subtotal) }}</dd></div>@if(bccomp((string) ($order->delivery_fee ?? '0'), '0', 2) > 0)<div class="flex justify-between gap-5 font-semibold text-slate-700"><dt>Delivery</dt><dd>{{ $order->currency_code ?: 'GHS' }} {{ $money($order->delivery_fee) }}</dd></div>@endif
                    @if(bccomp((string) ($order->tax_total ?? '0'), '0', 2) > 0)<div class="flex justify-between gap-5 font-semibold text-slate-700"><dt>Tax</dt><dd>{{ $order->currency_code ?: 'GHS' }} {{ $money($order->tax_total) }}</dd></div>@endif<div class="flex justify-between gap-5 border-t border-slate-300 pt-3 text-lg font-black text-slate-950"><dt>Total</dt><dd>{{ $order->currency_code ?: 'GHS' }} {{ $money($order->total) }}</dd></div></dl></div>
                </section>

                <section class="rounded-3xl border-2 border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-xl font-black text-slate-950">Order progress</h2><p class="mt-1 text-sm font-medium text-slate-600">Updates from the farm appear here.</p><ol class="mt-6 space-y-0">@forelse($order->events as $event)<li class="relative flex gap-4 pb-6 last:pb-0"><div class="relative z-10 mt-1 h-3 w-3 shrink-0 rounded-full bg-emerald-700 ring-4 ring-emerald-100"></div>@unless($loop->last)<div class="absolute bottom-0 left-[5px] top-4 w-px bg-slate-200"></div>@endunless<div><p class="text-sm font-bold leading-6 text-slate-900">{{ $event->note ?? ucfirst(str_replace('_', ' ', (string) $event->to_status)) }}</p><p class="mt-1 text-xs font-semibold text-slate-500">{{ $event->created_at->format('d M Y, g:i A') }}</p></div></li>@empty<li class="rounded-xl bg-slate-50 p-4 text-sm font-medium text-slate-600">No progress updates have been recorded yet.</li>@endforelse</ol></section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-3xl border-2 border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-black text-slate-950">Order details</h2><dl class="mt-5 space-y-4"><div><dt class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Payment</dt><dd class="mt-1 text-sm font-bold text-slate-900">{{ $paymentLabels[$order->payment_status] ?? ucfirst(str_replace('_', ' ', $order->payment_status)) }}</dd><p class="mt-1 text-xs font-medium text-slate-500">{{ $order->payment_method === 'paystack' ? 'Online through Paystack' : 'Cash on delivery or pickup' }}</p></div><div class="border-t border-slate-200 pt-4"><dt class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Fulfilment</dt><dd class="mt-1 text-sm font-bold text-slate-900">{{ $order->fulfilment_method === 'delivery' ? 'Local delivery' : 'Farm pickup' }}</dd></div>@if($order->quote_id)<div class="border-t border-slate-200 pt-4"><dt class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Order source</dt><dd class="mt-1 text-sm font-bold text-slate-900">Accepted quote</dd></div>@endif</dl></section>

                @if($order->fulfilment_method === 'delivery' && ($order->ship_city || $order->ship_region || $order->ship_address_line_1))<section class="rounded-3xl border-2 border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-black text-slate-950">Delivery details</h2><div class="mt-4 text-sm font-medium leading-6 text-slate-700">@if($order->ship_to_name)<p class="font-bold text-slate-900">{{ $order->ship_to_name }}</p>@endif
                @if($order->ship_address_line_1)<p>{{ $order->ship_address_line_1 }}</p>@endif
                @if($order->ship_address_line_2)<p>{{ $order->ship_address_line_2 }}</p>@endif<p>{{ collect([$order->ship_city, $order->ship_region])->filter()->join(', ') }}</p>@if($order->ship_ghana_post_gps)<p>{{ $order->ship_ghana_post_gps }}</p>@endif
                @if($order->ship_to_phone)<p class="mt-2 font-bold">{{ $order->ship_to_phone }}</p>@endif</div></section>@endif

                @if($order->customer_note)<section class="rounded-3xl border-2 border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-black text-slate-950">Your note</h2><p class="mt-3 whitespace-pre-line text-sm font-medium leading-6 text-slate-700">{{ $order->customer_note }}</p></section>@endif
            </aside>
        </div>
    </main>
</x-storefront.layout>
