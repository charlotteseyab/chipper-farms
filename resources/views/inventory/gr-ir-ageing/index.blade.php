<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">GR/IR Ageing</h1>
            <p class="text-sm text-slate-500">Goods received but not yet invoiced. Outstanding accrual per supplier.</p>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Supplier</th>
                        <th class="px-3 py-2 text-right">Open entries</th>
                        @foreach($buckets as $b)
                            <th class="px-3 py-2 text-right">{{ $b['label'] }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-right">Total (GH₵)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bySupplier as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 font-medium text-slate-800">{{ $row['supplier_name'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['entries_count'] }}</td>
                            @foreach($buckets as $b)
                                <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $row['buckets'][$b['label']], 2) }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-mono font-semibold text-slate-900">
                                {{ number_format((float) $row['total_outstanding'], 2) }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('inventory.gr-ir-ageing.show', $row['supplier_id']) }}"
                                   class="text-xs text-emerald-700 hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($buckets) }}" class="px-3 py-10 text-center text-slate-500">No outstanding GR/IR balances.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
