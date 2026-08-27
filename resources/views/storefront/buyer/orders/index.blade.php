@php
    $money = fn ($value) => number_format((float) $value, 2);
    $activeStatuses = ['pending_confirmation', 'pending_payment', 'payment_submitted', 'payment_under_review', 'payment_confirmed', 'confirmed', 'ready_for_pickup', 'out_for_delivery'];
    $statusLabels = ['draft' => 'Draft', 'pending_confirmation' => 'Awaiting confirmation', 'pending_payment' => 'Payment required', 'payment_submitted' => 'Payment submitted', 'payment_under_review' => 'Payment under review', 'payment_confirmed' => 'Payment confirmed', 'confirmed' => 'Confirmed', 'ready_for_pickup' => 'Ready for pickup', 'out_for_delivery' => 'Out for delivery', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'failed' => 'Payment failed', 'refund_requested' => 'Refund requested', 'refund_approved' => 'Refund approved', 'refunded' => 'Refunded'];
    $paymentLabels = ['unpaid' => 'Unpaid', 'cash_on_delivery' => 'Cash on delivery', 'paystack_pending' => 'Paystack pending', 'paystack_verified' => 'Paid with Paystack', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'refund_pending' => 'Refund pending', 'refunded' => 'Refunded', 'failed' => 'Payment failed'];
    $activeCount = $orders->whereIn('status', $activeStatuses)->count();
    $completedCount = $orders->where('status', 'completed')->count();
@endphp

<x-storefront.layout title="My orders">
    <style>
        .buyer-orders-hero { background: #064e3b !important; color: #fff !important; }
        .buyer-orders-hero .buyer-kicker { color: #a7f3d0 !important; }
        .buyer-orders-hero .buyer-title,
        .buyer-orders-hero .buyer-description { color: #fff !important; }
        .buyer-orders-hero .buyer-quotes-link { background: #065f46 !important; border-color: rgba(255,255,255,.65) !important; color: #fff !important; }
        .buyer-orders-hero .buyer-shop-link { background: #fff !important; color: #064e3b !important; }
    </style>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <header class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm">
            <div class="buyer-orders-hero px-5 py-7 sm:px-8 sm:py-9">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="buyer-kicker text-sm font-bold uppercase tracking-[0.18em]">Buyer account</p><h1 class="buyer-title mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">My orders</h1><p class="buyer-description mt-3 max-w-xl text-base font-medium leading-7">Track payments, confirmation and fulfilment for every storefront purchase.</p></div>
                    <div class="grid w-full grid-cols-2 gap-3 sm:flex sm:w-auto"><a href="{{ route('storefront.buyer.quotes.index') }}" class="buyer-quotes-link rounded-xl border px-4 py-3 text-center text-sm font-extrabold">My quotes</a><a href="{{ route('storefront.shop') }}" class="buyer-shop-link rounded-xl px-4 py-3 text-center text-sm font-extrabold">Shop products</a></div>
                </div>
            </div>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-4 sm:px-8"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">All orders</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $orders->count() }}</p><p class="mt-1 text-xs text-slate-500">Every order saved to this account.</p></div>
                <div class="px-5 py-4 sm:px-8"><p class="text-xs font-bold uppercase tracking-wide text-amber-600">In progress</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $activeCount }}</p><p class="mt-1 text-xs text-slate-500">Awaiting payment or fulfilment.</p></div>
                <div class="px-5 py-4 sm:px-8"><p class="text-xs font-bold uppercase tracking-wide text-emerald-600">Completed</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $completedCount }}</p><p class="mt-1 text-xs text-slate-500">Successfully completed orders.</p></div>
            </div>
        </header>

        <section class="mt-8">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-xl font-extrabold text-slate-900">Order history</h2><p class="mt-1 text-sm text-slate-500">Newest orders appear first.</p></div>
                <div class="flex flex-wrap items-center gap-3"><p class="text-sm text-slate-500">Signed in as <span class="font-bold text-slate-700">{{ $buyer->name }}</span></p><form method="POST" action="{{ route('storefront.buyer.logout') }}">@csrf<button class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Sign out</button></form></div>
            </div>

            <div class="grid gap-4">
                @forelse($orders as $order)
                    @php
                        $needsPayment = $order->payment_method === 'paystack' && $order->payment_status === 'paystack_pending' && $order->status === 'pending_payment';
                        $isPositive = in_array($order->status, ['payment_confirmed', 'confirmed', 'ready_for_pickup', 'out_for_delivery', 'completed'], true);
                        $isProblem = in_array($order->status, ['cancelled', 'failed', 'refund_requested', 'refund_approved', 'refunded'], true);
                    @endphp
                    <article class="overflow-hidden rounded-2xl border {{ $needsPayment ? 'border-amber-200' : 'border-slate-200' }} bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        @if($needsPayment)<div class="flex items-center justify-between gap-3 bg-amber-50 px-4 py-2.5 text-xs text-amber-800 sm:px-6"><span class="font-bold">Payment is needed to continue this order.</span><span class="hidden sm:inline">Open the order to pay securely.</span></div>@endif
                        <a href="{{ route('storefront.buyer.orders.show', $order) }}" class="block p-4 sm:p-6">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-extrabold tracking-wide text-emerald-700">{{ $order->reference }}</span><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isProblem ? 'bg-red-50 text-red-700' : ($isPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700') }}">{{ $statusLabels[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)) }}</span></div>
                                    <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                                        <div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Placed</p><p class="mt-1 text-sm font-semibold text-slate-700">{{ optional($order->placed_at)->format('d M Y') ?? $order->created_at->format('d M Y') }}</p></div>
                                        <div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Items</p><p class="mt-1 text-sm font-semibold text-slate-700">{{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}</p></div>
                                        <div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Payment</p><p class="mt-1 text-sm font-semibold {{ $needsPayment ? 'text-amber-700' : 'text-slate-700' }}">{{ $paymentLabels[$order->payment_status] ?? ucfirst(str_replace('_', ' ', $order->payment_status)) }}</p></div>
                                        <div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Fulfilment</p><p class="mt-1 text-sm font-semibold text-slate-700">{{ $order->fulfilment_method === 'delivery' ? 'Delivery' : 'Farm pickup' }}</p></div>
                                    </div>
                                </div>
                                <div class="flex items-end justify-between gap-4 border-t border-slate-100 pt-4 lg:min-w-48 lg:flex-col lg:items-end lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                                    <div class="lg:text-right"><p class="text-xs text-slate-400">Order total</p><p class="mt-1 text-xl font-extrabold text-slate-900">{{ $order->currency_code ?: 'GHS' }} {{ $money($order->total) }}</p></div><span class="inline-flex items-center gap-1 text-sm font-bold {{ $needsPayment ? 'text-amber-700' : 'text-emerald-700' }}">{{ $needsPayment ? 'Continue to payment' : 'View order' }} <span aria-hidden="true">→</span></span>
                                </div>
                            </div>
                        </a>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center sm:px-10"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-2xl" aria-hidden="true">🛍</div><h2 class="mt-5 text-lg font-extrabold text-slate-900">No orders yet</h2><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Purchases will appear here after checkout or after you accept a priced quote.</p><div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row"><a href="{{ route('storefront.shop') }}" class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Browse products</a><a href="{{ route('storefront.quote.create') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700">Request a quote</a></div></div>
                @endforelse
            </div>
        </section>
    </main>
</x-storefront.layout>
