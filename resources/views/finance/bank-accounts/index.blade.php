<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Cash, Bank & MoMo Accounts</h1>
                <p class="text-sm text-slate-500">Practical money accounts used by payments, receipts, expenses, purchases, loans, and storefront checkout.</p>
            </div>
            @if (auth()->user()?->hasPermission('manage_bank_accounts'))
            <a href="{{ route('finance.bank-accounts.create') }}"
               class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                <i class="fa-solid fa-plus mr-2"></i> New Money Account
            </a>
            @endif
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="rounded-lg border-slate-200 text-sm">
                        <option value="active"   {{ $statusFilter === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="all"      {{ $statusFilter === 'all' ? 'selected' : '' }}>All</option>
                    </select>
                </div>
                <button class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-800">Filter</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Label</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">GL Account</th>
                        <th class="px-4 py-3 text-left">Currency</th>
                        <th class="px-4 py-3 text-right">Last Reconciled</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($bankAccounts as $ba)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2"><a href="{{ route('finance.bank-accounts.show', $ba) }}" class="text-emerald-700 hover:underline">{{ $ba->label }}</a></td>
                        <td class="px-4 py-2">{{ $ba->kindLabel() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $ba->referenceLabel() ?: '—' }}</td>
                        <td class="px-4 py-2"><span class="text-xs text-slate-500">{{ $ba->account?->code }}</span> {{ $ba->account?->name }}</td>
                        <td class="px-4 py-2">{{ $ba->currency_code }}</td>
                        <td class="px-4 py-2 text-right">
                            @if ($ba->last_reconciled_date)
                                <div class="text-slate-700">{{ number_format((float) $ba->last_reconciled_balance, 2) }}</div>
                                <div class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($ba->last_reconciled_date)->format('d M Y') }}</div>
                            @else
                                <span class="text-xs text-slate-400">never</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($ba->is_active)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">Active</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-slate-200 text-slate-600">Inactive</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No money accounts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $bankAccounts->links() }}</div>
    </div>
</x-app-layout>
