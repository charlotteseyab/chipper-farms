<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Income Statement</h1>
                <p class="text-sm text-gray-500">{{ $from->toDateString() }} → {{ $to->toDateString() }}</p>
            </div>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap gap-2 items-end">
            <div>
                <label class="block text-xs text-slate-600 mb-1">From</label>
                <input type="date" name="from_date" value="{{ $from->toDateString() }}" class="rounded-xl border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">To</label>
                <input type="date" name="to_date" value="{{ $to->toDateString() }}" class="rounded-xl border-slate-200 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="compare" value="1" @checked($compare) class="rounded border-slate-300">
                Compare to prior period
            </label>
            <button class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Apply</button>
        </form>

        @php
            $cmp = $statement['comparison'] ?? null;
            $fmt = fn ($v) => number_format((float) $v, 2);
            $sections = $statement['sections'];
        @endphp

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Account</th>
                        <th class="px-4 py-3 text-right">Amount (GHS)</th>
                        @if($cmp)<th class="px-4 py-3 text-right text-slate-400">Prior</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach(['revenue' => 'Revenue', 'cost_of_sales' => 'Cost of Sales', 'opex' => 'Operating Expenses', 'other_income' => 'Other Income', 'finance_cost' => 'Finance Costs', 'other_expense' => 'Other Expenses'] as $key => $label)
                        @php $sec = $sections[$key]; @endphp
                        @if(count($sec['lines']) || (float) $sec['subtotal'] != 0)
                            <tr class="bg-slate-50/50">
                                <td class="px-4 py-2 font-semibold text-slate-700" colspan="{{ $cmp ? 3 : 2 }}">{{ $sec['label'] }}</td>
                            </tr>
                            @foreach($sec['lines'] as $line)
                                <tr>
                                    <td class="px-4 py-2 pl-8 text-slate-600">{{ $line['code'] }} — {{ $line['name'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ $fmt($line['amount']) }}</td>
                                    @if($cmp)<td class="px-4 py-2 text-right text-slate-400"></td>@endif
                                </tr>
                            @endforeach
                            <tr class="font-medium">
                                <td class="px-4 py-2 text-right text-slate-500">Total {{ $sec['label'] }}</td>
                                <td class="px-4 py-2 text-right border-t border-slate-200">{{ $fmt($sec['subtotal']) }}</td>
                                @if($cmp)<td class="px-4 py-2 text-right border-t border-slate-200 text-slate-400">{{ $fmt($cmp[$key] ?? 0) }}</td>@endif
                            </tr>

                            @if($key === 'cost_of_sales')
                                <tr class="bg-emerald-50 font-bold text-emerald-900">
                                    <td class="px-4 py-2 text-right">Gross Profit</td>
                                    <td class="px-4 py-2 text-right">{{ $fmt($statement['gross_profit']) }}</td>
                                    @if($cmp)<td class="px-4 py-2 text-right">{{ $fmt($cmp['gross_profit'] ?? 0) }}</td>@endif
                                </tr>
                            @elseif($key === 'opex')
                                <tr class="bg-emerald-50 font-bold text-emerald-900">
                                    <td class="px-4 py-2 text-right">Operating Profit</td>
                                    <td class="px-4 py-2 text-right">{{ $fmt($statement['operating_profit']) }}</td>
                                    @if($cmp)<td class="px-4 py-2 text-right">{{ $fmt($cmp['operating_profit'] ?? 0) }}</td>@endif
                                </tr>
                            @endif
                        @endif
                    @endforeach

                    <tr class="bg-slate-800 text-white font-bold text-base">
                        <td class="px-4 py-3 text-right">Net Profit</td>
                        <td class="px-4 py-3 text-right">{{ $fmt($statement['net_profit']) }}</td>
                        @if($cmp)<td class="px-4 py-3 text-right text-slate-300">{{ $fmt($cmp['net_profit'] ?? 0) }}</td>@endif
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-400">
            Revenue is shown net of returns. Cost of sales includes feed, pig COGS, mortality, and inventory adjustments.
            Figures are from posted journals only.
        </p>
    </div>
</x-app-layout>
