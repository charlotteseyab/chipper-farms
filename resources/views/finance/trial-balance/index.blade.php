<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Trial Balance</h1>
                <p class="text-sm text-gray-500">
                    Range:
                    {{ $fromDate ?? 'inception' }} → {{ $asOfDate }}
                </p>
            </div>
        </div>

        <form method="GET" class="mb-4 flex flex-col sm:flex-row flex-wrap gap-2 items-end">
            <div>
                <label class="block text-xs text-slate-600 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="rounded-xl border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">As of Date</label>
                <input type="date" name="as_of_date" value="{{ $asOfDate }}" class="rounded-xl border-slate-200 text-sm">
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="hide_zero" value="1" @checked($hideZeroBalance) class="rounded border-slate-300">
                Hide accounts with no activity
            </label>
            <button type="submit" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Refresh</button>
        </form>

        @if(! $totals['balanced'])
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <strong>UNBALANCED.</strong> Total debits do not equal total credits.
                Difference: GHS {{ number_format(abs((float) $totals['debit'] - (float) $totals['credit']), 2) }}.
                This indicates a serious posting integrity issue. Investigate immediately.
            </div>
        @endif

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Account</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">Total Debit</th>
                            <th class="px-4 py-3 text-right">Total Credit</th>
                            <th class="px-4 py-3 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $r)
                            <tr class="hover:bg-slate-50 {{ $r->has_activity ? '' : 'opacity-50' }}">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $r->code }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('finance.coa.show', $r->account_id) }}" class="text-slate-800 hover:underline">{{ $r->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 capitalize">{{ $r->account_type }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{ (float) $r->total_debit > 0 ? number_format((float) $r->total_debit, 2) : '' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ (float) $r->total_credit > 0 ? number_format((float) $r->total_credit, 2) : '' }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format((float) $r->net, 2) }}
                                    <span class="text-xs text-slate-400 ml-1">{{ $r->is_debit_normal ? 'Dr' : 'Cr' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No accounts to display.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-slate-700">TOTALS</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $totals['debit'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $totals['credit'], 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($totals['balanced'])
                                    <span class="text-emerald-700">✓ Balanced</span>
                                @else
                                    <span class="text-red-700">✗ {{ number_format(abs((float) $totals['debit'] - (float) $totals['credit']), 2) }} off</span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
