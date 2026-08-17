<x-storefront.layout :title="'Order ' . $order->reference">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <a href="{{ route('storefront.account.orders.index') }}" class="text-sm text-slate-500">← My orders</a>

        @if(session('success'))<div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mt-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="mt-4 flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Order {{ $order->reference }}</h1>
                <p class="text-sm text-slate-500 mt-1">Placed {{ optional($order->placed_at)->format('d M Y, H:i') }}</p>
            </div>
            <span class="px-3 py-1 rounded-full text-sm bg-slate-100 text-slate-700">{{ str_replace('_',' ', $order->status) }}</span>
        </div>

        @if($order->status === \App\Models\Storefront\StorefrontOrder::STATUS_PENDING_CONFIRMATION)
            <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 text-sm">Awaiting confirmation from our team. We'll be in touch shortly.</div>
        @elseif($order->status === \App\Models\Storefront\StorefrontOrder::STATUS_PENDING_PAYMENT)
            <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 text-sm">Awaiting payment. If you didn't finish paying, please contact us.</div>
        @elseif($order->status === \App\Models\Storefront\StorefrontOrder::STATUS_PAYMENT_CONFIRMED)
            <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">Payment received. Your order is now awaiting final confirmation.</div>
        @endif

        <div class="mt-6 grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Items</h2>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-2">{{ $item->description }}</td>
                                    <td class="py-2 text-slate-500">{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->displayQuantityUnit() }} × {{ number_format((float)$item->unit_price,2) }}</td>
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

                @if($order->fulfilment_method === \App\Models\Storefront\StorefrontOrder::FULFIL_DELIVERY)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm">
                        <h2 class="font-semibold text-slate-700 mb-2">Delivery to</h2>
                        <p class="text-slate-600">{{ $order->ship_to_name }}<br>{{ $order->ship_address_line_1 }} {{ $order->ship_address_line_2 }}<br>{{ $order->ship_city }} {{ $order->ship_region }}</p>
                    </div>
                @else
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm text-slate-600">Farm pickup.</div>
                @endif
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm">
                    <p class="text-xs uppercase text-slate-400">Payment</p>
                    <p class="font-medium text-slate-800">{{ $order->payment_method === 'paystack' ? 'Paystack (online)' : 'Cash on delivery' }}</p>
                    <p class="text-slate-500 mt-1">{{ str_replace('_',' ', $order->payment_status) }}</p>
                </div>

                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3 text-sm">Timeline</h2>
                    <ol class="space-y-3">
                        @foreach($order->events as $event)
                            <li class="text-xs">
                                <p class="text-slate-700">{{ $event->note ?? str_replace('_',' ', (string)$event->to_status) }}</p>
                                <p class="text-slate-400">{{ $event->created_at->format('d M, H:i') }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>
</x-storefront.layout>
