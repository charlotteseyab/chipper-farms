<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Mortality Writeoff Audit</h1>
            <p class="text-sm text-slate-500">Each row is a recorded pig death that took an allocated share of its group's cost pool. Rows with the "estimated weight" flag fell back to the group's average weight at death — the per-pig weight wasn't known.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Deaths posted</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format($totals['count']) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Total written off</p>
                <p class="mt-1 text-2xl font-bold text-red-700 font-mono">GH₵ {{ number_format((float) $totals['total_writeoff'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs text-amber-700">Used group-average weight</p>
                <p class="mt-1 text-2xl font-bold text-amber-800 font-mono">{{ number_format($totals['used_avg_count']) }}</p>
                <p class="mt-1 text-xs text-amber-700">deaths where per-pig weight was unknown</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Pig</th>
                        <th class="px-3 py-2 text-left">Cause</th>
                        <th class="px-3 py-2 text-left">Group</th>
                        <th class="px-3 py-2 text-right">Weight (kg)</th>
                        <th class="px-3 py-2 text-right">Pool value before</th>
                        <th class="px-3 py-2 text-right">Writeoff</th>
                        <th class="px-3 py-2 text-left">Posting</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($writeoffs as $w)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ $w->occurred_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 font-medium">
                                @php($pig = optional($w->pigDeath)->pig ?? $w->pig)
                                {{ $pig?->identityAt($w->pigDeath?->death_date ?? $w->occurred_at) ?? 'pig#'.$w->pig_id }}
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ optional($w->pigDeath)->death_cause ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ optional($w->group)->name ?? 'group#'.$w->group_id }}</td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ number_format((float) $w->pig_weight_at_death_kg, 2) }}
                                @if($w->used_group_average_weight)
                                    <span class="ml-1 px-1.5 py-0.5 text-xs font-semibold rounded-full bg-amber-50 text-amber-700"
                                          title="Pig had no recorded weight at death; used group average">est</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $w->group_value_before, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-semibold text-red-700">{{ number_format((float) $w->writeoff_amount, 2) }}</td>
                            <td class="px-3 py-2">
                                @if($w->journal_batch_id)
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700">posted</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-50 text-amber-700">pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-10 text-center text-slate-500">No mortality writeoffs recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $writeoffs->links() }}</div>
    </div>
</x-app-layout>
