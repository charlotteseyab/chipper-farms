<x-app-layout>
    <x-feed-hub hub="today" />
    @php $showCosts = auth()->user()?->hasPermission('view_finance'); @endphp
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('feed.issues.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Feeding #{{ $issue->id }}</h1>
            <p class="text-sm text-slate-500">
                {{ $issue->issued_at?->format('Y-m-d H:i') }}
                · {{ $issue->feeding_stage ? 'Stage' : 'Group' }} <strong>{{ $issue->where_label }}</strong>
                · Feed <strong>{{ $issue->feedType?->name }}</strong>
                · By {{ $issue->issuedBy?->name ?? '—' }}
            </p>
        </div>

        @if($issue->isReversed())
            <div role="status" class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Reversed {{ $issue->reversed_at->format('j M Y, H:i') }} by {{ $issue->reverser?->name ?? '—' }}</p>
                <p class="mt-1">{{ $issue->reversal_reason }}</p>
                <p class="mt-1 text-xs text-slate-500">The feed went back into stock and this feeding no longer counts towards consumption.</p>
            </div>
        @endif

        <div class="grid grid-cols-1 {{ $showCosts ? 'sm:grid-cols-4' : 'sm:grid-cols-2' }} gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Quantity</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ \App\Support\Qty::kg((float) $issue->quantity_kg) }} <span class="text-base text-slate-500">kg</span></p>
            </div>
            @if($showCosts)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Unit cost (WAC)</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ \App\Support\Qty::money((float) $issue->unit_cost_at_issuance) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Total cost</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 font-mono">GH₵ {{ number_format((float) $issue->total_cost, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Posting</p>
                @php
                    $badge = match($issue->posting_status) {
                        'posted'   => 'bg-emerald-50 text-emerald-700',
                        'pending'  => 'bg-amber-50 text-amber-700',
                        'failed'   => 'bg-red-50 text-red-700',
                        'reversed' => 'bg-slate-100 text-slate-500',
                        default    => 'bg-slate-50 text-slate-600',
                    };
                @endphp
                <p class="mt-1"><span class="px-2 py-0.5 text-sm font-semibold rounded-full {{ $badge }}">{{ $issue->posting_status }}</span></p>
                @if($issue->journalBatch)
                    <p class="mt-2 text-xs text-slate-400">Journal batch #{{ $issue->journalBatch->id }}</p>
                @endif
            </div>
            @else
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Planned for the day</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ $issue->planned_quantity_kg === null ? '—' : number_format((float) $issue->planned_quantity_kg, 2).' kg' }}</p>
            </div>
            @endif
        </div>

        <section class="rounded-xl border bg-white p-4 mb-6"><h2 class="font-bold">Saved daily feeding plan</h2>
            <p>Daily target for this {{ $issue->feeding_stage ? 'stage' : 'group' }} and feed: {{ $issue->planned_quantity_kg === null ? 'Unknown (no reliable plan recorded)' : \App\Support\Qty::kg($issue->planned_quantity_kg).' kg' }}. Multiple deliveries share this one daily target.</p>
            @foreach($issue->planning_snapshot['rows'] ?? [] as $row)<p>{{ \App\Services\Feed\FeedStageResolver::STAGES[$row['stage']] ?? $row['stage'] }}: {{ $row['animal_count'] }} animals × {{ $row['rate'] }} kg/day = {{ $row['daily_kg'] }} kg (standard #{{ $row['standard_id'] }}).</p>@endforeach
            <p class="text-sm text-slate-500">Saved when the first delivery was recorded. Later herd, standard and stock changes do not rewrite this target.</p>
        </section>
        @if($showCosts && $issue->posting_error)
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <p class="font-semibold mb-1">Posting error</p>
                <p>{{ $issue->posting_error }}</p>
            </div>
        @endif

        @if(! $issue->isReversed() && auth()->user()?->hasPermission('manage_feed_issues'))
            <details class="mb-6 rounded-2xl border border-red-100 bg-white p-4 shadow-sm" @if($errors->has('reason')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-red-700">Recorded by mistake? Reverse this feeding</summary>
                <form method="POST" action="{{ route('feed.issues.reverse', $issue) }}" class="mt-3 space-y-3">
                    @csrf
                    <p class="text-sm text-slate-600">{{ number_format((float) $issue->quantity_kg, 2) }} kg of {{ $issue->feedType?->name }} goes back into stock. The feeding stays on record, marked as reversed.</p>
                    <label class="block text-sm font-medium text-slate-700">Reason
                        <input name="reason" required minlength="5" maxlength="2000" value="{{ old('reason') }}" placeholder="e.g. Entered 500 kg instead of 50 kg"
                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    @error('reason')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Reverse feeding</button>
                </form>
            </details>
        @endif

        @if($issue->reference)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-2">
                <p class="text-xs uppercase text-slate-500 mb-1">Reference</p>
                <p class="text-sm text-slate-800">{{ $issue->reference }}</p>
            </div>
        @endif
        @if($issue->notes)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs uppercase text-slate-500 mb-1">Notes</p>
                <p class="text-sm text-slate-800">{{ $issue->notes }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
