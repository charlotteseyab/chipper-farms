<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Inventory Value</h1>
                <p class="text-sm text-slate-500">Current on-hand quantity, weighted-average cost, and total value across all inventory items.</p>
            </div>

            <div class="flex items-center gap-2">
                @if(auth()->user()?->hasPermission('manage_inventory_mappings'))
                    <a href="{{ route('inventory.account-mappings.index') }}"
                       class="px-3 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">
                        Account Mappings
                    </a>
                @endif
                @if(auth()->user()?->hasPermission('manage_opening_balances'))
                    <a href="{{ route('inventory.opening-balances.index') }}"
                       class="px-3 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">
                        Opening Balances
                    </a>
                @endif
                @if(auth()->user()?->hasPermission('view_gr_ir_ageing'))
                    <a href="{{ route('inventory.gr-ir-ageing.index') }}"
                       class="px-3 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">
                        GR/IR Ageing
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-slate-500">Raw Materials</p>
                <h3 class="mt-2 text-2xl font-bold text-emerald-700">
                    GH₵ {{ number_format((float) $totals['raw_material_value'], 2) }}
                </h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-slate-500">Finished Feed</p>
                <h3 class="mt-2 text-2xl font-bold text-emerald-700">
                    GH₵ {{ number_format((float) $totals['finished_feed_value'], 2) }}
                </h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-slate-500">Total</p>
                <h3 class="mt-2 text-2xl font-bold text-slate-900">
                    GH₵ {{ number_format((float) $totals['total_value'], 2) }}
                </h3>
            </div>
        </div>

        <div class="mb-4 flex items-center gap-2">
            <a href="{{ route('inventory.value.index') }}"
               class="px-3 py-1.5 text-sm rounded-lg {{ $kind === null ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-700' }}">
                All
            </a>
            <a href="{{ route('inventory.value.index', ['kind' => 'raw_material']) }}"
               class="px-3 py-1.5 text-sm rounded-lg {{ $kind === 'raw_material' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-700' }}">
                Raw Materials
            </a>
            <a href="{{ route('inventory.value.index', ['kind' => 'finished_feed']) }}"
               class="px-3 py-1.5 text-sm rounded-lg {{ $kind === 'finished_feed' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-700' }}">
                Finished Feed
            </a>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Kind</th>
                        <th class="px-4 py-3 text-left">Item</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-right">On hand</th>
                        <th class="px-4 py-3 text-right">WAC</th>
                        <th class="px-4 py-3 text-right">Value (GH₵)</th>
                        <th class="px-4 py-3 text-left">Last movement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                    {{ $row['item_kind'] === 'raw_material' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ str_replace('_', ' ', $row['item_kind']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <a href="{{ route('inventory.value.show', [$row['item_kind'], $row['item_id']]) }}"
                                   class="hover:underline">
                                    {{ $row['item_name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $row['category'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">
                                {{ number_format((float) $row['on_hand_qty'], 4) }} {{ $row['unit'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">
                                {{ number_format((float) $row['wac'], 4) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-slate-900">
                                {{ number_format((float) $row['value'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $row['last_movement_at'] ? \Carbon\Carbon::parse($row['last_movement_at'])->diffForHumans() : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                No inventory recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
