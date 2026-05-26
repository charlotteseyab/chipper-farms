<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Biological Assets</h1>
                <p class="text-sm text-gray-500">Carrying value by stage (cost model, IAS 41) · as at {{ $asOf->toDateString() }}</p>
            </div>
            <a href="{{ route('finance.biological-assets.fair-value') }}" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Fair-value note</a>
        </div>

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        {{-- Reconciliation banner --}}
        @php $r = $reconciliation; @endphp
        <div class="mb-4 rounded-xl px-4 py-3 text-sm {{ $r['reconciled'] ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' }}">
            @if($r['reconciled'])
                ✓ Reconciled: stage subledger ties to the GL stage accounts and to the group cost pools; clearing account 1230 is drained.
            @else
                ⚠ Reconciliation differences detected:
                <ul class="mt-1 list-disc list-inside">
                    @foreach(['subledger_vs_gl','subledger_vs_pools','clearing_drained'] as $k)
                        @if(!$r[$k]['reconciled'])
                            <li>{{ $r[$k]['label'] }} — variance GHS {{ $fmt($r[$k]['variance']) }} (left {{ $fmt($r[$k]['left']) }} vs right {{ $fmt($r[$k]['right']) }})</li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Stage</th>
                        <th class="px-4 py-3 text-left">Account</th>
                        <th class="px-4 py-3 text-left">Class</th>
                        <th class="px-4 py-3 text-right">Pigs</th>
                        <th class="px-4 py-3 text-right">Weight (kg)</th>
                        <th class="px-4 py-3 text-right">Carrying value (GHS)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($byStage as $stage)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-700">{{ $stage['label'] }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ $stage['account_code'] }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ ucfirst(str_replace('_',' ', (string) $stage['classification'])) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($stage['pig_count']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($stage['weight_kg']) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ $fmt($stage['carrying_value']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No biological-asset balances yet.</td></tr>
                    @endforelse
                </tbody>
                @if(!empty($byStage))
                    <tfoot>
                        <tr class="bg-slate-800 text-white font-bold">
                            <td class="px-4 py-3" colspan="3">Total</td>
                            <td class="px-4 py-3 text-right">{{ number_format(collect($byStage)->sum('pig_count')) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt(collect($byStage)->reduce(fn($c,$s)=>bcadd($c,(string)$s['weight_kg'],4),'0')) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt(collect($byStage)->reduce(fn($c,$s)=>bcadd($c,(string)$s['carrying_value'],4),'0')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-app-layout>
