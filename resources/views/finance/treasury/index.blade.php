<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Treasury</h1>
            <p class="text-sm text-gray-500">Cash position &amp; upcoming loan obligations as at {{ $position['as_of'] }}.</p>
        </div>

        <form method="GET" class="mb-4 flex gap-2 items-end">
            <div><label class="block text-xs text-slate-600 mb-1">As of</label>
                <input type="date" name="as_of_date" value="{{ $position['as_of'] }}" class="rounded-xl border-slate-200 text-sm"></div>
            <button class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Apply</button>
        </form>

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        <div class="grid md:grid-cols-2 gap-4">
            {{-- Cash --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-4 py-3 font-bold text-slate-700">Cash &amp; Bank</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        @forelse($position['cash_accounts'] as $acct)
                            <tr>
                                <td class="px-4 py-2 text-slate-600">{{ $acct['code'] }} — {{ $acct['name'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $fmt($acct['balance']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-6 text-center text-slate-400">No cash balances.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-800 text-white font-bold">
                            <td class="px-4 py-3">Total cash</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($position['total_cash']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Upcoming obligations --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-4 py-3 font-bold text-slate-700">Upcoming Loan Obligations</div>
                <table class="w-full text-sm">
                    <thead class="text-slate-500 text-xs">
                        <tr><th class="px-3 py-2 text-left">Loan / period</th><th class="px-3 py-2 text-left">Due</th><th class="px-3 py-2 text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($position['upcoming'] as $u)
                            <tr>
                                <td class="px-3 py-2 text-slate-600">{{ $u['loan_reference'] }} · P{{ $u['period_number'] }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $u['due_date'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($u['total_due']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-6 text-center text-slate-400">No upcoming instalments.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-slate-700">
                            <td class="px-3 py-2" colspan="2">Total upcoming</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($position['upcoming_total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-400">
            Cash balances are derived from posted journals on the accounts under 1000 Cash and Bank. Upcoming obligations
            are the next unpaid amortisation instalments across all active loans.
        </p>
    </div>
</x-app-layout>
