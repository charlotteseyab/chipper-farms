<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Receipts</h1>
                <p class="text-sm text-gray-500">Customer payments — cash, bank, mobile money, cheques.</p>
            </div>
            @if(auth()->user()?->hasPermission('create_receipt'))
                <a href="{{ route('finance.receipts.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    + New Receipt
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
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-700">{{ $counts['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Unallocated</p>
                <h3 class="mt-1 text-3xl font-bold {{ $counts['unallocated'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $counts['unallocated'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Reversed</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-500">{{ $counts['reversed'] }}</h3>
            </div>
            <div class="rounded-2xl border border-blue-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Backfilled</p>
                <h3 class="mt-1 text-3xl font-bold text-blue-700">{{ $counts['backfilled'] }}</h3>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2 items-center">
            <a href="{{ route('finance.receipts.index') }}" class="px-4 py-1.5 text-sm rounded-full {{ ! $unallocatedOnly && $status === 'all' ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">All</a>
            <a href="{{ route('finance.receipts.index', ['status' => 'posted']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'posted' ? 'bg-emerald-600 text-white' : 'bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">Posted</a>
            <a href="{{ route('finance.receipts.index', ['unallocated' => 1]) }}" class="px-4 py-1.5 text-sm rounded-full {{ $unallocatedOnly ? 'bg-amber-600 text-white' : 'bg-white border border-amber-200 text-amber-700 hover:bg-amber-50' }}">Unallocated</a>
            <a href="{{ route('finance.receipts.index', ['status' => 'reversed']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'reversed' ? 'bg-slate-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">Reversed</a>

            <form method="GET" class="ml-auto flex items-center gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search receipt or reference..." class="rounded-xl border-slate-200 text-sm">
                <button type="submit" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Search</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Receipt #</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Method</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Unallocated</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($receipts as $r)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">
                                    <a href="{{ route('finance.receipts.show', $r) }}" class="hover:underline">{{ $r->receipt_number }}</a>
                                    @if($r->is_backfilled)<span class="ml-1 text-blue-600 text-xs">[backfill]</span>@endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $r->receipt_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-slate-800">{{ $r->counterparty?->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $r->counterparty?->code }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 text-xs">{{ $r->paymentMethod?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ $r->currency_code }} {{ number_format((float) $r->amount, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @php $unalloc = (float) $r->unallocatedAmount(); @endphp
                                    @if($unalloc > 0.005)
                                        <span class="text-amber-700 font-medium">{{ number_format($unalloc, 2) }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php $statusClasses = ['posted' => 'bg-emerald-100 text-emerald-800', 'reversed' => 'bg-slate-100 text-slate-700']; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$r->status] ?? '' }}">
                                        {{ $r->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('finance.receipts.show', $r) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center px-4 py-8 text-gray-500">No receipts found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $receipts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
