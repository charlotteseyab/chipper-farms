<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Bank Reconciliations</h1>
                <p class="text-sm text-slate-500">Period-close reconciliation per bank account.</p>
            </div>
            <a href="{{ route('finance.bank-reconciliation.create') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                <i class="fa-solid fa-plus mr-2"></i> New Reconciliation
            </a>
        </div>

        @if (session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Bank Account</label>
                    <select name="bank_account_id" class="rounded-lg border-slate-200 text-sm">
                        <option value="">All accounts</option>
                        @foreach ($bankAccounts as $ba)
                            <option value="{{ $ba->id }}" {{ (string) $bankAccountId === (string) $ba->id ? 'selected' : '' }}>{{ $ba->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="rounded-lg border-slate-200 text-sm">
                        <option value="open"   {{ $statusFilter === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="closed" {{ $statusFilter === 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="all"    {{ $statusFilter === 'all' ? 'selected' : '' }}>All</option>
                        <option value="archived" {{ $statusFilter === 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>
                <button class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-800">Filter</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Number</th>
                        <th class="px-4 py-3 text-left">Bank Account</th>
                        <th class="px-4 py-3 text-left">Period</th>
                        <th class="px-4 py-3 text-right">Bank Closing</th>
                        <th class="px-4 py-3 text-right">Diff</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($recons as $rec)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2"><a href="{{ route('finance.bank-reconciliation.show', $rec) }}" class="text-emerald-700 hover:underline font-mono">{{ $rec->reconciliation_number }}</a></td>
                        <td class="px-4 py-2">{{ $rec->bankAccount?->displayName() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ \Carbon\Carbon::parse($rec->period_from)->format('d M') }} – {{ \Carbon\Carbon::parse($rec->period_to)->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $rec->closing_balance_per_bank, 2) }}</td>
                        <td class="px-4 py-2 text-right
                            @if (bccomp((string) $rec->reconciliation_difference, '0', 2) === 0) text-emerald-700
                            @else text-red-700
                            @endif">
                            {{ number_format((float) $rec->reconciliation_difference, 2) }}
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                @switch($rec->status)
                                    @case('draft') bg-slate-200 text-slate-700 @break
                                    @case('in_progress') bg-blue-100 text-blue-700 @break
                                    @case('reconciled') bg-emerald-100 text-emerald-700 @break
                                    @case('flagged') bg-amber-100 text-amber-800 @break
                                    @case('archived') bg-slate-100 text-slate-500 @break
                                @endswitch">{{ str_replace('_', ' ', $rec->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No reconciliations match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $recons->links() }}</div>
    </div>
</x-app-layout>
