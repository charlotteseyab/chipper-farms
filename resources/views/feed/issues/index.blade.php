<x-app-layout>
    <x-feed-hub hub="today" />
    @php $showCosts = auth()->user()?->hasPermission('view_finance'); @endphp
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Feedings</h1>
                <p class="text-sm text-slate-500">Feed given to each feeding stage.@if($showCosts) Each feeding is costed at the feed's average cost and added to that stage's live pig value.@endif</p>
            </div>

            @if(auth()->user()?->hasPermission('manage_feed_issues'))
                <a href="{{ route('feed.issues.create') }}"
                   class="px-3 py-2 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl">
                    + Record feeding
                </a>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="mb-4 px-4 py-3 rounded-xl bg-amber-50 text-amber-700 text-sm">{{ session('warning') }}</div>@endif
        @if(session('error'))<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Issued</th>
                        <th class="px-3 py-2 text-left">Fed to</th>
                        <th class="px-3 py-2 text-left">Feed</th>
                        <th class="px-3 py-2 text-right">Qty (kg)</th>
                        @if($showCosts)
                            <th class="px-3 py-2 text-right">Unit cost</th>
                            <th class="px-3 py-2 text-right">Total (GH₵)</th>
                        @endif
                        <th class="px-3 py-2 text-left">By</th>
                        @if($showCosts)<th class="px-3 py-2 text-left">Posting</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($issues as $issue)
                        <tr class="hover:bg-slate-50 {{ $issue->isReversed() ? 'text-slate-400' : '' }}">
                            <td class="px-3 py-2 text-slate-600">
                                <a href="{{ route('feed.issues.show', $issue) }}" class="text-emerald-700 hover:underline">
                                    {{ $issue->issued_at?->format('Y-m-d H:i') }}
                                </a>
                            </td>
                            <td class="px-3 py-2 font-medium">
                                {{ $issue->recipient_label }}
                                @if($issue->isReversed())<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">Reversed</span>@endif
                            </td>
                            <td class="px-3 py-2">{{ $issue->feedType?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $issue->quantity_kg, 4) }}</td>
                            @if($showCosts)
                                <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $issue->unit_cost_at_issuance, 6) }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format((float) $issue->total_cost, 2) }}</td>
                            @endif
                            <td class="px-3 py-2 text-xs">{{ $issue->issuedBy?->name ?? '—' }}</td>
                            @if($showCosts)
                            <td class="px-3 py-2">
                                @php
                                    $badge = match($issue->posting_status) {
                                        'posted'   => 'bg-emerald-50 text-emerald-700',
                                        'pending'  => 'bg-amber-50 text-amber-700',
                                        'failed'   => 'bg-red-50 text-red-700',
                                        'reversed' => 'bg-slate-100 text-slate-500',
                                        default    => 'bg-slate-50 text-slate-600',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $badge }}">{{ $issue->posting_status }}</span>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showCosts ? 8 : 5 }}" class="px-3 py-10 text-center text-slate-500">No feedings recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $issues->links() }}</div>
    </div>
</x-app-layout>
