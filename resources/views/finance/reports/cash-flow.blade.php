<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Cash Flow Statement</h1>
            <p class="text-sm text-gray-500">Indirect method · {{ $from->toDateString() }} → {{ $to->toDateString() }}</p>
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
            <button class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Apply</button>
        </form>

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    {{-- Operating --}}
                    <tr class="bg-slate-50"><td class="px-4 py-2 font-bold text-slate-700" colspan="2">Operating Activities</td></tr>
                    <tr>
                        <td class="px-4 py-2 text-slate-600">Net profit for the period</td>
                        <td class="px-4 py-2 text-right">{{ $fmt($statement['net_profit']) }}</td>
                    </tr>
                    @foreach($statement['operating']['adjustments'] as $adj)
                        <tr>
                            <td class="px-4 py-2 pl-8 text-slate-600">Add back: {{ $adj['label'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($adj['amount']) }}</td>
                        </tr>
                    @endforeach
                    @foreach($statement['operating']['working_capital'] as $wc)
                        <tr>
                            <td class="px-4 py-2 pl-8 text-slate-600">Δ {{ $wc['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($wc['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold">
                        <td class="px-4 py-2 text-right text-slate-500">Net cash from operating</td>
                        <td class="px-4 py-2 text-right border-t border-slate-200">{{ $fmt($statement['operating']['subtotal']) }}</td>
                    </tr>

                    {{-- Investing --}}
                    <tr class="bg-slate-50"><td class="px-4 py-2 font-bold text-slate-700" colspan="2">Investing Activities</td></tr>
                    @forelse($statement['investing']['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2 pl-8 text-slate-600">{{ $line['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-2 pl-8 text-slate-400 italic" colspan="2">No investing activity</td></tr>
                    @endforelse
                    <tr class="font-semibold">
                        <td class="px-4 py-2 text-right text-slate-500">Net cash from investing</td>
                        <td class="px-4 py-2 text-right border-t border-slate-200">{{ $fmt($statement['investing']['subtotal']) }}</td>
                    </tr>

                    {{-- Financing --}}
                    <tr class="bg-slate-50"><td class="px-4 py-2 font-bold text-slate-700" colspan="2">Financing Activities</td></tr>
                    @forelse($statement['financing']['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2 pl-8 text-slate-600">{{ $line['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-2 pl-8 text-slate-400 italic" colspan="2">No financing activity</td></tr>
                    @endforelse
                    <tr class="font-semibold">
                        <td class="px-4 py-2 text-right text-slate-500">Net cash from financing</td>
                        <td class="px-4 py-2 text-right border-t border-slate-200">{{ $fmt($statement['financing']['subtotal']) }}</td>
                    </tr>

                    {{-- Net change --}}
                    <tr class="bg-slate-800 text-white font-bold">
                        <td class="px-4 py-3 text-right">Net change in cash</td>
                        <td class="px-4 py-3 text-right">{{ $fmt($statement['net_change_in_cash']) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-right text-slate-500">Opening cash</td>
                        <td class="px-4 py-2 text-right">{{ $fmt($statement['opening_cash']) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-right text-slate-500">Closing cash</td>
                        <td class="px-4 py-2 text-right">{{ $fmt($statement['closing_cash']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs {{ $statement['reconciled'] ? 'text-emerald-600' : 'text-red-600' }}">
            @if($statement['reconciled'])
                ✓ Reconciled: derived net change equals the actual movement in cash &amp; bank accounts.
            @else
                ✗ Reconciliation variance of GHS {{ $fmt($statement['variance']) }} between the derived cash flow and the
                actual cash &amp; bank movement. Investigate before relying on these figures.
            @endif
        </p>
    </div>
</x-app-layout>
