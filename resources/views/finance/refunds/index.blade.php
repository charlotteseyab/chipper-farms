<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Refunds</h1>
                <p class="text-sm text-gray-500">Money paid back to customers. Posts Dr AR control / Cr Cash.</p>
            </div>
            @if(auth()->user()?->hasPermission('create_refund'))
                <a href="{{ route('finance.refunds.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                    + New Refund
                </a>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-700">{{ $counts['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Drafts</p>
                <h3 class="mt-1 text-3xl font-bold {{ $counts['draft'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $counts['draft'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Posted</p>
                <h3 class="mt-1 text-3xl font-bold text-emerald-700">{{ $counts['posted'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Reversed</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-500">{{ $counts['reversed'] }}</h3>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('finance.refunds.index') }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'all' ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">All</a>
            <a href="{{ route('finance.refunds.index', ['status' => 'draft']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'draft' ? 'bg-amber-600 text-white' : 'bg-white border border-amber-200 text-amber-700 hover:bg-amber-50' }}">Drafts</a>
            <a href="{{ route('finance.refunds.index', ['status' => 'posted']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'posted' ? 'bg-emerald-600 text-white' : 'bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">Posted</a>
            <a href="{{ route('finance.refunds.index', ['status' => 'reversed']) }}" class="px-4 py-1.5 text-sm rounded-full {{ $status === 'reversed' ? 'bg-slate-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">Reversed</a>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Refund #</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Customer</th>
                        <th class="px-4 py-3 text-left">Method</th>
                        <th class="px-4 py-3 text-left">From</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($refunds as $r)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs">
                                <a href="{{ route('finance.refunds.show', $r) }}" class="hover:underline">{{ $r->refund_number }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $r->refund_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">
                                <div class="text-slate-800">{{ $r->counterparty?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400 font-mono">{{ $r->counterparty?->code }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 text-xs">{{ $r->paymentMethod?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if($r->sourceReceipt)
                                    <a href="{{ route('finance.receipts.show', $r->sourceReceipt) }}" class="text-emerald-700 hover:underline font-mono">{{ $r->sourceReceipt->receipt_number }}</a>
                                @else
                                    <span class="text-slate-300">standalone</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium">{{ $r->currency_code }} {{ number_format((float) $r->amount, 2) }}</td>
                            <td class="px-4 py-3">
                                @php $statusClasses = ['draft' => 'bg-amber-100 text-amber-800', 'posted' => 'bg-emerald-100 text-emerald-800', 'reversed' => 'bg-slate-100 text-slate-700']; @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$r->status] ?? '' }}">{{ $r->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                <a href="{{ route('finance.refunds.show', $r) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center px-4 py-8 text-gray-500">No refunds.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $refunds->links() }}</div>
        </div>
    </div>
</x-app-layout>
