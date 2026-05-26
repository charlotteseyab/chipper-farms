<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.customers.show', $customer) }}" class="text-sm text-slate-500 hover:underline">← Back to {{ $customer->name }}</a>
        </div>

        <div class="mb-6 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">{{ $customer->name }} — Ledger</h1>
                <p class="text-sm text-slate-500 font-mono">{{ $customer->code }}</p>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm px-6 py-4 min-w-[240px]">
                <p class="text-xs uppercase text-slate-500 tracking-wide">Outstanding Balance</p>
                <p class="text-2xl font-bold {{ (float) $outstandingBalance > 0.01 ? 'text-red-600' : 'text-emerald-700' }} mt-1">
                    {{ $customer->currency_code ?? 'GHS' }} {{ number_format((float) $outstandingBalance, 2) }}
                </p>
                @if((float) $outstandingBalance > 0.01)
                    <p class="text-xs text-slate-500 mt-1">across open invoices</p>
                @else
                    <p class="text-xs text-slate-500 mt-1">all settled</p>
                @endif
            </div>
        </div>

        {{-- Date filter --}}
        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Filter</button>
                    <a href="{{ route('finance.customers.ledger', $customer) }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Clear</a>
                </div>
            </div>
        </form>

        {{-- Ledger table --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">Description</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                        <th class="px-4 py-3 text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($entries as $entry)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">{{ $entry->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">
                                @if($entry->type === 'invoice')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Invoice</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Receipt</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                @if($entry->type === 'invoice')
                                    <a href="{{ route('finance.invoices.show', $entry->relatedId) }}" class="text-emerald-700 hover:underline">{{ $entry->reference }}</a>
                                @else
                                    <a href="{{ route('finance.receipts.show', $entry->relatedId) }}" class="text-emerald-700 hover:underline">{{ $entry->reference }}</a>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($entry->description, 60) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if(bccomp($entry->debit, '0', 2) > 0)
                                    <span class="text-slate-800">{{ number_format((float) $entry->debit, 2) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(bccomp($entry->credit, '0', 2) > 0)
                                    <span class="text-emerald-700">{{ number_format((float) $entry->credit, 2) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ (float) $entry->runningBalance() > 0.01 ? 'text-red-600' : 'text-slate-700' }}">
                                {{ number_format((float) $entry->runningBalance(), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center px-4 py-8 text-gray-500">No transactions in this date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-500">
                <p><span class="font-medium">{{ $entries->count() }}</span> entries</p>
                <p><span class="font-medium">{{ $entries->where('type', 'invoice')->count() }}</span> invoices</p>
                <p><span class="font-medium">{{ $entries->where('type', 'receipt')->count() }}</span> receipts</p>
            </div>
        @endif
    </div>
</x-app-layout>
