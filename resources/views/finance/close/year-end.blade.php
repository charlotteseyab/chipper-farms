<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Year-End Close</h1>
            <p class="text-sm text-gray-500">Sweep P&amp;L to Retained Earnings and close the fiscal year.</p>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        <div class="space-y-4">
            @foreach($years as $year)
                @php $closing = $closings[$year->id] ?? null; @endphp
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-700">{{ $year->name ?? ('FY ' . $year->id) }}</div>
                            <div class="text-sm text-slate-500">{{ $year->start_date->toDateString() }} → {{ $year->end_date->toDateString() }} · {{ ucfirst($year->status) }}</div>
                        </div>
                        <div class="text-right">
                            @if($closing && $closing->status === 'completed')
                                <div class="text-sm text-slate-500">Net {{ (float) $closing->net_profit >= 0 ? 'profit' : 'loss' }}</div>
                                <div class="font-bold {{ (float) $closing->net_profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">GHS {{ $fmt(abs((float) $closing->net_profit)) }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        @if($closing && $closing->status === 'completed')
                            <span class="px-2 py-1 rounded-lg text-xs font-medium bg-slate-200 text-slate-700">Closed · batch #{{ $closing->closing_journal_batch_id }}</span>
                            @can('override_period_close')
                                <form method="POST" action="{{ route('finance.year-end.reverse', $year) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="reason" required minlength="5" placeholder="Reversal reason" class="rounded-lg border-slate-200 text-xs">
                                    <button class="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs">Reverse</button>
                                </form>
                            @endcan
                        @elseif($closing && $closing->status === 'reversed')
                            <span class="px-2 py-1 rounded-lg text-xs font-medium bg-amber-50 text-amber-700">Reversed</span>
                        @else
                            @can('manage_year_end_close')
                                <form method="POST" action="{{ route('finance.year-end.run', $year) }}" onsubmit="return confirm('Run year-end close for this fiscal year? This posts the closing journal.');">
                                    @csrf
                                    <button class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm" {{ $year->status === 'locked' ? 'disabled' : '' }}>Run Year-End Close</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-slate-400">
            The closing journal debits each revenue account, credits each expense account, and posts the net to Retained Earnings (3200).
            It is posted through the normal posting engine, so period locks and audit apply. Reversal reopens the year.
        </p>
    </div>
</x-app-layout>
