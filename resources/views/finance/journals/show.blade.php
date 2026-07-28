<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.journals.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Batches</a>
        </div>

        {{-- Batch header --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">{{ str_replace('_', ' ', $batch->batch_type) }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800">{{ $batch->batch_number }}</h1>
                    <p class="text-sm text-slate-600 mt-1">{{ $batch->description ?: 'No description' }}</p>

                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-gray-500">Status</p>
                            @php
                                $statusClasses = [
                                    'draft' => 'bg-amber-100 text-amber-800',
                                    'posted' => 'bg-emerald-100 text-emerald-800',
                                    'reversed' => 'bg-slate-100 text-slate-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$batch->status] }}">{{ $batch->status }}</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Posting Date</p>
                            <p class="font-medium">{{ $batch->posting_date?->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Period</p>
                            <p class="font-medium">{{ $batch->fiscalPeriod?->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total</p>
                            <p class="font-medium">GHS {{ number_format((float) $batch->journals->sum('total_debit'), 2) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-600">
                        @if($batch->proposedBy)
                            <div>
                                <span class="text-gray-500">Proposed by:</span>
                                <span class="font-medium">{{ $batch->proposedBy->name }}</span>
                                <span class="text-gray-400">— {{ $batch->proposed_at?->format('Y-m-d H:i') }}</span>
                            </div>
                        @endif
                        @if($batch->postedBy)
                            <div>
                                <span class="text-gray-500">Posted by:</span>
                                <span class="font-medium">{{ $batch->postedBy->name }}</span>
                                <span class="text-gray-400">— {{ $batch->posted_at?->format('Y-m-d H:i') }}</span>
                            </div>
                        @endif
                        @if($batch->reversedByUser)
                            <div>
                                <span class="text-gray-500">Reversed by:</span>
                                <span class="font-medium">{{ $batch->reversedByUser->name }}</span>
                                <span class="text-gray-400">— {{ $batch->reversed_at?->format('Y-m-d H:i') }}</span>
                            </div>
                        @endif
                    </div>

                    @if($batch->reverses)
                        <p class="mt-3 text-sm text-slate-600">
                            ↩ Reverses: <a href="{{ route('finance.journals.show', $batch->reverses) }}" class="text-emerald-700 hover:underline font-mono">{{ $batch->reverses->batch_number }}</a>
                        </p>
                    @endif
                    @if($batch->reversedBy)
                        <p class="mt-2 text-sm text-amber-700">
                            Warning Reversed by: <a href="{{ route('finance.journals.show', $batch->reversedBy) }}" class="hover:underline font-mono">{{ $batch->reversedBy->batch_number }}</a>
                            @if($batch->reversal_reason)
                                <span class="block text-xs text-slate-500">Reason: {{ $batch->reversal_reason }}</span>
                            @endif
                        </p>
                    @endif
                </div>

                {{-- Action panel --}}
                <div class="flex flex-col gap-2 min-w-[180px]">
                    @if($batch->status === 'draft')
                        @if(auth()->user()?->hasPermission('approve_journal') && $batch->proposed_by_user_id !== auth()->id())
                            <form method="POST" action="{{ route('finance.journals.approve', $batch) }}">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Approve & Post</button>
                            </form>
                            <button type="button" onclick="document.getElementById('reject-form').classList.toggle('hidden')"
                                    class="w-full px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium rounded-xl">Reject</button>
                        @endif
                        @if(auth()->user()?->hasPermission('approve_journal') && $batch->proposed_by_user_id === auth()->id())
                            <p class="text-xs text-amber-700 italic px-2">You proposed this batch — another user must approve.</p>
                        @endif
                    @endif

                    @if($batch->status === 'posted' && auth()->user()?->hasPermission('reverse_journal') && ! $batch->reversed_by_batch_id)
                        <button type="button" onclick="document.getElementById('reverse-form').classList.toggle('hidden')"
                                class="w-full px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-800 text-sm font-medium rounded-xl">Reverse</button>
                    @endif
                </div>
            </div>

            {{-- Reject form (hidden by default) --}}
            @if($batch->status === 'draft' && auth()->user()?->hasPermission('approve_journal'))
                <form id="reject-form" method="POST" action="{{ route('finance.journals.reject', $batch) }}" class="mt-4 hidden">
                    @csrf
                    <label class="block text-sm font-medium text-slate-700 mb-1">Rejection Reason</label>
                    <textarea name="reason" required rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Why is this being rejected?"></textarea>
                    <button type="submit" class="mt-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl">Confirm Rejection</button>
                </form>
            @endif

            {{-- Reverse form (hidden by default) --}}
            @if($batch->status === 'posted' && auth()->user()?->hasPermission('reverse_journal'))
                <form id="reverse-form" method="POST" action="{{ route('finance.journals.reverse', $batch) }}" class="mt-4 hidden">
                    @csrf
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason for Reversal</label>
                    <textarea name="reason" required rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Why is this being reversed?"></textarea>
                    <label class="block text-sm font-medium text-slate-700 mt-3 mb-1">Reversal Date (optional)</label>
                    <input type="date" name="reversal_date" class="rounded-xl border-slate-200 text-sm">
                    <button type="submit" class="mt-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl">Confirm Reversal</button>
                </form>
            @endif
        </div>

        {{-- Journals + lines --}}
        @foreach($batch->journals as $journal)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">{{ str_replace('_', ' ', $journal->journal_type) }}</p>
                        <h3 class="font-mono text-sm text-slate-700">{{ $journal->journal_number }}</h3>
                    </div>
                    <div class="text-right text-sm">
                        <p class="text-xs text-gray-500">Currency</p>
                        <p class="font-medium">{{ $journal->currency_code }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Account</th>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-left">Counterparty</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($journal->lines as $line)
                                <tr>
                                    <td class="px-4 py-3 text-xs text-slate-400">{{ $line->line_number }}</td>
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-xs text-slate-500">{{ $line->account?->code }}</span>
                                        <span class="text-slate-700 ml-1">{{ $line->account?->name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $line->description ?: '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $line->counterparty?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium">
                                        {{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium">
                                        {{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '' }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50 font-semibold">
                                <td colspan="4" class="px-4 py-3 text-right text-slate-600 text-sm">Totals</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $journal->total_debit, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $journal->total_credit, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
