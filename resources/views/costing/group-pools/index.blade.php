<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Group Cost Pools</h1>
                <p class="text-sm text-slate-500">Live Pig Inventory subledger — per-group carrying value, pig count, and weight.</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('finance.costing.group-pools.index', ['show_closed' => $showClosed ? 0 : 1]) }}"
                   class="px-3 py-2 text-sm font-semibold {{ $showClosed ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-700' }} rounded-xl">
                    {{ $showClosed ? 'Showing closed' : 'Show closed pools' }}
                </a>
                @if(auth()->user()?->hasPermission('view_group_pools'))
                    <a href="{{ route('finance.costing.opening-pig-inventory.index') }}"
                       class="px-3 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">
                        Opening Pool
                    </a>
                @endif
                @if(auth()->user()?->hasPermission('manage_pig_mortality'))
                    <a href="{{ route('finance.costing.mortality-writeoffs.index') }}"
                       class="px-3 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">
                        Mortality Audit
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Open pools</p>
                <p class="mt-1 text-2xl font-bold text-slate-800">{{ $totals['pool_count'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Total pigs</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format($totals['total_pig_count']) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Total weight (kg)</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ number_format((float) $totals['total_weight_kg'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Carrying value</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 font-mono">GH₵ {{ number_format((float) $totals['total_value'], 2) }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Group</th>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-right">Pigs</th>
                        <th class="px-3 py-2 text-right">Weight (kg)</th>
                        <th class="px-3 py-2 text-right">Avg weight</th>
                        <th class="px-3 py-2 text-right">Per-kg basis</th>
                        <th class="px-3 py-2 text-right">Carrying value</th>
                        <th class="px-3 py-2 text-left">Opened</th>
                        <th class="px-3 py-2 text-left">Last mvmt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pools as $pool)
                        @php
                            $count    = (int) $pool->on_hand_pig_count;
                            $weight   = (float) $pool->total_weight_kg;
                            $value    = (float) $pool->total_carrying_value;
                            $avgW     = $count > 0 ? $weight / $count : 0;
                            $perKg    = $weight > 0 ? $value / $weight : 0;
                        @endphp
                        <tr class="hover:bg-slate-50 {{ $pool->closed_at ? 'opacity-60' : '' }}">
                            <td class="px-3 py-2 font-medium text-slate-800">
                                <a href="{{ route('finance.costing.group-pools.show', $pool) }}" class="text-emerald-700 hover:underline">
                                    {{ $pool->group?->name ?? 'group#'.$pool->group_id }}
                                </a>
                                @if($pool->closed_at)<span class="ml-1 text-xs text-slate-400">(closed)</span>@endif
                            </td>
                            <td class="px-3 py-2 text-slate-500 text-xs">{{ $pool->group?->type ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($count) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($weight, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($avgW, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($perKg, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format($value, 2) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $pool->opened_at?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $pool->last_movement_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-10 text-center text-slate-500">No pools to show.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
