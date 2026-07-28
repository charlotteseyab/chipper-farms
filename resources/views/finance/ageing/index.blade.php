<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        @php
            $isAp = $report->isAp();
            $title = $report->reportTitle();
            $noun = $report->counterpartyNoun();
            $exportRoute = $isAp ? 'finance.ageing.export-ap' : 'finance.ageing.export';
            $resetRoute  = $isAp ? 'finance.ageing.ap'         : 'finance.ageing.index';
            $labels = $report->bucketLabels();
        @endphp

        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">{{ $title }}</h1>
                <p class="text-sm text-slate-500">
                    @if($isAp)
                        Outstanding payables to suppliers, bucketed by overdue days.
                    @else
                        Outstanding receivables from customers, bucketed by overdue days.
                    @endif
                    As of <strong>{{ $asOf->format('Y-m-d') }}</strong>.
                </p>

                {{-- Companion-report cross-link --}}
                <p class="mt-2 text-xs text-slate-400">
                    @if($isAp)
                        <a href="{{ route('finance.ageing.index') }}" class="hover:underline">View AR Ageing →</a>
                    @else
                        @if(auth()->user()?->hasPermission('view_ap_ageing'))
                            <a href="{{ route('finance.ageing.ap') }}" class="hover:underline">View AP Ageing →</a>
                        @endif
                    @endif
                </p>
            </div>
            @if(auth()->user()?->hasPermission('export_finance'))
                <a href="{{ route($exportRoute, ['as_of' => $asOf->toDateString()]) }}"
                   class="inline-flex items-center px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl">
                    <i class="fa-solid fa-file-csv mr-2"></i> Export CSV
                </a>
            @endif
        </div>

        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">As of</label>
                    <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">{{ $noun }} (optional)</label>
                    <select name="counterparty_id" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">All {{ strtolower($noun) }}s</option>
                        @foreach($counterparties as $c)
                            <option value="{{ $c->id }}" @selected($counterpartyId === $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Update</button>
                    <a href="{{ route($resetRoute) }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Reset</a>
                </div>
            </div>
        </form>

        @if($report->isEmpty())
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center">
                <p class="text-emerald-800 text-lg font-medium">
                    @if($isAp)
                        All suppliers settled.
                    @else
                        All customers settled.
                    @endif
                </p>
                <p class="text-emerald-700 text-sm mt-1">
                    @if($isAp)
                        No outstanding payables as of {{ $asOf->format('Y-m-d') }}.
                    @else
                        No outstanding receivables as of {{ $asOf->format('Y-m-d') }}.
                    @endif
                </p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 mb-4">
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                    <p class="text-xs text-slate-500 uppercase tracking-wide">{{ $labels['current'] }}</p>
                    <p class="text-xl font-bold text-emerald-700 mt-1">{{ number_format((float) $report->grandTotals['current'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                    <p class="text-xs text-slate-500 uppercase tracking-wide">{{ $labels['bucket_1'] }}</p>
                    <p class="text-xl font-bold text-amber-700 mt-1">{{ number_format((float) $report->grandTotals['bucket_1'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                    <p class="text-xs text-slate-500 uppercase tracking-wide">{{ $labels['bucket_2'] }}</p>
                    <p class="text-xl font-bold text-orange-600 mt-1">{{ number_format((float) $report->grandTotals['bucket_2'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                    <p class="text-xs text-slate-500 uppercase tracking-wide">{{ $labels['bucket_3'] }}</p>
                    <p class="text-xl font-bold text-red-600 mt-1">{{ number_format((float) $report->grandTotals['bucket_3'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-red-200 bg-red-50 shadow-sm p-4">
                    <p class="text-xs text-red-700 uppercase tracking-wide font-semibold">{{ $labels['bucket_4'] }}</p>
                    <p class="text-xl font-bold text-red-700 mt-1">{{ number_format((float) $report->grandTotals['bucket_4'], 2) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-slate-50 shadow-sm p-4">
                    <p class="text-xs text-slate-700 uppercase tracking-wide font-semibold">Grand Total</p>
                    <p class="text-xl font-bold text-slate-800 mt-1">{{ number_format((float) $report->grandTotals['total'], 2) }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ $noun }}</th>
                                <th class="px-4 py-3 text-right">Docs</th>
                                <th class="px-4 py-3 text-right">{{ $labels['current'] }}</th>
                                <th class="px-4 py-3 text-right">{{ $labels['bucket_1'] }}</th>
                                <th class="px-4 py-3 text-right">{{ $labels['bucket_2'] }}</th>
                                <th class="px-4 py-3 text-right">{{ $labels['bucket_3'] }}</th>
                                <th class="px-4 py-3 text-right">{{ $labels['bucket_4'] }}</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                @if(! $isAp)
                                    <th class="px-4 py-3 text-right">Limit</th>
                                @endif
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($report->customerLines as $line)
                                @php
                                    $severe = $line->hasSevereOverdue();
                                    $overLimit = ! $isAp && $line->exceedsCreditLimit();
                                    $rowClass = $severe ? 'bg-red-50 hover:bg-red-100' :
                                                ($overLimit ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-slate-50');
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="px-4 py-3">
                                        @if($isAp)
                                            @if(\Illuminate\Support\Facades\Route::has('finance.suppliers.show'))
                                                <a href="{{ route('finance.suppliers.show', $line->counterparty) }}" class="font-medium text-slate-800 hover:underline">{{ $line->counterparty->name }}</a>
                                            @else
                                                <span class="font-medium text-slate-800">{{ $line->counterparty->name }}</span>
                                            @endif
                                        @else
                                            @if(\Illuminate\Support\Facades\Route::has('finance.customers.ledger'))
                                                <a href="{{ route('finance.customers.ledger', $line->counterparty) }}" class="font-medium text-slate-800 hover:underline">{{ $line->counterparty->name }}</a>
                                            @else
                                                <span class="font-medium text-slate-800">{{ $line->counterparty->name }}</span>
                                            @endif
                                        @endif
                                        <div class="text-xs text-slate-400 font-mono">{{ $line->counterparty->code }}</div>
                                        @if(! $isAp && $line->counterparty->credit_hold_mode === 'block')
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded-full bg-red-100 text-red-800 text-xs font-medium">HOLD</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ $line->invoiceCount }}</td>
                                    <td class="px-4 py-3 text-right">@if(bccomp($line->buckets['current'], '0', 2) > 0){{ number_format((float) $line->buckets['current'], 2) }}@endif</td>
                                    <td class="px-4 py-3 text-right text-amber-700">@if(bccomp($line->buckets['bucket_1'], '0', 2) > 0){{ number_format((float) $line->buckets['bucket_1'], 2) }}@endif</td>
                                    <td class="px-4 py-3 text-right text-orange-600">@if(bccomp($line->buckets['bucket_2'], '0', 2) > 0){{ number_format((float) $line->buckets['bucket_2'], 2) }}@endif</td>
                                    <td class="px-4 py-3 text-right text-red-600">@if(bccomp($line->buckets['bucket_3'], '0', 2) > 0){{ number_format((float) $line->buckets['bucket_3'], 2) }}@endif</td>
                                    <td class="px-4 py-3 text-right text-red-700 font-medium">@if(bccomp($line->buckets['bucket_4'], '0', 2) > 0){{ number_format((float) $line->buckets['bucket_4'], 2) }}@endif</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-800">{{ number_format((float) $line->buckets['total'], 2) }}</td>
                                    @if(! $isAp)
                                        <td class="px-4 py-3 text-right text-xs">
                                            @if($line->counterparty->credit_limit)
                                                {{ number_format((float) $line->counterparty->credit_limit, 0) }}
                                                @if($line->exceedsCreditLimit())<span class="ml-1 text-red-600">Warning</span>@endif
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-right text-xs">
                                        @if($isAp)
                                            @if(\Illuminate\Support\Facades\Route::has('finance.supplier-statements.create'))
                                                <a href="{{ route('finance.supplier-statements.create', ['counterparty_id' => $line->counterparty->id]) }}" class="text-emerald-700 hover:underline">Reconcile</a>
                                            @endif
                                        @else
                                            @if(\Illuminate\Support\Facades\Route::has('finance.statements.show'))
                                                <a href="{{ route('finance.statements.show', $line->counterparty) }}" class="text-emerald-700 hover:underline">Statement</a>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-100 font-semibold border-t-2 border-slate-300">
                                <td class="px-4 py-3 text-slate-800 uppercase text-xs">Grand Total</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ $report->customerCount() }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $report->grandTotals['current'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $report->grandTotals['bucket_1'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $report->grandTotals['bucket_2'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $report->grandTotals['bucket_3'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-red-700">{{ number_format((float) $report->grandTotals['bucket_4'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-800">{{ number_format((float) $report->grandTotals['total'], 2) }}</td>
                                @if(! $isAp)
                                    <td class="px-4 py-3"></td>
                                @endif
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                @if($isAp)
                    Suppliers with rows highlighted in red have balances over 90 days overdue — relationship risk. Sorted by total exposure descending.
                @else
                    Customers with rows highlighted in red have balances over 90 days overdue. Amber rows are over their credit limit. Sorted by total balance descending.
                @endif
            </p>
        @endif
    </div>
</x-app-layout>
