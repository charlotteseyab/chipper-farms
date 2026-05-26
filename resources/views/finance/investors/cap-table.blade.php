<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Cap Table</h1>
                <p class="text-sm text-gray-500">Ownership and capital balances by investor.</p>
            </div>
            <a href="{{ route('finance.investors.index') }}" class="px-4 py-2 bg-slate-100 rounded-xl text-sm">← Investors</a>
        </div>

        @php $fmt = fn ($v) => number_format((float) $v, 2); $r = $reconciliation; @endphp

        <div class="mb-4 rounded-xl px-4 py-3 text-sm {{ $r['reconciled'] ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' }}">
            @if($r['reconciled'])
                ✓ Reconciled: investor capital subledger ties to the 3100/3110/3120 GL block.
            @else
                ⚠ {{ $r['label'] }} — variance GHS {{ $fmt($r['variance']) }} (subledger {{ $fmt($r['subledger']) }} vs GL {{ $fmt($r['gl']) }}).
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Investor</th>
                        <th class="px-4 py-3 text-left">Class</th>
                        <th class="px-4 py-3 text-right">Ownership %</th>
                        <th class="px-4 py-3 text-right">Contributed</th>
                        <th class="px-4 py-3 text-right">Allocated</th>
                        <th class="px-4 py-3 text-right">Distributed</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3 text-right">Share of capital</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($capTable['rows'] as $row)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-700">{{ $row['name'] }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ ucfirst($row['class']) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format((float) $row['ownership_pct'], 2) }}%</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($row['total_contributed']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($row['total_allocated']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($row['total_distributed']) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ $fmt($row['current_balance']) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format((float) $row['share_of_capital'], 2) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No investors.</td></tr>
                    @endforelse
                </tbody>
                @if(!empty($capTable['rows']))
                    <tfoot>
                        <tr class="bg-slate-800 text-white font-bold">
                            <td class="px-4 py-3" colspan="6">Total capital</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($capTable['totals']['total_capital']) }}</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-app-layout>
