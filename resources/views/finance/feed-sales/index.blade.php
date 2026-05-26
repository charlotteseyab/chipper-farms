<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Feed Sales</h1>
                <p class="text-sm text-gray-500">Sales of finished feed. Revenue and COGS post automatically.</p>
            </div>
            @can('record_feed_sales')
                <a href="{{ route('finance.feed-sales.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Record sale</a>
            @endcan
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @php
            $fmt = fn ($v) => number_format((float) $v, 2);
            $badge = fn ($s) => match($s){'posted'=>'bg-emerald-50 text-emerald-700','failed'=>'bg-red-50 text-red-700','skipped'=>'bg-slate-100 text-slate-500',default=>'bg-amber-50 text-amber-700'};
        @endphp

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Feed</th>
                        <th class="px-4 py-3 text-right">Qty (kg)</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-left">Channel</th>
                        <th class="px-4 py-3 text-left">Revenue</th>
                        <th class="px-4 py-3 text-left">COGS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($sales as $sale)
                        <tr>
                            <td class="px-4 py-3 text-slate-500">{{ $sale->sale_date->toDateString() }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $sale->feedType->name ?? ('#' . $sale->feed_type_id) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($sale->quantity_kg) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($sale->sale_total) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ ucfirst($sale->channel) }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $badge($sale->revenue_posting_status) }}">{{ $sale->revenue_posting_status }}</span></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs {{ $badge($sale->cogs_posting_status) }}">{{ $sale->cogs_posting_status }}</span>
                                @if($sale->cogs_amount !== null)<span class="text-xs text-slate-400 ml-1">{{ $fmt($sale->cogs_amount) }}</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No feed sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $sales->links() }}</div>
    </div>
</x-app-layout>
