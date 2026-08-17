<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <a href="{{ route('finance.bank-accounts.index') }}" class="text-xs text-slate-500 hover:underline"><i class="fa-solid fa-chevron-left mr-1"></i> All Money Accounts</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $bankAccount->displayName() }}</h1>
                <p class="text-sm text-slate-500">{{ $bankAccount->account?->code }} — {{ $bankAccount->account?->name }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($bankAccount->is_active)
                    <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Active</span>
                @else
                    <span class="px-2 py-1 text-xs rounded-full bg-slate-200 text-slate-600">Inactive</span>
                @endif
                @if (auth()->user()?->hasPermission('manage_bank_accounts'))
                    <a href="{{ route('finance.bank-accounts.edit', $bankAccount) }}" class="px-3 py-1.5 text-sm rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Edit</a>
                    @if ($bankAccount->is_active)
                        <form method="POST" action="{{ route('finance.bank-accounts.deactivate', $bankAccount) }}" onsubmit="return confirm('Deactivate this money account?')" class="inline">
                            @csrf
                            <button class="px-3 py-1.5 text-sm rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800">Deactivate</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('finance.bank-accounts.activate', $bankAccount) }}" class="inline">
                            @csrf
                            <button class="px-3 py-1.5 text-sm rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-800">Activate</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Currency</div>
                <div class="text-lg font-semibold text-slate-800 mt-1">{{ $bankAccount->currency_code }}</div>
            </div>
            <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Opening Balance</div>
                <div class="text-lg font-semibold text-slate-800 mt-1">{{ number_format((float) $bankAccount->opening_balance, 2) }}</div>
                <div class="text-xs text-slate-400">as of {{ \Carbon\Carbon::parse($bankAccount->opening_balance_date)->format('d M Y') }}</div>
            </div>
            <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Last Reconciled</div>
                @if ($bankAccount->last_reconciled_date)
                    <div class="text-lg font-semibold text-slate-800 mt-1">{{ number_format((float) $bankAccount->last_reconciled_balance, 2) }}</div>
                    <div class="text-xs text-slate-400">as of {{ \Carbon\Carbon::parse($bankAccount->last_reconciled_date)->format('d M Y') }}</div>
                @else
                    <div class="text-sm text-slate-400 mt-2">Never reconciled</div>
                @endif
            </div>
        </div>

        <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-5 mb-6">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">Money Account Details</h2>
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-xs text-slate-500">Type</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->kindLabel() }}</dd></div>
                <div><dt class="text-xs text-slate-500">Provider / Bank</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->provider ?: $bankAccount->bank_name ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Account / Wallet #</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->referenceLabel() ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Account name</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->account_name ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">SWIFT</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->swift_code ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Branch</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->branch ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Storefront</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->storefront_payment_method ? str_replace('_', ' ', $bankAccount->storefront_payment_method) : '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Default CSV format</dt><dd class="text-slate-800 mt-0.5">{{ $bankAccount->default_csv_format ?: 'auto-detect' }}</dd></div>
            </dl>
            @if ($bankAccount->notes)
                <p class="mt-4 text-xs text-slate-500 whitespace-pre-line">{{ $bankAccount->notes }}</p>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-700">Recent Statements</h2>
                    @if (auth()->user()?->hasPermission('import_bank_statement'))
                        <a href="{{ route('finance.bank-statements.create', ['bank_account_id' => $bankAccount->id]) }}" class="text-xs text-emerald-700 hover:underline">+ Import</a>
                    @endif
                </div>
                @forelse ($recentStatements as $stmt)
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-b-0">
                        <div>
                            <a href="{{ route('finance.bank-statements.show', $stmt) }}" class="text-sm text-emerald-700 hover:underline">{{ $stmt->statement_number }}</a>
                            <p class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($stmt->period_from)->format('d M') }} – {{ \Carbon\Carbon::parse($stmt->period_to)->format('d M Y') }} · {{ $stmt->line_count }} lines</p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            @switch($stmt->status)
                                @case('imported') bg-blue-100 text-blue-700 @break
                                @case('partially_reconciled') bg-amber-100 text-amber-700 @break
                                @case('fully_reconciled') bg-emerald-100 text-emerald-700 @break
                                @case('void') bg-slate-200 text-slate-600 @break
                            @endswitch">
                            {{ str_replace('_', ' ', $stmt->status) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No statements imported yet.</p>
                @endforelse
            </div>

            <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-700">Recent Reconciliations</h2>
                    @if (auth()->user()?->hasPermission('reconcile_bank_statement'))
                        <a href="{{ route('finance.bank-reconciliation.create', ['bank_account_id' => $bankAccount->id]) }}" class="text-xs text-emerald-700 hover:underline">+ New</a>
                    @endif
                </div>
                @forelse ($recentRecons as $rec)
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-b-0">
                        <div>
                            <a href="{{ route('finance.bank-reconciliation.show', $rec) }}" class="text-sm text-emerald-700 hover:underline">{{ $rec->reconciliation_number }}</a>
                            <p class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($rec->period_from)->format('d M') }} – {{ \Carbon\Carbon::parse($rec->period_to)->format('d M Y') }}</p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            @switch($rec->status)
                                @case('draft') bg-slate-200 text-slate-700 @break
                                @case('in_progress') bg-blue-100 text-blue-700 @break
                                @case('reconciled') bg-emerald-100 text-emerald-700 @break
                                @case('flagged') bg-amber-100 text-amber-800 @break
                                @case('archived') bg-slate-100 text-slate-500 @break
                            @endswitch">
                            {{ str_replace('_', ' ', $rec->status) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No reconciliations yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
