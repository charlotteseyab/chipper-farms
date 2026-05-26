<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Vendor Invoices</h1>
                <p class="text-sm text-gray-500">Bills from suppliers. Posts Dr Inventory/Expense / Cr AP control.</p>
            </div>
            @if(auth()->user()?->hasPermission('create_vendor_invoice'))
                <a href="{{ route('finance.vendor-invoices.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                    + New Vendor Invoice
                </a>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total</p>
                <h3 class="mt-1 text-2xl font-bold text-slate-700">{{ $counts['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Drafts</p>
                <h3 class="mt-1 text-2xl font-bold {{ $counts['draft'] > 0 ? 'text-slate-600' : 'text-slate-400' }}">{{ $counts['draft'] }}</h3>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Pending Approval</p>
                <h3 class="mt-1 text-2xl font-bold {{ $counts['pending_approval'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $counts['pending_approval'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Posted</p>
                <h3 class="mt-1 text-2xl font-bold text-emerald-700">{{ $counts['posted'] }}</h3>
            </div>
            <div class="rounded-2xl border border-red-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Open AP</p>
                <h3 class="mt-1 text-2xl font-bold {{ $counts['open'] > 0 ? 'text-red-600' : 'text-slate-400' }}">{{ $counts['open'] }}</h3>
            </div>
        </div>

        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="all">All</option>
                        <option value="draft" @selected($status === 'draft')>Drafts</option>
                        <option value="pending_approval" @selected($status === 'pending_approval')>Pending Approval</option>
                        <option value="posted" @selected($status === 'posted')>Posted</option>
                        <option value="open" @selected($status === 'open')>Open (unpaid)</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                        <option value="reversed" @selected($status === 'reversed')>Reversed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
                    <select name="supplier_id" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">All</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected($supplierId == $s->id)>{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Match Status</label>
                    <select name="match_status" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">All</option>
                        <option value="matched_clean" @selected($matchStatus === 'matched_clean')>Clean match</option>
                        <option value="matched_variance" @selected($matchStatus === 'matched_variance')>Variance</option>
                        <option value="pending" @selected($matchStatus === 'pending')>Pending match</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Filter</button>
                    <a href="{{ route('finance.vendor-invoices.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Reset</a>
                </div>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">VI #</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Supplier</th>
                            <th class="px-4 py-3 text-left">Their Ref</th>
                            <th class="px-4 py-3 text-left">PR</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Match</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $inv)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs">
                                    <a href="{{ route('finance.vendor-invoices.show', $inv) }}" class="hover:underline">{{ $inv->vendor_invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $inv->invoice_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-slate-800">{{ $inv->counterparty?->name }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $inv->counterparty?->code }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono text-slate-600">{{ $inv->supplier_invoice_number ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs">
                                    @if($inv->purchaseRequest)
                                        <span class="font-mono text-slate-600">{{ $inv->purchaseRequest->request_no }}</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ $inv->currency_code }} {{ number_format((float) $inv->total, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($inv->isPosted() && (float) $inv->balance_due > 0)
                                        <span class="font-medium text-red-700">{{ number_format((float) $inv->balance_due, 2) }}</span>
                                    @elseif($inv->isFullyPaid() && $inv->isPosted())
                                        <span class="text-emerald-700 text-xs">paid</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php $statusClasses = [
                                        'draft'            => 'bg-slate-100 text-slate-700',
                                        'pending_approval' => 'bg-amber-100 text-amber-800',
                                        'posted'           => 'bg-emerald-100 text-emerald-800',
                                        'cancelled'        => 'bg-slate-100 text-slate-500',
                                        'reversed'         => 'bg-rose-100 text-rose-800',
                                    ]; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$inv->status] ?? '' }}">{{ str_replace('_', ' ', $inv->status) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($inv->match_status === 'matched_clean')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">✓ clean</span>
                                    @elseif($inv->match_status === 'matched_variance')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">⚠ variance</span>
                                    @elseif($inv->match_status === 'pending')
                                        <span class="text-xs text-slate-400">pending</span>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('finance.vendor-invoices.show', $inv) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center px-4 py-8 text-gray-500">No vendor invoices.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $invoices->links() }}</div>
        </div>
    </div>
</x-app-layout>
