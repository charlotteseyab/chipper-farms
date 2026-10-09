<x-app-layout>
    @php
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
        $today = now()->startOfDay();
        $low = $medicine->reorder_level !== null && bccomp($total, (string) $medicine->reorder_level, 4) <= 0;
    @endphp
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div>
            <a href="{{ route('medicines.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Medicines</a>
            <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ $medicine->display_name }}</h1>
                    <p class="text-sm text-slate-500">
                        Dosed in {{ $medicine->dose_unit }} · stocked in {{ $medicine->stock_unit }} (1 {{ $medicine->stock_unit }} = {{ $qty($medicine->dose_units_per_stock_unit) }} {{ $medicine->dose_unit }}) ·
                        @if($medicine->meat_withdrawal_days === null)<span class="font-semibold text-amber-700">no withdrawal recorded</span>@else{{ $medicine->meat_withdrawal_days }} days withdrawal @endif
                    </p>
                </div>
                <div class="flex gap-2">
                    @can('create', \App\Models\MedicationDosingEvent::class)<a href="{{ route('health.create-medication') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Record a dose</a>@endcan
                    @can('update', $medicine)<a href="{{ route('medicines.edit', $medicine) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Edit medicine</a>@endcan
                </div>
            </div>
        </div>
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<ul role="alert" class="list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif

        <section class="flex flex-wrap items-baseline gap-x-6 gap-y-1 rounded-2xl border p-5 {{ $low ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-white' }}">
            <p class="text-3xl font-bold tabular-nums text-slate-900">{{ $qty($total) }} <span class="text-base font-medium text-slate-500">{{ $medicine->stock_unit }} on hand</span></p>
            @if($medicine->reorder_level !== null)<p class="text-sm {{ $low ? 'font-semibold text-amber-800' : 'text-slate-500' }}">{{ $low ? 'At or below' : 'Reorder level' }} {{ $qty($medicine->reorder_level) }} {{ $medicine->stock_unit }}</p>@endif
        </section>

        <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Batches</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Batch</th><th>Expiry</th><th>Received</th><th class="text-right">Received qty</th><th class="text-right">On hand</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($batches as $batch)
                            @php $expired = $batch->isExpiredOn($today); $soon = ! $expired && $batch->expiry_date->lte($today->copy()->addDays(30)); @endphp
                            <tr class="{{ $expired ? 'text-slate-400' : '' }}">
                                <td class="py-2 font-semibold">{{ $batch->batch_number }}@if($batch->supplier)<div class="text-xs font-normal text-slate-500">{{ $batch->supplier }}</div>@endif</td>
                                <td>
                                    {{ $batch->expiry_date->format('d M Y') }}
                                    @if($expired)<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Expired</span>
                                    @elseif($soon)<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Within 30 days</span>@endif
                                </td>
                                <td>{{ $batch->received_at->format('d M Y') }}</td>
                                <td class="text-right tabular-nums">{{ $qty($batch->quantity_received) }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ $qty($batch->on_hand) }}</td>
                                <td class="text-right">
                                    @can('manageStock', $medicine)
                                        <details class="inline-block text-left">
                                            <summary class="cursor-pointer text-sm font-semibold text-emerald-700">Adjust</summary>
                                            <form method="POST" action="{{ route('medicine-batches.adjust', $batch) }}" class="mt-2 w-72 max-w-full text-left space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                @csrf
                                                <select name="type" class="block w-full rounded-lg border-slate-300 text-sm">
                                                    @foreach(\App\Models\MedicineStockAdjustment::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                                </select>
                                                <input name="quantity" inputmode="decimal" required placeholder="Amount, or the counted total" class="block w-full rounded-lg border-slate-300 text-sm">
                                                <input name="reason" required minlength="5" placeholder="Reason (e.g. vial broken, monthly count)" class="block w-full rounded-lg border-slate-300 text-sm">
                                                <button class="w-full rounded-lg bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white">Save</button>
                                            </form>
                                        </details>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-slate-500">No batches received yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('manageStock', $medicine)
            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Receive a batch</h2>
                <form method="POST" action="{{ route('medicines.batches.store', $medicine) }}" class="mt-3 grid gap-3 sm:grid-cols-3">
                    @csrf
                    <label class="text-sm font-medium text-slate-700">Batch / lot number
                        <input name="batch_number" required maxlength="100" value="{{ old('batch_number') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Expiry date
                        <input type="date" name="expiry_date" required value="{{ old('expiry_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Received on
                        <input type="date" name="received_at" required max="{{ now()->toDateString() }}" value="{{ old('received_at', now()->toDateString()) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Quantity ({{ $medicine->stock_unit }})
                        <input name="quantity_received" required inputmode="decimal" value="{{ old('quantity_received') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Cost per {{ $medicine->stock_unit }}
                        <input name="unit_cost" inputmode="decimal" value="{{ old('unit_cost') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Supplier
                        <input name="supplier" maxlength="255" value="{{ old('supplier') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <div class="sm:col-span-3 flex justify-end"><button class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-semibold text-white">Receive into stock</button></div>
                </form>
            </section>
        @endcan

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Recent doses</h2>
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @forelse($usage as $allocation)
                        <li class="flex items-start justify-between gap-3 py-2 {{ $allocation->event?->status !== 'recorded' ? 'text-slate-400 line-through' : '' }}">
                            <a href="{{ route('doses.show', $allocation->medication_dosing_event_id) }}" class="hover:underline">
                                {{ $allocation->event?->administered_at?->format('d M H:i') }} · {{ $allocation->event?->administrations->pluck('pig.code')->take(4)->implode(', ') }}{{ ($allocation->event?->administrations->count() ?? 0) > 4 ? '…' : '' }}
                            </a>
                            <span class="whitespace-nowrap tabular-nums">{{ $qty($allocation->stock_quantity) }} · {{ $allocation->batch?->batch_number }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-slate-500">No doses recorded yet.</li>
                    @endforelse
                </ul>
            </section>
            <section class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Adjustments and counts</h2>
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @forelse($adjustments as $adjustment)
                        <li class="py-2">
                            <div class="flex justify-between gap-3"><span class="font-semibold">{{ \App\Models\MedicineStockAdjustment::TYPES[$adjustment->type] }} · {{ $adjustment->batch?->batch_number }}</span><span class="tabular-nums">{{ $qty($adjustment->quantity_before) }} → {{ $qty($adjustment->quantity_after) }}</span></div>
                            <p class="text-xs text-slate-500">{{ $adjustment->adjusted_at->format('d M Y H:i') }} · {{ $adjustment->adjustedBy?->name }} · {{ $adjustment->reason }}</p>
                        </li>
                    @empty
                        <li class="py-6 text-center text-slate-500">None yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
