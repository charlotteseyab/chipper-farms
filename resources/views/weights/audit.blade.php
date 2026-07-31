<x-app-layout>
    @php
        $fieldLabels = [
            'pig_id' => 'Pig',
            'weight' => 'Weight',
            'weight_unit' => 'Unit',
            'weigh_date' => 'Weighing Date',
            'target_week' => 'Target Week',
            'actual_age_weeks' => 'Actual Age (weeks)',
            'purpose' => 'Purpose',
            'body_condition_score' => 'Body Condition Score',
            'health_status' => 'Health Status',
            'notes' => 'Notes',
            'week_notes' => 'Week Notes',
            'weighed_by' => 'Recorded By',
            'is_estimated' => 'Estimated Weight',
            'breed' => 'Breed',
            'gender' => 'Gender',
            'stock_type' => 'Stock Type',
            'week_number' => 'Week Number',
            'expected_min_weight' => 'Minimum Weight',
            'expected_max_weight' => 'Maximum Weight',
            'optimal_weight' => 'Optimal Weight',
            'is_active' => 'Active',
        ];

        $formatAuditValue = function ($field, $value) {
            if ($value === null || $value === '') return 'Not set';
            if (in_array($field, ['is_estimated', 'is_active'], true)) return $value ? 'Yes' : 'No';
            if (in_array($field, ['weight', 'expected_min_weight', 'expected_max_weight', 'optimal_weight'], true)) return number_format((float) $value, 2).' kg';
            if ($field === 'pig_id') return 'Pig #'.$value;
            return is_scalar($value) ? (string) $value : json_encode($value);
        };
    @endphp

    <div class="min-h-screen bg-slate-50 py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-700 via-blue-700 to-cyan-700 p-7 text-white shadow-xl">
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-100">Weight governance</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $weight ? 'Weight Change History' : 'Weight Audit Trail' }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                            {{ $weight
                                ? 'A chronological, read-only record of every saved change to this weight entry.'
                                : 'A chronological, read-only record of saved changes across all weight entries.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if($weight)
                            <a href="{{ route('weights.edit', $weight) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-indigo-700 shadow hover:bg-blue-50">
                                Edit Weight
                            </a>
                        @endif
                        <a href="{{ route('weights.index') }}" class="rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/20">
                            Back to Records
                        </a>
                    </div>
                </div>
            </div>

            @if(!$weight)
                <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <nav class="grid grid-cols-1 gap-2 sm:grid-cols-2" aria-label="Weight audit sections">
                        <a href="{{ route('weights.audit.index', ['tab' => 'weights']) }}"
                            @if($auditTab === 'weights') aria-current="page" @endif
                            class="group relative flex items-center gap-4 overflow-hidden rounded-2xl px-5 py-4 text-left transition-all duration-200 {{ $auditTab === 'weights' ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white shadow-lg shadow-indigo-200' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-800' }}">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $auditTab === 'weights' ? 'bg-white/15 text-white' : 'bg-indigo-100 text-indigo-600 group-hover:bg-indigo-200' }}">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M7 12h10M10 18h4"></path></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-black">Weight Records</span>
                                <span class="mt-0.5 block text-xs {{ $auditTab === 'weights' ? 'text-indigo-100' : 'text-slate-400' }}">Recorded weight changes</span>
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $auditTab === 'weights' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $auditCounts['weights'] }}</span>
                        </a>
                        @if($canViewStandards)
                            <a href="{{ route('weights.audit.index', ['tab' => 'standards']) }}"
                                @if($auditTab === 'standards') aria-current="page" @endif
                                class="group relative flex items-center gap-4 overflow-hidden rounded-2xl px-5 py-4 text-left transition-all duration-200 {{ $auditTab === 'standards' ? 'bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-lg shadow-violet-200' : 'text-slate-600 hover:bg-violet-50 hover:text-violet-800' }}">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $auditTab === 'standards' ? 'bg-white/15 text-white' : 'bg-violet-100 text-violet-600 group-hover:bg-violet-200' }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h10M4 17h7m6-3v6m3-3h-6"></path></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-black">Weight Standards</span>
                                    <span class="mt-0.5 block text-xs {{ $auditTab === 'standards' ? 'text-violet-100' : 'text-slate-400' }}">Expected range changes</span>
                                </span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $auditTab === 'standards' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $auditCounts['standards'] }}</span>
                            </a>
                        @endif
                    </nav>
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $weight ? 'lg:grid-cols-4' : '' }}">
                @if($weight)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Pig</div>
                        <div class="mt-2 text-lg font-black text-slate-900">{{ $weight->pig?->code ?? $weight->pig?->ear_tag_number ?? 'Pig #'.$weight->pig_id }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Current Weight</div>
                        <div class="mt-2 text-lg font-black text-slate-900">{{ number_format((float) $weight->weight, 2) }} {{ $weight->weight_unit }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Weighing Date</div>
                        <div class="mt-2 text-lg font-black text-slate-900">{{ $weight->weigh_date?->format('M d, Y') }}</div>
                    </div>
                @else
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Scope</div>
                        <div class="mt-2 text-lg font-black text-slate-900">{{ $auditTab === 'standards' ? 'All Weight Standards' : 'All Weight Records' }}</div>
                    </div>
                @endif
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Recorded Changes</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ $logs->total() }}</div>
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-black text-slate-900">Audit Timeline</h2>
                    <p class="mt-1 text-sm text-slate-500">Newest changes appear first. Only fields whose saved value changed are shown.</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        @php
                            $fields = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? [])));
                        @endphp
                        <article class="p-6">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-black text-indigo-700">
                                        {{ strtoupper(substr($log->user?->name ?? $log->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ $log->user?->name ?? $log->name ?? 'System' }}</div>
                                        <div class="text-sm text-slate-500">
                                            {{ Str::headline($log->action) }} {{ $log->record_label ?: ($auditTab === 'standards' ? 'weight standard' : 'weight record #'.$log->auditable_id) }}
                                            @if(!$weight && $auditTab === 'weights' && $log->auditable_id)
                                                · <a href="{{ route('weights.audit', $log->auditable_id) }}" class="font-semibold text-indigo-600 hover:text-indigo-800">View record history</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-left sm:text-right">
                                    <time class="text-sm font-semibold text-slate-700" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('M d, Y · H:i') }}</time>
                                    <div class="mt-1 text-xs text-slate-400">{{ $log->created_at->diffForHumans() }} @if($log->ip_address) · {{ $log->ip_address }} @endif</div>
                                </div>
                            </div>

                            <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Field</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Before</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">After</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach($fields as $field)
                                            <tr>
                                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-700">{{ $fieldLabels[$field] ?? Str::headline($field) }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ $formatAuditValue($field, $log->old_values[$field] ?? null) }}</td>
                                                <td class="px-4 py-3 font-medium text-slate-900">{{ $formatAuditValue($field, $log->new_values[$field] ?? null) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-400">↺</div>
                            <h3 class="mt-4 text-lg font-bold text-slate-900">No changes recorded yet</h3>
                            <p class="mt-1 text-sm text-slate-500">The first saved change in this section will appear here.</p>
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <x-simple-pagination :paginator="$logs" />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
