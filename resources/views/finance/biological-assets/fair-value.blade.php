<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Biological Assets — Fair Value Note</h1>
                <p class="text-sm text-gray-500">IAS 41 disclosure (cost model). Fair value less costs to sell, by stage. Not posted.</p>
            </div>
            <a href="{{ route('finance.biological-assets.index') }}" class="px-4 py-2 bg-slate-100 rounded-xl text-sm">← Carrying value</a>
        </div>

        @php
            $d = $disclosure;
            $fmt = fn ($v) => number_format((float) $v, 2);
            $pct = number_format((float) $d['meta']['costs_to_sell_pct'] * 100, 1);
        @endphp

        <div class="mb-4 rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600">
            Carrying values are at accumulated cost. Fair value is estimated as
            live weight × price/kg × (1 − costs-to-sell {{ $pct }}%). Prices come from
            <code>config/biological_assets.php</code> and must be reviewed against the local market.
            @if($d['meta']['has_unreliable'])
                <span class="block mt-1 text-amber-700">⚠ Some stages have no reliable market price configured; fair value for those could not be measured (shown as 0). This is itself an IAS 41 disclosure.</span>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Stage</th>
                        <th class="px-4 py-3 text-right">Pigs</th>
                        <th class="px-4 py-3 text-right">Weight (kg)</th>
                        <th class="px-4 py-3 text-right">Price/kg</th>
                        <th class="px-4 py-3 text-right">Carrying (cost)</th>
                        <th class="px-4 py-3 text-right">Fair value (FVLCTS)</th>
                        <th class="px-4 py-3 text-right">Difference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($d['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-700">
                                {{ $line['label'] }}
                                @unless($line['reliable'])<span class="ml-1 text-xs text-amber-600">(no price)</span>@endunless
                            </td>
                            <td class="px-4 py-2 text-right">{{ number_format($line['pig_count']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['weight_kg']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $line['price_per_kg'] !== null ? $fmt($line['price_per_kg']) : '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['carrying_value']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $line['reliable'] ? $fmt($line['fair_value']) : '—' }}</td>
                            <td class="px-4 py-2 text-right {{ (float) $line['difference'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ $line['reliable'] ? $fmt($line['difference']) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No biological-asset balances to disclose.</td></tr>
                    @endforelse
                </tbody>
                @if(!empty($d['lines']))
                    <tfoot>
                        <tr class="bg-slate-800 text-white font-bold">
                            <td class="px-4 py-3" colspan="4">Total</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($d['totals']['carrying_value']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($d['totals']['fair_value']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($d['totals']['difference']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-400">
            This note does not change the carrying amount of biological assets. Under the cost model the balance sheet
            continues to carry these assets at accumulated cost; this disclosure reports estimated fair value where it
            can be measured reliably, per IAS 41.
        </p>
    </div>
</x-app-layout>
