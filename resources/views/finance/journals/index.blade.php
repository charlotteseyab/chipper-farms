<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Journal Batches</h1>
                <p class="text-sm text-gray-500">All journal entries — drafts, posted, and reversals.</p>
            </div>
            @if(auth()->user()?->hasPermission('propose_manual_journal'))
                <a href="{{ route('finance.journals.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    + New Journal Entry
                </a>
            @endif
        </div>


        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('finance.journals.index') }}"
               class="px-4 py-1.5 text-sm rounded-full {{ $status === 'all' ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                All
            </a>
            <a href="{{ route('finance.journals.index', ['status' => 'draft']) }}"
               class="px-4 py-1.5 text-sm rounded-full {{ $status === 'draft' ? 'bg-amber-600 text-white' : 'bg-white border border-amber-200 text-amber-700 hover:bg-amber-50' }}">
                Drafts ({{ $counts['draft'] }})
            </a>
            <a href="{{ route('finance.journals.index', ['status' => 'posted']) }}"
               class="px-4 py-1.5 text-sm rounded-full {{ $status === 'posted' ? 'bg-emerald-600 text-white' : 'bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                Posted ({{ $counts['posted'] }})
            </a>
            <a href="{{ route('finance.journals.index', ['status' => 'reversed']) }}"
               class="px-4 py-1.5 text-sm rounded-full {{ $status === 'reversed' ? 'bg-slate-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                Reversed ({{ $counts['reversed'] }})
            </a>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            @if($batches->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">No batches found for the selected filter.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Batch #</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Posting Date</th>
                                <th class="px-4 py-3 text-left">Period</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Proposed By</th>
                                <th class="px-4 py-3 text-left">Posted By</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($batches as $b)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $b->batch_number }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ str_replace('_', ' ', $b->batch_type) }}</td>
                                    <td class="px-4 py-3">{{ $b->posting_date?->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ $b->fiscalPeriod?->name }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusClasses = [
                                                'draft' => 'bg-amber-100 text-amber-800',
                                                'posted' => 'bg-emerald-100 text-emerald-800',
                                                'reversed' => 'bg-slate-100 text-slate-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$b->status] }}">
                                            {{ $b->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ $b->proposedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $b->postedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">GHS {{ number_format((float) $b->journals->sum('total_debit'), 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('finance.journals.show', $b) }}" class="text-emerald-700 hover:underline text-xs font-medium">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                    {{ $batches->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
