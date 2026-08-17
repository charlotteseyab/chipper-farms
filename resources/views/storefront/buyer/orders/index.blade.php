@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-storefront.layout title="My buyer orders">
    <div class="max-w-4xl mx-auto px-4 py-10 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">My orders</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $buyer->name }}</p>
            </div>
            <form method="POST" action="{{ route('storefront.buyer.logout') }}">
                @csrf
                <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium">Sign out</button>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @forelse($orders as $order)
                <a href="{{ route('storefront.buyer.orders.show', $order) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 text-sm last:border-0">
                    <span>
                        <span class="block font-semibold text-slate-800">{{ $order->reference }}</span>
                        <span class="text-slate-500">{{ str_replace('_', ' ', $order->status) }}</span>
                    </span>
                    <span class="font-semibold text-slate-900">GHS {{ $money($order->total) }}</span>
                </a>
            @empty
                <div class="px-5 py-8 text-sm text-slate-500">No orders are saved to this buyer account yet.</div>
            @endforelse
        </div>
    </div>
</x-storefront.layout>
