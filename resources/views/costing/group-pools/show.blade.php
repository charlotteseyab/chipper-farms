<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.costing.group-pools.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">
                {{ $pool->group?->name ?? 'Group #'.$pool->group_id }}
                @if($pool->closed_at)<span class="text-sm text-slate-400 font-normal">(closed {{ $pool->closed_at->format('Y-m-d') }})</span>@endif
            </h1>
            <p class="text-sm text-slate-500">{{ $pool->group?->type ?? '—' }} · pool subledger</p>
        </div>

        @php
            $count    = (int) $pool->on_hand_pig_count;
            $weight   = (float) $pool->total_weight_kg;
            $value    = (float) $pool->total_carrying_value;
            $avgW     = $count > 0 ? $weight / $count : 0;
            $perKg    = $weight > 0 ? $value / $weight : 0;
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Pigs</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format($count) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Total weight</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format($weight, 2) }} kg</p>
                <p class="mt-1 text-xs text-slate-400">avg {{ number_format($avgW, 2) }} kg/pig</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Per-kg basis</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format($perKg, 4) }}</p>
                <p class="mt-1 text-xs text-slate-400">used for sale + mortality allocation</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Carrying value</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 font-mono">GH₵ {{ number_format($value, 2) }}</p>
            </div>
        </div>

        <h2 class="text-lg font-bold text-slate-800 mb-3">Movement history</h2>
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Event</th>
                        <th class="px-3 py-2 text-right">Δ pigs</th>
                        <th class="px-3 py-2 text-right">Δ weight</th>
                        <th class="px-3 py-2 text-right">Δ value</th>
                        <th class="px-3 py-2 text-right">Pigs after</th>
                        <th class="px-3 py-2 text-right">Weight after</th>
                        <th class="px-3 py-2 text-right">Value after</th>
                        <th class="px-3 py-2 text-left">Ref</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $m)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ $m->occurred_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-slate-700">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                    @if($m->isPositive()) bg-emerald-50 text-emerald-700 @else bg-red-50 text-red-700 @endif">
                                    {{ str_replace('_', ' ', $m->event_type) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ $m->pig_count_delta }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->weight_delta, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->value_delta, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ $m->pig_count_after }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->weight_after, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->value_after, 2) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $m->reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">No movements recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
