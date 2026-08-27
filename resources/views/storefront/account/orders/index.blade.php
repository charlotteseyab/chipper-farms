<x-storefront.layout :title="'My orders'">
    <div class="max-w-4xl mx-auto px-4 py-10">
        <a href="{{ route('storefront.account.dashboard') }}" class="text-sm text-slate-500">← Account</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2 mb-6">My orders</h1>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @if($orders->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                You have no orders yet. <a href="{{ route('storefront.shop') }}" class="text-slate-700 underline">Start shopping</a>.
            </div>
        @else
            <div class="space-y-3 sm:hidden">
                @foreach($orders as $o)
                    <a href="{{ route('storefront.account.orders.show', $o) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="font-mono text-xs font-semibold text-slate-800">{{ $o->reference }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] text-slate-600">{{ str_replace('_',' ',$o->status) }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-xs text-slate-400">Payment</dt><dd class="mt-1 text-slate-600">{{ str_replace('_',' ',$o->payment_status) }}</dd></div>
                            <div class="text-right"><dt class="text-xs text-slate-400">Total</dt><dd class="mt-1 font-semibold text-slate-900">{{ $o->currency_code }} {{ number_format((float)$o->total,2) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Placed</dt><dd class="mt-1 text-slate-600">{{ optional($o->placed_at)->format('Y-m-d') }}</dd></div>
                        </dl>
                    </a>
                @endforeach
            </div>
            <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white sm:block">
                <div class="overflow-x-auto" data-responsive-table>
                <table class="min-w-[680px] w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr><th class="px-5 py-2">Ref</th><th class="px-5 py-2">Status</th><th class="px-5 py-2">Payment</th><th class="px-5 py-2">Total</th><th class="px-5 py-2">Placed</th></tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $o)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 py-2 font-mono text-xs"><a href="{{ route('storefront.account.orders.show', $o) }}" class="text-slate-800 hover:underline">{{ $o->reference }}</a></td>
                                <td class="px-5 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ str_replace('_',' ',$o->status) }}</span></td>
                                <td class="px-5 py-2 text-slate-500">{{ str_replace('_',' ',$o->payment_status) }}</td>
                                <td class="px-5 py-2 font-medium">{{ $o->currency_code }} {{ number_format((float)$o->total,2) }}</td>
                                <td class="px-5 py-2 text-slate-500">{{ optional($o->placed_at)->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
</x-storefront.layout>
