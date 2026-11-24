<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @php
            $isEditable = $statement->canEdit();
            $counts = $statement->lineCountsByStatus();
            $totalLines = array_sum($counts);
            $unresolvedCount = $counts['unmatched'] + $counts['amount_mismatch']
                              + $counts['not_in_our_books'] + $counts['duplicate_match'];
            $diff = (float) $statement->balance_difference;
            $balancesAgree = abs($diff) < 0.01;
        @endphp

        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-2xl font-bold text-gray-800">{{ $statement->statement_number }}</h1>
                    @php
                        $statusBadge = match ($statement->status) {
                            'draft'       => 'bg-slate-200 text-slate-800',
                            'in_progress' => 'bg-amber-100 text-amber-800',
                            'reconciled'  => 'bg-emerald-100 text-emerald-800',
                            'flagged'     => 'bg-red-100 text-red-800',
                            'archived'    => 'bg-slate-300 text-slate-600',
                            default       => 'bg-slate-100 text-slate-700',
                        };
                    @endphp
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                        {{ strtoupper(str_replace('_', ' ', $statement->status)) }}
                    </span>
                </div>
                <p class="text-sm text-slate-600">
                    {{ $statement->counterparty->name }} <span class="text-slate-400">({{ $statement->counterparty->code }})</span>
                </p>
                <p class="text-xs text-slate-400 mt-1">
                    Period {{ $statement->period_from->format('Y-m-d') }} → {{ $statement->period_to->format('Y-m-d') }}
                    · Statement date {{ $statement->statement_date->format('Y-m-d') }}
                </p>
            </div>
            <a href="{{ route('finance.supplier-statements.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← Back to list</a>
        </div>

        {{-- Balance section --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Supplier says</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format((float) $statement->supplier_balance, 2) }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ $statement->currency_code }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Our books say</p>
                <p class="text-2xl font-bold text-slate-700 mt-1">{{ number_format((float) $statement->our_balance, 2) }}</p>
                <p class="text-xs text-slate-400 mt-1">As of {{ optional($statement->last_reconciled_at)?->format('Y-m-d H:i') ?: 'not yet computed' }}</p>
            </div>
            <div class="rounded-2xl border {{ $balancesAgree ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} shadow-sm p-4">
                <p class="text-xs uppercase tracking-wide font-semibold {{ $balancesAgree ? 'text-emerald-700' : 'text-amber-700' }}">Difference</p>
                <p class="text-2xl font-bold {{ $balancesAgree ? 'text-emerald-700' : 'text-amber-700' }} mt-1">{{ number_format($diff, 2) }}</p>
                <p class="text-xs {{ $balancesAgree ? 'text-emerald-600' : 'text-amber-600' }} mt-1">
                    @if($balancesAgree) Balances agree
                    @elseif($diff > 0) Supplier thinks we owe more
                    @else We think we owe more @endif
                </p>
            </div>
        </div>

        {{-- Match summary --}}
        @if($totalLines > 0)
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-4 text-xs">
                <div class="rounded-xl border border-slate-100 bg-white p-3 text-center">
                    <p class="text-slate-500">Unmatched</p>
                    <p class="font-bold text-slate-700 mt-1">{{ $counts['unmatched'] }}</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-center">
                    <p class="text-emerald-700">Matched</p>
                    <p class="font-bold text-emerald-800 mt-1">{{ $counts['matched'] }}</p>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-center">
                    <p class="text-amber-700">Amt mismatch</p>
                    <p class="font-bold text-amber-800 mt-1">{{ $counts['amount_mismatch'] }}</p>
                </div>
                <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-center">
                    <p class="text-red-700">Not on our books</p>
                    <p class="font-bold text-red-800 mt-1">{{ $counts['not_in_our_books'] }}</p>
                </div>
                <div class="rounded-xl border border-orange-100 bg-orange-50 p-3 text-center">
                    <p class="text-orange-700">Duplicate</p>
                    <p class="font-bold text-orange-800 mt-1">{{ $counts['duplicate_match'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-center">
                    <p class="text-slate-600">Manual OK</p>
                    <p class="font-bold text-slate-700 mt-1">{{ $counts['manually_resolved'] }}</p>
                </div>
            </div>
        @endif

        {{-- Action bar --}}
        @if($isEditable)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('finance.supplier-statements.reconcile', $statement) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl">
                        <i class="fa-solid fa-arrows-rotate mr-2"></i> Run reconciliation
                    </button>
                </form>

                @if($balancesAgree && $unresolvedCount === 0 && $totalLines > 0)
                    <form method="POST" action="{{ route('finance.supplier-statements.reconciled', $statement) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                            <i class="fa-solid fa-check mr-2"></i> Mark Reconciled
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('finance.supplier-statements.flag', $statement) }}"
                      onsubmit="this.querySelector('input[name=reason]').value = (prompt('Reason for flagging this statement?') || ''); return this.querySelector('input[name=reason]').value !== '';">
                    @csrf
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl">
                        <i class="fa-solid fa-flag mr-2"></i> Flag
                    </button>
                </form>

                <form method="POST" action="{{ route('finance.supplier-statements.archive', $statement) }}"
                      onsubmit="this.querySelector('input[name=reason]').value = (prompt('Archive reason?') || ''); return this.querySelector('input[name=reason]').value !== '' && confirm('Archive this statement? It will become read-only.');">
                    @csrf
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-medium rounded-xl">
                        <i class="fa-solid fa-box-archive mr-2"></i> Archive
                    </button>
                </form>
            </div>
        @endif

        {{-- Lines table --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm mb-4 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Statement Lines ({{ $totalLines }})</h2>
            </div>

            @if($statement->lines->isEmpty())
                <div class="p-6 text-center text-sm text-slate-500">
                    No lines yet. Add them below or upload a CSV.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Kind</th>
                                <th class="px-3 py-2 text-left">Reference</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Match / Note</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($statement->lines as $line)
                                @php
                                    $rowClass = match ($line->match_status) {
                                        'matched'           => 'bg-emerald-50/30',
                                        'amount_mismatch'   => 'bg-amber-50/30',
                                        'not_in_our_books'  => 'bg-red-50/30',
                                        'duplicate_match'   => 'bg-orange-50/30',
                                        'manually_resolved' => 'bg-slate-100/50',
                                        default             => '',
                                    };
                                    $statusBadge = match ($line->match_status) {
                                        'matched'           => 'bg-emerald-100 text-emerald-800',
                                        'amount_mismatch'   => 'bg-amber-100 text-amber-800',
                                        'not_in_our_books'  => 'bg-red-100 text-red-800',
                                        'duplicate_match'   => 'bg-orange-100 text-orange-800',
                                        'manually_resolved' => 'bg-slate-200 text-slate-700',
                                        default             => 'bg-slate-100 text-slate-600',
                                    };
                                    $matched = $line->matchedDocument();
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="px-3 py-2 text-xs text-slate-400">{{ $line->line_number }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $line->document_date->format('Y-m-d') }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-slate-100 text-slate-700 font-medium">{{ $line->document_kind }}</span>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $line->document_reference }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ number_format((float) $line->amount, 2) }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-xs {{ $statusBadge }}">
                                            {{ str_replace('_', ' ', $line->match_status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs">
                                        @if($matched)
                                            @if($line->matched_type === 'vendor_invoice')
                                                @if(\Illuminate\Support\Facades\Route::has('finance.vendor-invoices.show'))
                                                    <a href="{{ route('finance.vendor-invoices.show', $matched) }}" class="text-emerald-700 hover:underline">{{ $matched->vendor_invoice_number }}</a>
                                                @else
                                                    <span class="text-emerald-700">{{ $matched->vendor_invoice_number }}</span>
                                                @endif
                                                @if($line->amount_difference)
                                                    <div class="text-amber-700">Δ {{ number_format((float) $line->amount_difference, 2) }}</div>
                                                @endif
                                            @elseif($line->matched_type === 'payment')
                                                @canopen('finance.payments.show')
                                                    <a href="{{ route('finance.payments.show', $matched) }}" class="text-emerald-700 hover:underline">{{ $matched->payment_number }}</a>
                                                @else
                                                    <span class="text-emerald-700">{{ $matched->payment_number }}</span>
                                                @endcanopen
                                            @endif
                                        @elseif($line->isDuplicate() && $line->duplicate_candidate_ids)
                                            <span class="text-orange-700">{{ count($line->duplicate_candidate_ids) }} candidates: #{{ implode(', #', $line->duplicate_candidate_ids) }}</span>
                                        @endif
                                        @if($line->match_notes)
                                            <div class="text-slate-500 italic mt-1">{{ $line->match_notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if($isEditable && $line->isUnresolved())
                                            <details class="inline-block">
                                                <summary class="cursor-pointer text-xs text-slate-600 hover:text-slate-900">Resolve…</summary>
                                                <div class="absolute right-4 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg p-3 z-10 w-64">
                                                    <form method="POST" action="{{ route('finance.supplier-statements.lines.resolve', [$statement, $line]) }}">
                                                        @csrf
                                                        <textarea name="note" rows="2" required minlength="3" maxlength="1000"
                                                                  class="w-full rounded-lg border-slate-200 text-xs mb-2"
                                                                  placeholder="Why is this line OK as-is?"></textarea>
                                                        <button type="submit" class="w-full px-2 py-1 bg-slate-700 text-white text-xs rounded-lg">Mark resolved</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @endif
                                        @if($isEditable && ! $line->isMatched())
                                            <form method="POST" action="{{ route('finance.supplier-statements.lines.destroy', [$statement, $line]) }}"
                                                  onsubmit="return confirm('Remove this line?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:underline ml-2">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Add line form + CSV upload --}}
        @if($isEditable)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <form method="POST" action="{{ route('finance.supplier-statements.lines.store', $statement) }}"
                      class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Add Line Manually</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Date *</label>
                            <input type="date" name="document_date" required class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Kind *</label>
                            <select name="document_kind" required class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="invoice">invoice</option>
                                <option value="credit">credit</option>
                                <option value="payment">payment</option>
                                <option value="adjustment">adjustment</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Reference *</label>
                            <input type="text" name="document_reference" required maxlength="100" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Their invoice / cheque / transfer ref">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Amount *</label>
                            <input type="number" step="0.01" name="amount" required class="w-full rounded-lg border-slate-200 text-sm" placeholder="Negative for credits/refunds">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                            <input type="text" name="description" maxlength="255" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                    </div>
                    <button type="submit" class="mt-3 w-full px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl">Add Line</button>
                </form>

                <form method="POST" action="{{ route('finance.supplier-statements.lines.csv', $statement) }}"
                      enctype="multipart/form-data" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Upload CSV</h3>
                    <p class="text-xs text-slate-500 mb-3">
                        Required columns: <code class="bg-slate-100 px-1 rounded">document_date</code>,
                        <code class="bg-slate-100 px-1 rounded">document_kind</code>,
                        <code class="bg-slate-100 px-1 rounded">document_reference</code>,
                        <code class="bg-slate-100 px-1 rounded">amount</code>.
                        Optional: <code class="bg-slate-100 px-1 rounded">description</code>.
                        Atomic: any malformed row rejects the entire batch.
                    </p>
                    <input type="file" name="csv_file" accept=".csv,.txt" required class="block w-full text-sm text-slate-700 mb-3">
                    <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Upload &amp; Import</button>
                </form>
            </div>
        @endif

        {{-- Reverse-direction panel: our docs missing from their statement --}}
        @if(! empty($missingFromStatement))
            <div class="rounded-2xl border border-amber-200 bg-amber-50/40 shadow-sm p-4 mb-4">
                <h3 class="text-sm font-semibold text-amber-900 mb-2">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                    Our open vendor invoices in this period that DON'T appear on the supplier's statement ({{ count($missingFromStatement) }})
                </h3>
                <p class="text-xs text-amber-800 mb-3">
                    Either the supplier missed these (chase them), or you posted them after the statement was issued.
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-amber-100/50 text-xs uppercase text-amber-900">
                            <tr>
                                <th class="px-3 py-2 text-left">Our VI #</th>
                                <th class="px-3 py-2 text-left">Their Invoice #</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-right">Balance Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            @foreach($missingFromStatement as $vi)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">
                                        @if(\Illuminate\Support\Facades\Route::has('finance.vendor-invoices.show'))
                                            <a href="{{ route('finance.vendor-invoices.show', $vi) }}" class="text-emerald-700 hover:underline">{{ $vi->vendor_invoice_number }}</a>
                                        @else
                                            {{ $vi->vendor_invoice_number }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $vi->supplier_invoice_number ?: '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $vi->invoice_date?->format('Y-m-d') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $vi->total, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ number_format((float) $vi->balance_due, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Notes / audit footer --}}
        @if($statement->notes || $statement->reconciliation_notes || $statement->archive_reason)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 text-sm space-y-2">
                @if($statement->notes)
                    <div><span class="text-xs uppercase tracking-wide text-slate-500">Notes:</span> {{ $statement->notes }}</div>
                @endif
                @if($statement->reconciliation_notes)
                    <div><span class="text-xs uppercase tracking-wide text-slate-500">Reconciliation notes:</span> {{ $statement->reconciliation_notes }}</div>
                @endif
                @if($statement->archive_reason)
                    <div><span class="text-xs uppercase tracking-wide text-slate-500">Archive reason:</span> {{ $statement->archive_reason }}</div>
                @endif
            </div>
        @endif

        <div class="mt-4 text-xs text-slate-400">
            Created by {{ optional($statement->createdBy)->name ?: '—' }} on {{ $statement->created_at?->format('Y-m-d H:i') }}
            @if($statement->last_reconciled_at)
                · Last reconciled by {{ optional($statement->lastReconciledBy)->name ?: '—' }} at {{ $statement->last_reconciled_at->format('Y-m-d H:i') }}
            @endif
            @if($statement->archived_at)
                · Archived by {{ optional($statement->archivedBy)->name ?: '—' }} at {{ $statement->archived_at->format('Y-m-d H:i') }}
            @endif
        </div>
    </div>
</x-app-layout>
