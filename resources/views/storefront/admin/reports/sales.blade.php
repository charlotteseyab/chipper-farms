<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Storefront sales report</h1>

        <form method="GET" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
            <label class="text-sm">From<input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="block mt-1 rounded-xl border-slate-300 text-sm"></label>
            <label class="text-sm">To<input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="block mt-1 rounded-xl border-slate-300 text-sm"></label>
            <button class="rounded-xl bg-slate-800 text-white text-sm px-4 py-2">Apply</button>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl bg-white border border-slate-200 p-4"><p class="text-xs uppercase text-slate-400">Orders</p><p class="text-2xl font-bold">{{ $summary['orders_total'] }}</p></div>
            <div class="rounded-2xl bg-white border border-slate-200 p-4"><p class="text-xs uppercase text-slate-400">Open</p><p class="text-2xl font-bold">{{ $summary['orders_open'] }}</p></div>
            <div class="rounded-2xl bg-white border border-slate-200 p-4"><p class="text-xs uppercase text-slate-400">Completed</p><p class="text-2xl font-bold">{{ $summary['orders_completed'] }}</p></div>
            <div class="rounded-2xl bg-white border border-slate-200 p-4"><p class="text-xs uppercase text-slate-400">Revenue realised</p><p class="text-2xl font-bold">GHS {{ number_format((float)$summary['revenue_realised'],2) }}</p></div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                <h2 class="font-semibold text-slate-700 mb-3">By status</h2>
                <table class="w-full text-sm"><tbody>
                    @foreach($byStatus as $status => $row)
                        <tr class="border-t border-slate-100"><td class="py-2">{{ str_replace('_',' ',$status) }}</td><td class="py-2 text-right">{{ $row->n }}</td><td class="py-2 text-right text-slate-500">GHS {{ number_format((float)$row->total,2) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                <h2 class="font-semibold text-slate-700 mb-3">By payment method</h2>
                <table class="w-full text-sm"><tbody>
                    @foreach($byMethod as $method => $row)
                        <tr class="border-t border-slate-100"><td class="py-2">{{ strtoupper((string)$method) }}</td><td class="py-2 text-right">{{ $row->n }}</td><td class="py-2 text-right text-slate-500">GHS {{ number_format((float)$row->total,2) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-4">Pipeline (open orders): GHS {{ number_format((float)$summary['revenue_pipeline'],2) }}. Revenue realised counts completed orders only.</p>
    </div>
</x-app-layout>
