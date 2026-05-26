<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Finance Dashboard</h1>
                <p class="text-sm text-gray-500">Live snapshot of the general ledger and posting activity.</p>
            </div>
            <div class="flex items-center gap-2">
                @if(auth()->user()?->hasPermission('propose_manual_journal'))
                    <a href="{{ route('finance.journals.create') }}"
                       class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                        + New Journal Entry
                    </a>
                @endif
                <a href="{{ route('finance.trial-balance') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl shadow-sm transition">
                    Trial Balance
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Period banner --}}
        <div class="mb-6 rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Current Open Period</p>
                    @if($currentPeriod)
                        <h2 class="mt-1 text-xl font-bold text-slate-800">{{ $currentPeriod->name }}</h2>
                        <p class="text-sm text-gray-500">
                            {{ \Illuminate\Support\Carbon::parse($currentPeriod->start_date)->format('M d, Y') }}
                            —
                            {{ \Illuminate\Support\Carbon::parse($currentPeriod->end_date)->format('M d, Y') }}
                        </p>
                    @else
                        <h2 class="mt-1 text-xl font-bold text-amber-700">No open period</h2>
                        <p class="text-sm text-amber-600">A fiscal period must be open for postings to land. Contact an admin.</p>
                    @endif
                </div>

                @if($activeLock)
                    <div class="text-right">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Active Lock</p>
                        <p class="mt-1 text-sm font-semibold {{ $activeLock->is_hard ? 'text-red-700' : 'text-amber-700' }}">
                            {{ $activeLock->is_hard ? 'Hard' : 'Soft' }} lock @
                            {{ \Illuminate\Support\Carbon::parse($activeLock->lock_date)->format('M d, Y') }}
                        </p>
                        @if($activeLock->reason)
                            <p class="text-xs text-gray-500">{{ $activeLock->reason }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Posted (last 30 days)</p>
                <h3 class="mt-2 text-3xl font-bold text-slate-800">{{ number_format($kpis['posted_last_30']) }}</h3>
                <p class="mt-1 text-xs text-gray-400">Journal batches posted</p>
            </div>

            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Drafts Awaiting Approval</p>
                <h3 class="mt-2 text-3xl font-bold text-amber-700">{{ number_format($kpis['drafts_awaiting']) }}</h3>
                <p class="mt-1 text-xs text-gray-400">
                    @if($kpis['drafts_awaiting'] > 0)
                        <a href="{{ route('finance.journals.index', ['status' => 'draft']) }}" class="underline hover:text-amber-700">Review now →</a>
                    @else
                        Approval queue empty
                    @endif
                </p>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Pending Outbox Events</p>
                <h3 class="mt-2 text-3xl font-bold text-blue-700">{{ number_format($kpis['pending_events']) }}</h3>
                <p class="mt-1 text-xs text-gray-400">Awaiting subscriber processing</p>
            </div>

            <div class="rounded-2xl border border-red-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Failed Outbox Events</p>
                <h3 class="mt-2 text-3xl font-bold {{ $kpis['failed_events'] > 0 ? 'text-red-700' : 'text-slate-400' }}">
                    {{ number_format($kpis['failed_events']) }}
                </h3>
                <p class="mt-1 text-xs text-gray-400">
                    @if($kpis['failed_events'] > 0)
                        Action needed — see logs
                    @else
                        All clear
                    @endif
                </p>
            </div>
        </div>

        {{-- Trial balance summary --}}
        @if($tbSummary)
            <div class="mb-6 rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Trial Balance — Current Period</h3>
                        <p class="text-xs text-gray-500">Sum of all postings in {{ $currentPeriod->name }}</p>
                    </div>
                    <a href="{{ route('finance.trial-balance') }}" class="text-sm text-emerald-700 hover:underline font-medium">View full report →</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Total Debits</p>
                        <p class="text-xl font-bold text-slate-800">GHS {{ number_format((float) $tbSummary['debit'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Credits</p>
                        <p class="text-xl font-bold text-slate-800">GHS {{ number_format((float) $tbSummary['credit'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Status</p>
                        @if($tbSummary['balanced'])
                            <p class="text-xl font-bold text-emerald-700">✓ Balanced</p>
                        @else
                            <p class="text-xl font-bold text-red-700">✗ UNBALANCED</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Recent batches --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Recent Batches</h3>
            </div>

            @if($recentBatches->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">No batches yet. They will appear here once posting activity begins.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Batch #</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Posting Date</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Posted By</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentBatches as $b)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $b->batch_number }}</td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs text-slate-600">{{ str_replace('_', ' ', $b->batch_type) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ $b->posting_date?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusClasses = [
                                                'draft' => 'bg-amber-100 text-amber-800 border border-amber-200',
                                                'posted' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                                                'reversed' => 'bg-slate-100 text-slate-700 border border-slate-200',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$b->status] ?? '' }}">
                                            {{ $b->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ $b->postedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">
                                        @php
                                            $journalTotal = $b->journals->sum('total_debit');
                                        @endphp
                                        GHS {{ number_format((float) $journalTotal, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('finance.journals.show', $b) }}" class="text-emerald-700 hover:underline text-xs font-medium">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
