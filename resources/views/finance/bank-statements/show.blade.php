<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <a href="{{ route('finance.bank-statements.index') }}" class="text-xs text-slate-500 hover:underline"><i class="fa-solid fa-chevron-left mr-1"></i> All Statements</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1 font-mono">{{ $statement->statement_number }}</h1>
                <p class="text-sm text-slate-500">
                    {{ $statement->bankAccount?->displayName() }} ·
                    {{ \Carbon\Carbon::parse($statement->period_from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($statement->period_to)->format('d M Y') }} ·
                    {{ $statement->source_format }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-1 rounded-full
                    @switch($statement->status)
                        @case('imported') bg-blue-100 text-blue-700 @break
                        @case('partially_reconciled') bg-amber-100 text-amber-700 @break
                        @case('fully_reconciled') bg-emerald-100 text-emerald-700 @break
                        @case('void') bg-slate-200 text-slate-600 @break
                    @endswitch">
                    {{ str_replace('_', ' ', $statement->status) }}
                </span>
                @if ($statement->file_path)
                    <a href="{{ route('finance.bank-statements.download', $statement) }}" class="px-3 py-1.5 text-sm rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700"><i class="fa-solid fa-download mr-1"></i> CSV</a>
                @endif
                @if ($statement->canVoid())
                    <form method="POST" action="{{ route('finance.bank-statements.void', $statement) }}" class="inline" onsubmit="const r = prompt('Reason (≥5 chars):'); if (! r || r.length < 5) return false; this.querySelector('input[name=void_reason]').value = r; return true;">
                        @csrf
                        <input type="hidden" name="void_reason" value="">
                        <button class="px-3 py-1.5 text-sm rounded-lg bg-red-100 hover:bg-red-200 text-red-700">Void</button>
                    </form>
                @endif
                @if (! $statement->isVoid() && ! $statement->isFullyReconciled())
                    <form method="POST" action="{{ route('finance.bank-statements.match', $statement) }}" class="inline">
                        @csrf
                        <button class="px-3 py-1.5 text-sm rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white"><i class="fa-solid fa-bolt mr-1"></i> Run Match</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-3">
                <p class="text-xs text-slate-500 uppercase">Unmatched</p>
                <p class="text-xl font-bold text-blue-700 mt-1">{{ $counts['unmatched'] }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-3">
                <p class="text-xs text-slate-500 uppercase">Matched</p>
                <p class="text-xl font-bold text-emerald-700 mt-1">{{ $counts['matched'] + $counts['matched_to_journal'] }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-3">
                <p class="text-xs text-slate-500 uppercase">Resolved</p>
                <p class="text-xl font-bold text-slate-700 mt-1">{{ $counts['manually_resolved'] }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-3">
                <p class="text-xs text-slate-500 uppercase">Duplicate</p>
                <p class="text-xl font-bold text-amber-700 mt-1">{{ $counts['duplicate'] }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-3">
                <p class="text-xs text-slate-500 uppercase">Total Lines</p>
                <p class="text-xl font-bold text-slate-800 mt-1">{{ $statement->line_count }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase">Opening Balance per Bank</p>
                <p class="text-lg font-semibold text-slate-800 mt-1">{{ number_format((float) $statement->opening_balance_per_bank, 2) }} {{ $statement->currency_code }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase">Closing Balance per Bank</p>
                <p class="text-lg font-semibold text-slate-800 mt-1">{{ number_format((float) $statement->closing_balance_per_bank, 2) }} {{ $statement->currency_code }}</p>
            </div>
        </div>

        @if ($statement->reconciliation)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 mb-4">
                <i class="fa-solid fa-link mr-1"></i> Linked to reconciliation
                <a href="{{ route('finance.bank-reconciliation.show', $statement->reconciliation) }}" class="font-mono underline">{{ $statement->reconciliation->reconciliation_number }}</a>
                ({{ $statement->reconciliation->status }})
            </div>
        @endif

        @if ($statement->isVoid())
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 mb-4">
                <strong>VOIDED.</strong> Reason: {{ $statement->void_reason }}
                — by {{ $statement->voidedBy?->name ?? 'unknown' }} on {{ $statement->voided_at?->format('d M Y H:i') }}
            </div>
        @endif

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-left">Reference</th>
                        <th class="px-3 py-2 text-right">Debit</th>
                        <th class="px-3 py-2 text-right">Credit</th>
                        <th class="px-3 py-2 text-left">Match</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($lines as $line)
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 text-slate-500">{{ $line->line_number }}</td>
                        <td class="px-3 py-2">{{ \Carbon\Carbon::parse($line->value_date)->format('d M Y') }}</td>
                        <td class="px-3 py-2 text-slate-700 max-w-md truncate" title="{{ $line->description }}">{{ $line->description }}</td>
                        <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ $line->bank_reference ?? $line->counterparty_reference ?? '—' }}</td>
                        <td class="px-3 py-2 text-right text-red-700">{{ bccomp((string) $line->debit_amount, '0', 2) > 0 ? number_format((float) $line->debit_amount, 2) : '' }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">{{ bccomp((string) $line->credit_amount, '0', 2) > 0 ? number_format((float) $line->credit_amount, 2) : '' }}</td>
                        <td class="px-3 py-2">
                            @switch($line->match_status)
                                @case('unmatched') <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">unmatched</span> @break
                                @case('matched')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">{{ $line->matched_type }} #{{ $line->matched_id }}</span>
                                    @break
                                @case('matched_to_journal') <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">journal #{{ $line->matched_id }}</span> @break
                                @case('manually_resolved') <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-700" title="{{ $line->match_notes }}">resolved</span> @break
                                @case('duplicate')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">duplicate ({{ count($line->duplicate_candidate_ids ?? []) }})</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if ($line->isMatched())
                                <form method="POST" action="{{ route('finance.bank-statements.lines.unmatch', $line) }}" class="inline" onsubmit="return confirm('Unmatch?')">
                                    @csrf
                                    <button class="text-xs text-red-600 hover:underline">Unmatch</button>
                                </form>
                            @elseif ($line->isUnmatched() || $line->isDuplicate())
                                <button class="text-xs text-slate-600 hover:underline" onclick="document.getElementById('resolve-{{ $line->id }}').classList.toggle('hidden')">Resolve…</button>
                                <form id="resolve-{{ $line->id }}" method="POST" action="{{ route('finance.bank-statements.lines.resolve', $line) }}" class="hidden mt-2">
                                    @csrf
                                    <input name="note" required minlength="3" maxlength="500" placeholder="Why ignoring? (≥3 chars)" class="text-xs rounded border-slate-200 w-40">
                                    <button class="text-xs px-2 py-0.5 bg-slate-700 text-white rounded">Save</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
