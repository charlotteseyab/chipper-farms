<x-app-layout>
    @php use App\Models\Storefront\StorefrontOrder; @endphp
    <div class="max-w-4xl mx-auto py-6 px-4">
        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="text-xs font-mono text-slate-400">{{ $order->reference }}</p>
                <h1 class="text-2xl font-bold text-gray-800">Order from {{ optional($order->counterparty)->name ?? 'customer' }}</h1>
                <p class="text-sm text-slate-500 mt-1">
                    <span class="font-medium">{{ str_replace('_',' ',$order->status) }}</span>
                    · {{ strtoupper($order->payment_method) }} ({{ str_replace('_',' ',$order->payment_status) }})
                    · {{ $order->fulfilment_method }}
                </p>
            </div>
            <a href="{{ route('storefront.admin.orders.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Items</h2>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-2">{{ $item->description }}</td>
                                    <td class="py-2 text-slate-500">{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->quantity_unit }} × {{ number_format((float)$item->unit_price,2) }}</td>
                                    <td class="py-2 text-right font-medium">GHS {{ number_format((float)$item->line_total,2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="border-t border-slate-200 mt-3 pt-3 text-sm space-y-1">
                        <div class="flex justify-between text-slate-500"><span>Subtotal</span><span>GHS {{ number_format((float)$order->subtotal,2) }}</span></div>
                        <div class="flex justify-between text-slate-500"><span>Delivery</span><span>GHS {{ number_format((float)$order->delivery_fee,2) }}</span></div>
                        <div class="flex justify-between font-bold text-slate-800"><span>Total</span><span>GHS {{ number_format((float)$order->total,2) }}</span></div>
                    </div>
                </div>

                @if($order->fulfilment_method === StorefrontOrder::FULFIL_DELIVERY)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm">
                        <h2 class="font-semibold text-slate-700 mb-2">Delivery</h2>
                        <p class="text-slate-600">{{ $order->ship_to_name }} · {{ $order->ship_to_phone }}<br>{{ $order->ship_address_line_1 }} {{ $order->ship_address_line_2 }}<br>{{ $order->ship_city }} {{ $order->ship_region }} {{ $order->ship_ghana_post_gps }}</p>
                    </div>
                @endif

                @if($order->payments->isNotEmpty())
                    <div class="rounded-2xl bg-white border border-slate-200 p-5">
                        <h2 class="font-semibold text-slate-700 mb-3">Payments</h2>
                        <table class="w-full text-sm">
                            <thead class="text-slate-400 text-left"><tr><th class="py-1">Provider</th><th class="py-1">Ref</th><th class="py-1">Amount</th><th class="py-1">Status</th></tr></thead>
                            <tbody>
                                @foreach($order->payments as $p)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2">{{ strtoupper($p->provider) }}</td>
                                        <td class="py-2 font-mono text-xs">{{ $p->provider_reference }}</td>
                                        <td class="py-2">GHS {{ number_format((float)$p->amount,2) }}</td>
                                        <td class="py-2">{{ $p->status }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Timeline</h2>
                    <ol class="space-y-3">
                        @foreach($order->events as $event)
                            <li class="text-xs">
                                <p class="text-slate-700">{{ $event->note ?? str_replace('_',' ',(string)$event->to_status) }}</p>
                                <p class="text-slate-400">{{ $event->created_at->format('d M, H:i') }} @if($event->actor) · {{ $event->actor->name }}@endif</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- Actions --}}
            <div class="space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-3">
                    <h2 class="font-semibold text-slate-700">Actions</h2>

                    @if($order->isConfirmable())
                        @can('confirm_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.confirm', $order) }}">@csrf
                                <button class="w-full px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm">Confirm order</button>
                            </form>
                            <p class="text-xs text-slate-400">Confirming reserves the listing(s). Payment, if online, is already verified.</p>
                        @endcan
                    @endif

                    @if($order->payment_method === 'paystack' && $order->payment_status === StorefrontOrder::PAY_PAYSTACK_PEND)
                        @can('confirm_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.verify-payment', $order) }}" class="border-t border-slate-100 pt-3">@csrf
                                <button class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">Re-verify payment</button>
                            </form>
                        @endcan
                    @endif

                    @if($order->status === StorefrontOrder::STATUS_CONFIRMED)
                        @can('manage_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.dispatch', $order) }}" class="border-t border-slate-100 pt-3">@csrf
                                <button class="w-full px-3 py-2 rounded-xl bg-slate-700 text-white text-sm">
                                    {{ $order->fulfilment_method === StorefrontOrder::FULFIL_DELIVERY ? 'Mark out for delivery' : 'Mark ready for pickup' }}
                                </button>
                            </form>
                        @endcan
                    @endif

                    @if($order->isCancellable())
                        @can('cancel_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.cancel', $order) }}" class="border-t border-slate-100 pt-3" onsubmit="return confirm('Cancel this order?');">@csrf
                                <input type="text" name="reason" placeholder="Reason (optional)" class="w-full rounded-xl border-slate-300 text-sm mb-2">
                                <button class="w-full px-3 py-2 rounded-xl border border-red-300 text-red-600 text-sm">Cancel order</button>
                            </form>
                        @endcan
                    @endif
                </div>

                @if($order->customer_note)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm">
                        <p class="text-xs uppercase text-slate-400 mb-1">Customer note</p>
                        <p class="text-slate-600">{{ $order->customer_note }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
