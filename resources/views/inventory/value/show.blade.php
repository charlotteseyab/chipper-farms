<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('inventory.value.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                ← Back to inventory value
            </a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">{{ $summary['item_name'] }}</h1>
            <p class="text-sm text-slate-500">
                {{ str_replace('_', ' ', $summary['item_kind']) }}
                @if($summary['category']) · {{ $summary['category'] }} @endif
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-slate-500">On hand</p>
                <h3 class="mt-2 text-2xl font-bold text-slate-800 font-mono">
                    {{ number_format((float) $summary['on_hand_qty'], 4) }}
                    <span class="text-base text-slate-500">{{ $summary['unit'] }}</span>
                </h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-slate-500">WAC</p>
                <h3 class="mt-2 text-2xl font-bold text-slate-800 font-mono">
                    GH₵ {{ number_format((float) $summary['wac'], 6) }}
                </h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-slate-500">Total value</p>
                <h3 class="mt-2 text-2xl font-bold text-emerald-700 font-mono">
                    GH₵ {{ number_format((float) $summary['value'], 2) }}
                </h3>
            </div>
        </div>

        <h2 class="text-lg font-bold text-slate-800 mb-3">Recent movements</h2>
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Event</th>
                        <th class="px-3 py-2 text-right">Δ qty</th>
                        <th class="px-3 py-2 text-right">Δ value</th>
                        <th class="px-3 py-2 text-right">Qty after</th>
                        <th class="px-3 py-2 text-right">Value after</th>
                        <th class="px-3 py-2 text-right">WAC after</th>
                        <th class="px-3 py-2 text-left">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $m)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-600">
                                {{ $m->occurred_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-3 py-2 text-slate-700">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                    @if($m->isPositive()) bg-emerald-50 text-emerald-700 @else bg-red-50 text-red-700 @endif">
                                    {{ str_replace('_', ' ', $m->event_type) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->quantity_delta, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->value_delta, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->quantity_on_hand_after, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->value_on_hand_after, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->weighted_avg_cost_after, 6) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $m->reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">No movements recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
