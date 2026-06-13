<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Orders</h1>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <form method="GET" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-3">
            <select name="status" class="rounded-xl border-slate-300 text-sm">
                <option value="">Open orders</option>
                @foreach(\App\Models\Storefront\StorefrontOrder::STATUSES as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ str_replace('_',' ',$s) }}</option>
                @endforeach
            </select>
            <select name="payment_method" class="rounded-xl border-slate-300 text-sm">
                <option value="">Any payment</option>
                <option value="cod" @selected(($filters['payment_method'] ?? '')==='cod')>COD</option>
                <option value="paystack" @selected(($filters['payment_method'] ?? '')==='paystack')>Paystack</option>
            </select>
            <button class="rounded-xl bg-slate-800 text-white text-sm px-4 py-2">Filter</button>
        </form>

        <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr><th class="px-5 py-2">Ref</th><th class="px-5 py-2">Customer</th><th class="px-5 py-2">Status</th><th class="px-5 py-2">Payment</th><th class="px-5 py-2">Total</th><th class="px-5 py-2">Placed</th></tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 py-2 font-mono text-xs"><a href="{{ route('storefront.admin.orders.show', $o) }}" class="text-slate-800 hover:underline">{{ $o->reference }}</a></td>
                            <td class="px-5 py-2">{{ optional($o->counterparty)->name ?? '—' }}</td>
                            <td class="px-5 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ str_replace('_',' ',$o->status) }}</span></td>
                            <td class="px-5 py-2 text-slate-500">{{ strtoupper($o->payment_method) }} · {{ str_replace('_',' ',$o->payment_status) }}</td>
                            <td class="px-5 py-2 font-medium">{{ $o->currency_code }} {{ number_format((float)$o->total,2) }}</td>
                            <td class="px-5 py-2 text-slate-500">{{ optional($o->placed_at)->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    </div>
</x-app-layout>
