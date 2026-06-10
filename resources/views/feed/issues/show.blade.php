<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.feed.issues.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Feed Issue #{{ $issue->id }}</h1>
            <p class="text-sm text-slate-500">
                {{ $issue->issued_at?->format('Y-m-d H:i') }}
                · Group <strong>{{ $issue->group?->name }}</strong>
                · Feed <strong>{{ $issue->feedType?->name }}</strong>
                · By {{ $issue->issuedBy?->name ?? '—' }}
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Quantity</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format((float) $issue->quantity_kg, 4) }} <span class="text-base text-slate-500">kg</span></p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Unit cost (WAC)</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format((float) $issue->unit_cost_at_issuance, 6) }}</p>
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
        </div>

        @if($issue->posting_error)
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <p class="font-semibold mb-1">Posting error</p>
                <p>{{ $issue->posting_error }}</p>
            </div>
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
