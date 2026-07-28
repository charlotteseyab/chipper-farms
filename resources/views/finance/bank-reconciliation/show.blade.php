<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <a href="{{ route('finance.bank-reconciliation.index') }}" class="text-xs text-slate-500 hover:underline"><i class="fa-solid fa-chevron-left mr-1"></i> All Reconciliations</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1 font-mono">{{ $rec->reconciliation_number }}</h1>
                <p class="text-sm text-slate-500">
                    {{ $rec->bankAccount?->displayName() }} ·
                    {{ \Carbon\Carbon::parse($rec->period_from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($rec->period_to)->format('d M Y') }} ·
                    {{ $rec->currency_code }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-1 rounded-full
                    @switch($rec->status)
                        @case('draft') bg-slate-200 text-slate-700 @break
                        @case('in_progress') bg-blue-100 text-blue-700 @break
                        @case('reconciled') bg-emerald-100 text-emerald-700 @break
                        @case('flagged') bg-amber-100 text-amber-800 @break
                        @case('archived') bg-slate-100 text-slate-500 @break
                    @endswitch">{{ str_replace('_', ' ', $rec->status) }}</span>

                @if ($rec->canEdit())
                    <form method="POST" action="{{ route('finance.bank-reconciliation.refresh', $rec) }}" class="inline">
                        @csrf
                        <button class="px-3 py-1.5 text-sm rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700"><i class="fa-solid fa-rotate mr-1"></i> Refresh</button>
                    </form>
                    @if ($rec->balancesAgree())
                        <form method="POST" action="{{ route('finance.bank-reconciliation.reconcile', $rec) }}" class="inline" onsubmit="return confirm('Close this reconciliation? This locks contributing payments and receipts from reversal.')">
                            @csrf
                            <button class="px-3 py-1.5 text-sm rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white"><i class="fa-solid fa-check mr-1"></i> Reconcile</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('finance.bank-reconciliation.flag', $rec) }}" class="inline" onsubmit="const r = prompt('Flag reason (≥5 chars):'); if (! r || r.length < 5) return false; this.querySelector('input[name=flag_reason]').value = r; return confirm('Flag this reconciliation? It will lock just like reconciled.');">
                        @csrf
                        <input type="hidden" name="flag_reason" value="">
                        <button class="px-3 py-1.5 text-sm rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800"><i class="fa-solid fa-flag mr-1"></i> Flag</button>
                    </form>
                @elseif (in_array($rec->status, ['reconciled', 'flagged'], true))
                    <form method="POST" action="{{ route('finance.bank-reconciliation.reopen', $rec) }}" class="inline" onsubmit="const r = prompt('Reopen reason (≥5 chars):'); if (! r || r.length < 5) return false; this.querySelector('input[name=reopen_reason]').value = r; return true;">
                        @csrf
                        <input type="hidden" name="reopen_reason" value="">
                        <button class="px-3 py-1.5 text-sm rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-700"><i class="fa-solid fa-lock-open mr-1"></i> Reopen</button>
                    </form>
                    <form method="POST" action="{{ route('finance.bank-reconciliation.archive', $rec) }}" class="inline" onsubmit="const r = prompt('Archive reason:'); if (! r) return false; this.querySelector('input[name=archive_reason]').value = r; return true;">
                        @csrf
                        <input type="hidden" name="archive_reason" value="">
                        <button class="px-3 py-1.5 text-sm rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700"><i class="fa-solid fa-box-archive mr-1"></i> Archive</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif

        @if ($rec->isFlagged() && $rec->flag_reason)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 mb-4">
                <strong>Flagged:</strong> {{ $rec->flag_reason }}
                — by {{ $rec->flaggedBy?->name }} on {{ $rec->flagged_at?->format('d M Y H:i') }}
            </div>
        @endif

        @if ($rec->isReconciled())
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 mb-4">
                <i class="fa-solid fa-lock mr-1"></i> Reconciled and locked.
                Contributing payments and receipts cannot be reversed without reopening.
                Reconciled by {{ $rec->reconciledBy?->name }} on {{ $rec->reconciled_at?->format('d M Y H:i') }}.
            </div>
        @endif

        {{-- Arithmetic panel --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5 mb-6">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Reconciliation Arithmetic</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-xs text-slate-500 uppercase">Opening per Bank</p>
                    <p class="text-lg font-semibold text-slate-800">{{ number_format((float) $rec->opening_balance_per_bank, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase">Closing per Bank</p>
                    <p class="text-lg font-semibold text-slate-800">{{ number_format((float) $rec->closing_balance_per_bank, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase">Closing per Books (GL)</p>
                    <p class="text-lg font-semibold text-slate-800">{{ number_format((float) $rec->closing_balance_per_books, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase">+ Outstanding Withdrawals</p>
                    <p class="text-lg font-semibold text-amber-700">{{ number_format((float) $rec->outstanding_withdrawals, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase">– Outstanding Deposits</p>
                    <p class="text-lg font-semibold text-amber-700">{{ number_format((float) $rec->outstanding_deposits, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase">Bank-Only Cr / Dr</p>
                    <p class="text-base font-semibold text-slate-700">+{{ number_format((float) $rec->bank_only_debits, 2) }} / -{{ number_format((float) $rec->bank_only_credits, 2) }}</p>
                </div>
            </div>
            <hr class="my-4 border-slate-200">
            <div class="text-right">
                <p class="text-xs text-slate-500 uppercase">Difference</p>
                <p class="text-3xl font-extrabold
                    @if (bccomp((string) $rec->reconciliation_difference, '0', 2) === 0) text-emerald-700
                    @else text-red-700
                    @endif">
                    {{ number_format((float) $rec->reconciliation_difference, 2) }}
                </p>
                @if (bccomp((string) $rec->reconciliation_difference, '0', 2) === 0)
                    <p class="text-xs text-emerald-700 mt-1">Balances agree. Ready to reconcile.</p>
                @else
                    <p class="text-xs text-red-700 mt-1">Match more lines, or close with a Flag if you have an unresolvable known cause.</p>
                @endif
            </div>
        </div>

        {{-- Contributing lines panel --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Statement Lines in Period</h2>
                <p class="text-xs text-slate-500">{{ $allLines->count() }} lines across non-void statements</p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Description</th>
                        <th class="px-4 py-2 text-right">Debit</th>
                        <th class="px-4 py-2 text-right">Credit</th>
                        <th class="px-4 py-2 text-left">Match</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($allLines as $line)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-1.5">{{ \Carbon\Carbon::parse($line->value_date)->format('d M') }}</td>
                        <td class="px-4 py-1.5 text-slate-700 max-w-md truncate" title="{{ $line->description }}">{{ $line->description }}</td>
                        <td class="px-4 py-1.5 text-right text-red-700">{{ bccomp((string) $line->debit_amount, '0', 2) > 0 ? number_format((float) $line->debit_amount, 2) : '' }}</td>
                        <td class="px-4 py-1.5 text-right text-emerald-700">{{ bccomp((string) $line->credit_amount, '0', 2) > 0 ? number_format((float) $line->credit_amount, 2) : '' }}</td>
                        <td class="px-4 py-1.5">
                            @switch($line->match_status)
                                @case('unmatched') <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">unmatched</span> @break
                                @case('matched') <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">{{ $line->matched_type }}</span> @break
                                @case('matched_to_journal') <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">journal</span> @break
                                @case('manually_resolved') <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">resolved</span> @break
                                @case('duplicate') <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">duplicate</span> @break
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No statement lines for this period yet. Import a statement first.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($rec->isReconciled() || $rec->isFlagged())
            <div class="mt-6 rounded-2xl bg-white border border-slate-100 shadow-sm p-5">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Frozen Snapshot</h2>
                <p class="text-xs text-slate-500 mb-3">Captured at close. This is the audit trail of what contributed to the reconciliation when it was finalised.</p>
                <pre class="text-xs bg-slate-50 p-3 rounded-lg overflow-auto max-h-96">{{ json_encode($rec->summary_snapshot, JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif
    </div>
</x-app-layout>
