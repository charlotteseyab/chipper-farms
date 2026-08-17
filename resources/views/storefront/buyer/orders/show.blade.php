@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-storefront.layout :title="'Order ' . $order->reference">
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-700">Buyer order</p>
                <h1 class="text-2xl font-bold text-slate-900">Order {{ $order->reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ str_replace('_', ' ', $order->status) }}</p>
            </div>
            <a href="{{ route('storefront.buyer.orders.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">Back</a>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach($order->items as $item)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-800 truncate">{{ $item->description }}</p>
                            <p class="text-xs text-slate-500">
                                {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->displayQuantityUnit() }}
                                × GHS {{ $money($item->unit_price) }}
                            </p>
                        </div>
                        <span class="tabular-nums">GHS {{ $money($item->line_total) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 py-3 border-t border-slate-100 text-sm space-y-1">
                <div class="flex justify-between text-slate-600"><span>Subtotal</span><span>GHS {{ $money($order->subtotal) }}</span></div>
                <div class="flex justify-between text-slate-600"><span>Delivery</span><span>GHS {{ $money($order->delivery_fee) }}</span></div>
                <div class="flex justify-between font-bold text-slate-800 pt-1"><span>Total</span><span>GHS {{ $money($order->total) }}</span></div>
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-700 mb-3">Timeline</h2>
            <ol class="space-y-3">
                @foreach($order->events as $event)
                    <li class="text-xs">
                        <p class="text-slate-700">{{ $event->note ?? str_replace('_', ' ', (string) $event->to_status) }}</p>
                        <p class="text-slate-400">{{ $event->created_at->format('d M, H:i') }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</x-storefront.layout>
