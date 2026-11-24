<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Payment Runs</h1>
                <p class="text-sm text-gray-500">Bulk supplier payments — typically a Friday "pay everything due" workflow.</p>
            </div>
            @if(auth()->user()?->hasPermission('create_payment'))
                <div class="flex gap-2">
                    @canopen('finance.payments.index')
                    <a href="{{ route('finance.payments.index') }}" class="inline-flex items-center px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">
                        <i class="fa-solid fa-money-bill-transfer mr-2"></i>Payments
                    </a>
                    @endcanopen
                    <a href="{{ route('finance.payment-runs.create') }}" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                        + New Run
                    </a>
                </div>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total</p>
                <h3 class="mt-1 text-2xl font-bold text-slate-700">{{ $counts['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Drafts</p>
                <h3 class="mt-1 text-2xl font-bold {{ $counts['draft'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $counts['draft'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Executed</p>
                <h3 class="mt-1 text-2xl font-bold text-emerald-700">{{ $counts['executed'] }}</h3>
            </div>
        </div>

        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="all">All</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="executed" @selected($status === 'executed')>Executed</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                        <option value="reversed" @selected($status === 'reversed')>Reversed</option>
                    </select>
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Filter</button>
                    <a href="{{ route('finance.payment-runs.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Reset</a>
                </div>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Run #</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Cur.</th>
                            <th class="px-4 py-3 text-right">Payments</th>
                            <th class="px-4 py-3 text-right">Gross</th>
                            <th class="px-4 py-3 text-right">WHT</th>
                            <th class="px-4 py-3 text-right">Net</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($runs as $r)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs">
                                    <a href="{{ route('finance.payment-runs.show', $r) }}" class="hover:underline">{{ $r->run_number }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $r->run_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $r->currency_code }}</td>
                                <td class="px-4 py-3 text-right">{{ $r->payment_count }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $r->total_gross, 2) }}</td>
                                <td class="px-4 py-3 text-right text-xs">
                                    @if(bccomp((string) $r->total_withholding, '0', 2) > 0)
                                        {{ number_format((float) $r->total_withholding, 2) }}
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-emerald-700">{{ number_format((float) $r->total_net, 2) }}</td>
                                <td class="px-4 py-3">
                                    @php $statusClasses = [
                                        'draft'     => 'bg-slate-100 text-slate-700',
                                        'executed'  => 'bg-emerald-100 text-emerald-800',
                                        'cancelled' => 'bg-slate-100 text-slate-500',
                                        'reversed'  => 'bg-rose-100 text-rose-800',
                                    ]; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$r->status] ?? '' }}">{{ $r->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('finance.payment-runs.show', $r) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center px-4 py-8 text-gray-500">No runs.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $runs->links() }}</div>
        </div>
    </div>
</x-app-layout>
