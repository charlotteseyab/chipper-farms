<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Medicines</h1>
                <p class="text-sm text-slate-500">The farm's medicine catalogue: what each medicine is for, its units and routes, and the label withdrawal period. Open one to see its details and stock. Doses keep the details they were given with, so changes here never alter past doses.</p>
            </div>
            @can('create', \App\Models\Medicine::class)<a href="{{ route('medicines.create') }}" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Add a medicine</a>@endcan
        </div>
        <x-health.glance :cards="$glance" />
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        <div class="health-table-wrap">
            <div class="overflow-x-auto">
            <table class="health-table">
                <thead><tr><th>Medicine</th><th>Dose unit</th><th>Stock unit</th><th>Routes</th><th>Meat withdrawal</th><th class="num">On hand</th><th></th></tr></thead>
                <tbody>
                    @forelse($medicines as $medicine)
                        <tr class="{{ $medicine->is_active ? '' : 'text-slate-400' }}">
                            <td class="font-semibold"><div class="flex items-center gap-3">@if($medicine->photo)<img src="{{ route('health.attachments.view', $medicine->photo) }}" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-lg border border-slate-200 object-cover">@else<span class="h-10 w-10 shrink-0 rounded-lg border border-dashed border-slate-200"></span>@endif<div><a href="{{ route('medicines.show', $medicine) }}" class="text-emerald-800 hover:underline">{{ $medicine->display_name }}</a>@if($medicine->used_for)<div class="text-xs font-normal text-slate-500">{{ \Illuminate\Support\Str::limit($medicine->used_for, 80) }}</div>@endif@unless($medicine->is_active) <span class="text-xs font-normal">(not in use)</span>@endunless</div></div></td>
                            <td>{{ $medicine->dose_unit }}</td>
                            <td>{{ $medicine->stock_unit }} <span class="text-xs text-slate-500">= {{ rtrim(rtrim((string) $medicine->dose_units_per_stock_unit, '0'), '.') }} {{ $medicine->dose_unit }}</span></td>
                            <td class="text-slate-600">{{ implode(', ', $medicine->allowed_routes ?? []) ?: 'Any' }}</td>
                            <td>
                                @if($medicine->meat_withdrawal_days === null)<span class="font-semibold text-amber-700">Not recorded</span>@else{{ $medicine->meat_withdrawal_days }} days @endif
                                @if($medicine->withdrawal_source_note)<div class="text-xs text-slate-500">{{ $medicine->withdrawal_source_note }}</div>@endif
                            </td>
                            @php $have = $onHand[$medicine->id] ?? '0'; $low = $medicine->reorder_level !== null && bccomp($have, (string) $medicine->reorder_level, 4) <= 0; @endphp
                            <td class="px-4 py-3 text-right tabular-nums {{ $low ? 'font-semibold text-amber-700' : '' }}">{{ rtrim(rtrim(number_format((float) $have, 4, '.', ''), '0'), '.') ?: '0' }} {{ $medicine->stock_unit }}@if($low)<div class="text-xs font-normal">at reorder level</div>@endif</td>
                            <td class="whitespace-nowrap text-right num"><a href="{{ route('medicines.show', $medicine) }}" class="font-semibold text-emerald-700 hover:underline">View</a>@can('update', $medicine)<span class="text-slate-300"> · </span><a href="{{ route('medicines.edit', $medicine) }}" class="font-semibold text-emerald-700 hover:underline">Edit</a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No medicines yet. Add the ones the farm uses so courses can be prescribed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
            <div class="health-table-footer"><x-health.pagination :paginator="$medicines" label="medicines" /></div>
        </div>
    </div>
</x-app-layout>
