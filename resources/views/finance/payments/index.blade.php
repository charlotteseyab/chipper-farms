<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Payments</h1>
                <p class="text-sm text-gray-500">Cash going out to suppliers. Posts Dr AP / Cr Cash; WHT slice journals come from allocations.</p>
            </div>
            @if(auth()->user()?->hasPermission('create_payment'))
                <div class="flex gap-2">
                    <a href="{{ route('finance.payment-runs.index') }}" class="inline-flex items-center px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">
                        <i class="fa-solid fa-money-check-dollar mr-2"></i>Payment Runs
                    </a>
                    <a href="{{ route('finance.payments.create') }}" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                        + New Payment
                    </a>
                </div>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total</p>
                <h3 class="mt-1 text-2xl font-bold text-slate-700">{{ $counts['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Posted</p>
                <h3 class="mt-1 text-2xl font-bold text-emerald-700">{{ $counts['posted'] }}</h3>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Unallocated</p>
                <h3 class="mt-1 text-2xl font-bold {{ $counts['unallocated'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $counts['unallocated'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Unreconciled</p>
                <h3 class="mt-1 text-2xl font-bold {{ $counts['unreconciled'] > 0 ? 'text-slate-700' : 'text-slate-400' }}">{{ $counts['unreconciled'] }}</h3>
            </div>
        </div>

        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="all">All</option>
                        <option value="posted" @selected($status === 'posted')>Posted</option>
                        <option value="unallocated" @selected($status === 'unallocated')>Unallocated (posted)</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                        <option value="reversed" @selected($status === 'reversed')>Reversed</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
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
                    <label class="block text-xs font-medium text-slate-600 mb-1">Reconciled</label>
                    <select name="reconciled" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="all">All</option>
                        <option value="yes" @selected($reconciled === 'yes')>Yes</option>
                        <option value="no" @selected($reconciled === 'no')>No</option>
                    </select>
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Filter</button>
                    <a href="{{ route('finance.payments.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Reset</a>
                </div>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">PMT #</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Supplier</th>
                            <th class="px-4 py-3 text-left">Method</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Allocated</th>
                            <th class="px-4 py-3 text-right">WHT</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Rec.</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($payments as $p)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs">
                                    <a href="{{ route('finance.payments.show', $p) }}" class="hover:underline">{{ $p->payment_number }}</a>
                                    @if($p->paymentRun)
                                        <div class="text-xs text-slate-400">via {{ $p->paymentRun->run_number }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $p->payment_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-slate-800">{{ $p->counterparty?->name }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $p->counterparty?->code }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ $p->paymentMethod?->name }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ $p->currency_code }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($p->isPosted())
                                        @if(bccomp((string) $p->allocated_amount, (string) $p->amount, 2) === 0)
                                            <span class="text-emerald-700 text-xs">full</span>
                                        @elseif(bccomp((string) $p->allocated_amount, '0', 2) > 0)
                                            <span class="text-amber-700">{{ number_format((float) $p->allocated_amount, 2) }}</span>
                                        @else
                                            <span class="text-red-600 text-xs">unallocated</span>
                                        @endif
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    @if(bccomp((string) $p->withholding_amount, '0', 2) > 0)
                                        {{ number_format((float) $p->withholding_amount, 2) }}
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php $statusClasses = [
                                        'draft'     => 'bg-slate-100 text-slate-700',
                                        'posted'    => 'bg-emerald-100 text-emerald-800',
                                        'cancelled' => 'bg-slate-100 text-slate-500',
                                        'reversed'  => 'bg-rose-100 text-rose-800',
                                    ]; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$p->status] ?? '' }}">{{ $p->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($p->isReconciled())
                                        <i class="fa-solid fa-check-circle text-emerald-600" title="Reconciled {{ $p->reconciled_at?->format('Y-m-d') }}"></i>
                                    @elseif($p->isPosted())
                                        <i class="fa-regular fa-circle text-slate-300" title="Not reconciled"></i>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('finance.payments.show', $p) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center px-4 py-8 text-gray-500">No payments.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $payments->links() }}</div>
        </div>
    </div>
</x-app-layout>
