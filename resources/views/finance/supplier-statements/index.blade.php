<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Supplier Statements</h1>
                <p class="text-sm text-slate-500">Reconcile supplier-issued statements against our AP detail.</p>
            </div>
            <a href="{{ route('finance.supplier-statements.create') }}"
               class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                <i class="fa-solid fa-plus mr-2"></i> New Statement
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total</p>
                <p class="text-xl font-bold text-slate-800 mt-1">{{ $counts['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Draft</p>
                <p class="text-xl font-bold text-slate-700 mt-1">{{ $counts['draft'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">In Progress</p>
                <p class="text-xl font-bold text-amber-700 mt-1">{{ $counts['in_progress'] }}</p>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50 shadow-sm p-4">
                <p class="text-xs text-red-700 uppercase tracking-wide font-semibold">Flagged</p>
                <p class="text-xl font-bold text-red-700 mt-1">{{ $counts['flagged'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 shadow-sm p-4">
                <p class="text-xs text-emerald-700 uppercase tracking-wide font-semibold">Reconciled</p>
                <p class="text-xl font-bold text-emerald-700 mt-1">{{ $counts['reconciled'] }}</p>
            </div>
        </div>

        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="all" @selected($status === 'all')>All</option>
                        <option value="open" @selected($status === 'open')>Open (draft / in progress / flagged)</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="in_progress" @selected($status === 'in_progress')>In Progress</option>
                        <option value="flagged" @selected($status === 'flagged')>Flagged</option>
                        <option value="reconciled" @selected($status === 'reconciled')>Reconciled</option>
                        <option value="archived" @selected($status === 'archived')>Archived</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
                    <select name="supplier_id" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">All suppliers</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected((int) $supplierId === $s->id)>{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Filter</button>
                    <a href="{{ route('finance.supplier-statements.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Reset</a>
                </div>
            </div>
        </form>

        @if($statements->isEmpty())
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-8 text-center">
                <p class="text-slate-600">No statements yet.</p>
                <p class="text-xs text-slate-400 mt-1">Create one when a supplier sends you a statement to reconcile.</p>
            </div>
        @else
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Number</th>
                                <th class="px-4 py-3 text-left">Supplier</th>
                                <th class="px-4 py-3 text-left">Period</th>
                                <th class="px-4 py-3 text-right">Supplier Bal.</th>
                                <th class="px-4 py-3 text-right">Our Bal.</th>
                                <th class="px-4 py-3 text-right">Diff.</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($statements as $s)
                                @php
                                    $diff = (float) $s->balance_difference;
                                    $diffClass = abs($diff) < 0.01 ? 'text-emerald-700'
                                                  : ($diff > 0 ? 'text-red-700' : 'text-amber-700');
                                    $statusBadge = match ($s->status) {
                                        'draft'       => 'bg-slate-100 text-slate-700',
                                        'in_progress' => 'bg-amber-100 text-amber-800',
                                        'reconciled'  => 'bg-emerald-100 text-emerald-800',
                                        'flagged'     => 'bg-red-100 text-red-800',
                                        'archived'    => 'bg-slate-200 text-slate-500',
                                        default       => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $s->statement_number }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-800">{{ $s->counterparty->name }}</div>
                                        <div class="text-xs text-slate-400 font-mono">{{ $s->counterparty->code }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <div>{{ $s->period_from->format('Y-m-d') }}</div>
                                        <div class="text-slate-500">to {{ $s->period_to->format('Y-m-d') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $s->supplier_balance, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600">{{ number_format((float) $s->our_balance, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $diffClass }}">{{ number_format($diff, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                            {{ ucfirst(str_replace('_', ' ', $s->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('finance.supplier-statements.show', $s) }}" class="text-emerald-700 hover:underline text-sm">View →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $statements->links() }}</div>
        @endif
    </div>
</x-app-layout>
