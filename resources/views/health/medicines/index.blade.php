<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Medicines</h1>
                <p class="text-sm text-slate-500">The farm's medicine catalogue: units, routes and the label withdrawal period. Doses keep the details they were given with, so changes here never alter past doses.</p>
            </div>
            @can('create', \App\Models\Medicine::class)<a href="{{ route('medicines.create') }}" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Add a medicine</a>@endcan
        </div>
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-left">Medicine</th><th class="px-4 py-3 text-left">Dose unit</th><th class="px-4 py-3 text-left">Stock unit</th><th class="px-4 py-3 text-left">Routes</th><th class="px-4 py-3 text-left">Meat withdrawal</th><th class="px-4 py-3 text-right">On hand</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($medicines as $medicine)
                        <tr class="{{ $medicine->is_active ? '' : 'text-slate-400' }}">
                            <td class="px-4 py-3 font-semibold"><a href="{{ route('medicines.show', $medicine) }}" class="text-emerald-800 hover:underline">{{ $medicine->display_name }}</a>@unless($medicine->is_active) <span class="text-xs font-normal">(not in use)</span>@endunless</td>
                            <td class="px-4 py-3">{{ $medicine->dose_unit }}</td>
                            <td class="px-4 py-3">{{ $medicine->stock_unit }} <span class="text-xs text-slate-500">= {{ rtrim(rtrim((string) $medicine->dose_units_per_stock_unit, '0'), '.') }} {{ $medicine->dose_unit }}</span></td>
                            <td class="px-4 py-3 text-slate-600">{{ implode(', ', $medicine->allowed_routes ?? []) ?: 'Any' }}</td>
                            <td class="px-4 py-3">
                                @if($medicine->meat_withdrawal_days === null)<span class="font-semibold text-amber-700">Not recorded</span>@else{{ $medicine->meat_withdrawal_days }} days @endif
                                @if($medicine->withdrawal_source_note)<div class="text-xs text-slate-500">{{ $medicine->withdrawal_source_note }}</div>@endif
                            </td>
                            @php $have = $onHand[$medicine->id] ?? '0'; $low = $medicine->reorder_level !== null && bccomp($have, (string) $medicine->reorder_level, 4) <= 0; @endphp
                            <td class="px-4 py-3 text-right tabular-nums {{ $low ? 'font-semibold text-amber-700' : '' }}">{{ rtrim(rtrim(number_format((float) $have, 4, '.', ''), '0'), '.') ?: '0' }} {{ $medicine->stock_unit }}@if($low)<div class="text-xs font-normal">at reorder level</div>@endif</td>
                            <td class="px-4 py-3 text-right">@can('update', $medicine)<a href="{{ route('medicines.edit', $medicine) }}" class="font-semibold text-emerald-700 hover:underline">Edit</a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No medicines yet. Add the ones the farm uses so courses can be prescribed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $medicines->links() }}</div>
    </div>
</x-app-layout>
