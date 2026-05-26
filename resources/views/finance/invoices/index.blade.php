<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Invoices</h1>
                <p class="text-sm text-gray-500">Sales invoices — drafts, posted, and reversals.</p>
            </div>
            @if(auth()->user()?->hasPermission('create_invoice'))
                <a href="{{ route('finance.invoices.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    + New Invoice
                </a>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Drafts</p>
                <h3 class="mt-1 text-3xl font-bold text-amber-700">{{ $counts['draft'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Posted</p>
                <h3 class="mt-1 text-3xl font-bold text-emerald-700">{{ $counts['posted'] }}</h3>
            </div>
            <div class="rounded-2xl border border-red-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Overdue</p>
                <h3 class="mt-1 text-3xl font-bold {{ $counts['overdue'] > 0 ? 'text-red-700' : 'text-slate-400' }}">{{ $counts['overdue'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Cancelled</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-500">{{ $counts['cancelled'] }}</h3>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2 items-center">
            <a href="{{ route('finance.invoices.index') }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'all' ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">All</a>
            <a href="{{ route('finance.invoices.index', ['status' => 'draft']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'draft' ? 'bg-amber-600 text-white' : 'bg-white border border-amber-200 text-amber-700 hover:bg-amber-50' }}">Draft</a>
            <a href="{{ route('finance.invoices.index', ['status' => 'posted']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'posted' ? 'bg-emerald-600 text-white' : 'bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">Posted</a>
            <a href="{{ route('finance.invoices.index', ['overdue' => 1]) }}" class="px-4 py-1.5 text-sm rounded-full {{ $overdueOnly ? 'bg-red-600 text-white' : 'bg-white border border-red-200 text-red-700 hover:bg-red-50' }}">Overdue</a>
            <a href="{{ route('finance.invoices.index', ['status' => 'cancelled']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'cancelled' ? 'bg-slate-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">Cancelled</a>

            <form method="GET" class="ml-auto flex items-center gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search invoice or reference..." class="rounded-xl border-slate-200 text-sm">
                <button type="submit" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Search</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Invoice #</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Due</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $inv)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">
                                    <a href="{{ route('finance.invoices.show', $inv) }}" class="hover:underline">{{ $inv->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $inv->invoice_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-slate-800">{{ $inv->counterparty?->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $inv->counterparty?->code }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClasses = [
                                            'draft' => 'bg-amber-100 text-amber-800',
                                            'posted' => 'bg-emerald-100 text-emerald-800',
                                            'cancelled' => 'bg-slate-100 text-slate-700',
                                            'reversed' => 'bg-slate-100 text-slate-700',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$inv->status] ?? '' }}">
                                        {{ $inv->status }}
                                    </span>
                                    @if($inv->is_cash_invoice)
                                        <span class="ml-1 text-xs text-slate-400">cash</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if($inv->due_date)
                                        @php $overdue = $inv->status === 'posted' && ! $inv->is_cash_invoice && $inv->due_date->isPast(); @endphp
                                        <span class="{{ $overdue ? 'text-red-600 font-semibold' : 'text-slate-600' }}">{{ $inv->due_date->format('Y-m-d') }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ $inv->currency_code }} {{ number_format((float) $inv->total, 2) }}</td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('finance.invoices.show', $inv) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center px-4 py-8 text-gray-500">No invoices found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $invoices->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
