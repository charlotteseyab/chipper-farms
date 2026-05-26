<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Balance Sheet</h1>
                <p class="text-sm text-gray-500">As at {{ $asOf->toDateString() }}</p>
            </div>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap gap-2 items-end">
            <div>
                <label class="block text-xs text-slate-600 mb-1">As of</label>
                <input type="date" name="as_of_date" value="{{ $asOf->toDateString() }}" class="rounded-xl border-slate-200 text-sm">
            </div>
            <button class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Apply</button>
        </form>

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        @unless($statement['balanced'])
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                ⚠ Balance sheet does not balance. Variance: GHS {{ $fmt($statement['variance']) }}.
                This indicates a GL integrity problem — investigate before relying on these figures.
            </div>
        @endunless

        <div class="grid md:grid-cols-2 gap-4">
            {{-- Assets --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-4 py-3 font-bold text-slate-700">Assets</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        @foreach($statement['assets']['sections'] as $sec)
                            @if(count($sec['lines']))
                                <tr class="bg-slate-50/50"><td class="px-4 py-2 font-semibold text-slate-600" colspan="2">{{ $sec['label'] }}</td></tr>
                                @foreach($sec['lines'] as $line)
                                    <tr>
                                        <td class="px-4 py-2 pl-8 text-slate-600">{{ $line['code'] }} — {{ $line['name'] }}</td>
                                        <td class="px-4 py-2 text-right">{{ $fmt($line['amount']) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                        <tr class="bg-slate-800 text-white font-bold">
                            <td class="px-4 py-3 text-right">Total Assets</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($statement['assets']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Liabilities + Equity --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-4 py-3 font-bold text-slate-700">Liabilities &amp; Equity</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        @foreach($statement['liabilities']['sections'] as $sec)
                            @if(count($sec['lines']))
                                <tr class="bg-slate-50/50"><td class="px-4 py-2 font-semibold text-slate-600" colspan="2">{{ $sec['label'] }}</td></tr>
                                @foreach($sec['lines'] as $line)
                                    <tr>
                                        <td class="px-4 py-2 pl-8 text-slate-600">{{ $line['code'] }} — {{ $line['name'] }}</td>
                                        <td class="px-4 py-2 text-right">{{ $fmt($line['amount']) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                        <tr class="font-medium">
                            <td class="px-4 py-2 text-right text-slate-500">Total Liabilities</td>
                            <td class="px-4 py-2 text-right border-t border-slate-200">{{ $fmt($statement['liabilities']['total']) }}</td>
                        </tr>

                        @foreach($statement['equity']['sections'] as $sec)
                            <tr class="bg-slate-50/50"><td class="px-4 py-2 font-semibold text-slate-600" colspan="2">{{ $sec['label'] }}</td></tr>
                            @foreach($sec['lines'] as $line)
                                <tr>
                                    <td class="px-4 py-2 pl-8 text-slate-600">{{ $line['code'] }} — {{ $line['name'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ $fmt($line['amount']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr>
                            <td class="px-4 py-2 pl-8 text-slate-600 italic">Current-Year Earnings (not yet closed)</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($statement['equity']['current_year_earnings']) }}</td>
                        </tr>
                        <tr class="font-medium">
                            <td class="px-4 py-2 text-right text-slate-500">Total Equity</td>
                            <td class="px-4 py-2 text-right border-t border-slate-200">{{ $fmt($statement['equity']['total']) }}</td>
                        </tr>

                        <tr class="bg-slate-800 text-white font-bold">
                            <td class="px-4 py-3 text-right">Total Liabilities &amp; Equity</td>
                            <td class="px-4 py-3 text-right">{{ $fmt((float) $statement['liabilities']['total'] + (float) $statement['equity']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs {{ $statement['balanced'] ? 'text-emerald-600' : 'text-red-600' }}">
            {{ $statement['balanced'] ? '✓ Assets = Liabilities + Equity. The balance sheet balances.' : '✗ Out of balance — see warning above.' }}
        </p>
    </div>
</x-app-layout>
