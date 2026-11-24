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
                <div class="flex min-w-0 items-start gap-4">
                    @if($medicine->photo)
                        <a href="{{ route('health.attachments.view', $medicine->photo) }}" target="_blank" class="shrink-0">
                            <img src="{{ route('health.attachments.view', $medicine->photo) }}" alt="{{ $medicine->name }}" class="h-24 w-24 rounded-xl border border-slate-200 object-cover">
                        </a>
                    @endif
                    <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-slate-900">{{ $medicine->display_name }}</h1>
                    <p class="text-sm text-slate-500">
                        Dosed in {{ $medicine->dose_unit }} · stocked in {{ $medicine->stock_unit }} (1 {{ $medicine->stock_unit }} = {{ $qty($medicine->dose_units_per_stock_unit) }} {{ $medicine->dose_unit }}) ·
                        @if($medicine->meat_withdrawal_days === null)<span class="font-semibold text-amber-700">no withdrawal recorded</span>@else{{ $medicine->meat_withdrawal_days }} days withdrawal @endif
                    </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    @can('create', \App\Models\MedicationDosingEvent::class)<a href="{{ route('health.create-medication') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Record a dose</a>@endcan
                    @can('update', $medicine)<a href="{{ route('medicines.edit', $medicine) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Edit medicine</a>@endcan
                </div>
            </div>
        </div>
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<ul role="alert" class="list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif

        @php
            $about = array_filter([
                'What it is and what it does' => $medicine->description,
                'Used for' => $medicine->used_for,
                'Storage' => $medicine->storage_instructions,
                'Precautions and side effects' => $medicine->precautions,
            ], fn ($v) => filled($v));
        @endphp
        <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">About this medicine</h2>
            @if($about)
                <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                    @foreach($about as $label => $text)
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm {{ $label === 'Precautions and side effects' ? 'text-amber-900' : 'text-slate-800' }}">{{ $text }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="mt-2 text-sm text-slate-500">No details yet.@can('update', $medicine) <a href="{{ route('medicines.edit', $medicine) }}" class="font-semibold text-emerald-700 underline">Add what it does, what it's used for and how to store it</a>.@endcan</p>
            @endif
            <p class="mt-3 text-xs text-slate-500">Route: {{ implode(', ', $medicine->allowed_routes ?? []) ?: 'any' }}@if($medicine->withdrawal_source_note) · Withdrawal from: {{ $medicine->withdrawal_source_note }}@endif</p>
        </section>

        <x-health.glance :cards="$glance" />

        <section class="space-y-3">
            <h2 class="font-bold text-slate-900">Batches</h2>
            <div class="health-table-wrap">
              <div class="overflow-x-auto">
                <table class="health-table min-w-[720px]">
                    <thead><tr><th>Batch</th><th>Expiry</th><th>Received</th><th class="num">Received qty</th><th class="num">On hand</th><th></th></tr></thead>
                    <tbody>
                        @forelse($batchPage as $batch)
                            @php $expired = $batch->isExpiredOn($today); $soon = ! $expired && $batch->expiry_date->lte($today->copy()->addDays(30)); @endphp
                            <tr class="{{ $expired ? 'text-slate-400' : '' }}">
                                <td class="font-semibold">{{ $batch->batch_number }}@if($batch->supplier)<div class="text-xs font-normal text-slate-500">{{ $batch->supplier }}</div>@endif</td>
                                <td>
                                    {{ $batch->expiry_date->format('d M Y') }}
                                    @if($expired)<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Expired</span>
                                    @elseif($soon)<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Within 30 days</span>@endif
                                </td>
                                <td>{{ $batch->received_at->format('d M Y') }}</td>
                                <td class="text-right tabular-nums">{{ $qty($batch->quantity_received) }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ $qty($batch->on_hand) }}</td>
                                <td class="text-right num">
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
                            <tr><td colspan="6" class="health-table-empty">No batches received yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
              </div>
              <div class="health-table-footer"><x-health.pagination :paginator="$batchPage" label="batches" /></div>
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

        <section class="space-y-3">
            <h2 class="font-bold text-slate-900">Doses given from this medicine</h2>
            <div class="health-table-wrap">
              <div class="overflow-x-auto">
                <table class="health-table min-w-[640px]">
                    <thead><tr><th>Given</th><th>Animals</th><th>Batch</th><th class="num">Quantity</th><th>Record</th></tr></thead>
                    <tbody>
                        @forelse($usage as $allocation)
                            @php $animals = $allocation->event?->administrations->pluck('pig.code') ?? collect(); @endphp
                            <tr class="{{ $allocation->event?->status !== 'recorded' ? 'text-slate-400 line-through' : '' }}">
                                <td class="whitespace-nowrap">{{ $allocation->event?->administered_at?->format('d M Y H:i') }}</td>
                                <td>{{ $animals->take(4)->implode(', ') }}{{ $animals->count() > 4 ? ' +'.($animals->count() - 4) : '' }}</td>
                                <td>{{ $allocation->batch?->batch_number }}</td>
                                <td class="num">{{ $qty($allocation->stock_quantity) }} {{ $medicine->stock_unit }}</td>
                                <td><a href="{{ route('doses.show', $allocation->medication_dosing_event_id) }}" class="font-semibold text-emerald-700 hover:underline">Dose #{{ $allocation->medication_dosing_event_id }}</a>@if($allocation->event?->status !== 'recorded') <span class="text-xs no-underline">({{ $allocation->event?->status }})</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="health-table-empty">No doses recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
              </div>
              <div class="health-table-footer"><x-health.pagination :paginator="$usage" label="doses" /></div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-bold text-slate-900">Adjustments and counts</h2>
            <div class="health-table-wrap">
              <div class="overflow-x-auto">
                <table class="health-table min-w-[640px]">
                    <thead><tr><th>When</th><th>Type</th><th>Batch</th><th class="num">Stock</th><th>Reason</th><th>By</th></tr></thead>
                    <tbody>
                        @forelse($adjustments as $adjustment)
                            <tr>
                                <td class="whitespace-nowrap">{{ $adjustment->adjusted_at->format('d M Y H:i') }}</td>
                                <td class="font-semibold">{{ \App\Models\MedicineStockAdjustment::TYPES[$adjustment->type] }}</td>
                                <td>{{ $adjustment->batch?->batch_number }}</td>
                                <td class="num whitespace-nowrap">{{ $qty($adjustment->quantity_before) }} → {{ $qty($adjustment->quantity_after) }}</td>
                                <td>{{ $adjustment->reason }}</td>
                                <td>{{ $adjustment->adjustedBy?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="health-table-empty">No adjustments or counts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
              </div>
              <div class="health-table-footer"><x-health.pagination :paginator="$adjustments" label="adjustments" /></div>
            </div>
        </section>
        </div>
    </div>
</x-app-layout>
