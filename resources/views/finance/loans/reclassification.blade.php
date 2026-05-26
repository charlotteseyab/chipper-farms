<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.loans.index') }}" class="text-sm text-blue-600 hover:underline">← Loans</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Loan Reclassification</h1>
            <p class="text-sm text-gray-500">Current vs long-term principal (IAS 1) as at {{ $report['as_of'] }}.</p>
        </div>

        <form method="GET" class="mb-4 flex gap-2 items-end">
            <div><label class="block text-xs text-slate-600 mb-1">As of</label>
                <input type="date" name="as_of_date" value="{{ $report['as_of'] }}" class="rounded-xl border-slate-200 text-sm"></div>
            <button class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Apply</button>
        </form>

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Loan</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                        <th class="px-4 py-3 text-right">Current (≤12m)</th>
                        <th class="px-4 py-3 text-right">Non-current</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($report['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-700">{{ $line['reference'] }} — {{ $line['lender_name'] }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ ucfirst($line['loan_type']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['outstanding']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['current_portion']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['non_current_portion']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No active loans.</td></tr>
                    @endforelse
                </tbody>
                @if(!empty($report['lines']))
                    <tfoot>
                        <tr class="bg-slate-800 text-white font-bold">
                            <td class="px-4 py-3" colspan="2">Total</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($report['totals']['outstanding']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($report['totals']['current']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($report['totals']['non_current']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-400">
            All principal is posted to the current liability account on disbursement. To reflect the split on the balance
            sheet, an accountant may post a manual reclassification journal: Dr current principal / Cr long-term principal
            for the non-current total shown above.
        </p>
    </div>
</x-app-layout>
