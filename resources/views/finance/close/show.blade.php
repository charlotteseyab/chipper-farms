<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('finance.close.index') }}" class="text-sm text-blue-600 hover:underline">← All periods</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $period->name ?? ('Period ' . $period->period_number) }}</h1>
                <p class="text-sm text-gray-500">{{ $period->start_date->toDateString() }} → {{ $period->end_date->toDateString() }} · Status: <strong>{{ ucfirst($period->status) }}</strong></p>
            </div>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @php
            $fmt = fn ($v) => number_format((float) $v, 2);
            $isOpen = $period->status === 'open';
            $statusBadge = fn ($s) => match($s) {
                'pass'       => 'bg-emerald-50 text-emerald-700',
                'fail'       => 'bg-red-50 text-red-700',
                'skipped'    => 'bg-slate-100 text-slate-500',
                'overridden' => 'bg-amber-50 text-amber-700',
                default      => 'bg-slate-50 text-slate-500',
            };
        @endphp

        {{-- Checklist + close form --}}
        @if($isOpen)
            <form method="POST" action="{{ route('finance.close.close', $period) }}">
                @csrf
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-4">
                    <div class="bg-slate-50 px-4 py-3 font-bold text-slate-700">Pre-Close Checklist</div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-50">
                            @foreach($checks as $check)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-700">{{ $check['label'] }}</div>
                                        @if(!empty($check['detail']))
                                            <div class="text-xs text-slate-400 mt-0.5">
                                                @foreach($check['detail'] as $k => $v)
                                                    {{ $k }}: {{ is_numeric($v) ? $fmt($v) : $v }}@if(!$loop->last) · @endif
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($check['status'] === 'fail' && $check['is_overridable'])
                                            <input type="text" name="overrides[{{ $check['check_key'] }}]"
                                                   placeholder="Override reason (required to proceed despite this failure)"
                                                   class="mt-2 w-full rounded-lg border-amber-300 text-xs">
                                        @elseif($check['status'] === 'fail' && !$check['is_overridable'])
                                            <div class="mt-1 text-xs text-red-600 font-medium">Hard check — cannot be overridden. Must be resolved.</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right align-top">
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $statusBadge($check['status']) }}">{{ ucfirst($check['status']) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4">
                    <label class="block text-xs text-slate-600 mb-1">Close level</label>
                    <select name="close_level" class="rounded-xl border-slate-200 text-sm mb-3">
                        <option value="soft">Soft close (period → closed; adjusting entries still allowed)</option>
                        <option value="hard">Hard close (period → locked; no further postings — privileged)</option>
                    </select>
                    <label class="block text-xs text-slate-600 mb-1">Notes (optional)</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Any context for this close…"></textarea>
                </div>

                <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-medium">Close Period</button>
                <p class="mt-2 text-xs text-slate-400">Hard checks must pass. Soft checks must pass or carry a documented override reason.</p>
            </form>
        @else
            {{-- Already closed/locked: show reopen --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4">
                <p class="text-sm text-slate-600">This period is <strong>{{ $period->status }}</strong>.</p>
                @can('override_period_close')
                    <form method="POST" action="{{ route('finance.close.reopen', $period) }}" class="mt-3">
                        @csrf
                        <label class="block text-xs text-slate-600 mb-1">Reopen reason (required)</label>
                        <input type="text" name="reason" required minlength="5" class="w-full rounded-xl border-slate-200 text-sm mb-2" placeholder="Why is this period being reopened?">
                        <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-xl text-sm">Reopen Period</button>
                        @if($period->status === 'locked')
                            <p class="mt-1 text-xs text-amber-600">This is a locked (hard-closed) period — reopening is a privileged, audited action.</p>
                        @endif
                    </form>
                @endcan
            </div>
        @endif

        {{-- Run history --}}
        @if($runs->count())
            <h2 class="text-lg font-bold text-slate-700 mt-8 mb-3">Close History</h2>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-2 text-left">Run</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Level</th>
                            <th class="px-4 py-2 text-left">Overrides</th>
                            <th class="px-4 py-2 text-left">When</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($runs as $run)
                            <tr>
                                <td class="px-4 py-2">#{{ $run->id }}</td>
                                <td class="px-4 py-2">{{ ucfirst($run->status) }}</td>
                                <td class="px-4 py-2">{{ ucfirst($run->close_level) }}</td>
                                <td class="px-4 py-2">{{ $run->override_count }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $run->completed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right"><a href="{{ route('finance.close.run', $run) }}" class="text-blue-600 hover:underline">View pack</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
