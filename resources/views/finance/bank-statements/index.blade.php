<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Bank Statements</h1>
                <p class="text-sm text-slate-500">CSV statements imported from each bank.</p>
            </div>
            <a href="{{ route('finance.bank-statements.create') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                <i class="fa-solid fa-upload mr-2"></i> Import Statement
            </a>
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
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All</option>
                        @foreach ($allStatuses as $st)
                            <option value="{{ $st }}" {{ $statusFilter === $st ? 'selected' : '' }}>{{ str_replace('_', ' ', $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Period (YYYY-MM)</label>
                    <input name="period" value="{{ $periodFilter }}" placeholder="2027-01" class="rounded-lg border-slate-200 text-sm">
                </div>
                <button class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-800">Filter</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Statement #</th>
                        <th class="px-4 py-3 text-left">Bank Account</th>
                        <th class="px-4 py-3 text-left">Period</th>
                        <th class="px-4 py-3 text-right">Lines</th>
                        <th class="px-4 py-3 text-right">Credits</th>
                        <th class="px-4 py-3 text-right">Debits</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($statements as $stmt)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2"><a href="{{ route('finance.bank-statements.show', $stmt) }}" class="text-emerald-700 hover:underline font-mono">{{ $stmt->statement_number }}</a></td>
                        <td class="px-4 py-2">{{ $stmt->bankAccount?->displayName() ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ \Carbon\Carbon::parse($stmt->period_from)->format('d M') }} – {{ \Carbon\Carbon::parse($stmt->period_to)->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-right">{{ $stmt->line_count }}</td>
                        <td class="px-4 py-2 text-right text-emerald-700">{{ number_format((float) $stmt->total_credits, 2) }}</td>
                        <td class="px-4 py-2 text-right text-red-700">{{ number_format((float) $stmt->total_debits, 2) }}</td>
                        <td class="px-4 py-2 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                @switch($stmt->status)
                                    @case('imported') bg-blue-100 text-blue-700 @break
                                    @case('partially_reconciled') bg-amber-100 text-amber-700 @break
                                    @case('fully_reconciled') bg-emerald-100 text-emerald-700 @break
                                    @case('void') bg-slate-200 text-slate-600 @break
                                @endswitch">
                                {{ str_replace('_', ' ', $stmt->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No statements.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $statements->links() }}</div>
    </div>
</x-app-layout>
