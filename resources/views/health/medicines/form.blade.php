<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24">
        <a href="{{ route('medicines.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Medicines</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $medicine ? 'Edit '.$medicine->name : 'Add a Medicine' }}</h1>
        @if($medicine)<p class="text-sm text-slate-500">Doses already given keep the name, units and withdrawal they were given with.</p>@endif
        @if($errors->any())<ul role="alert" class="mt-4 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        <form method="POST" action="{{ $medicine ? route('medicines.update', $medicine) : route('medicines.store') }}" class="mt-5 space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            @csrf
            @if($medicine) @method('PUT') @endif
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="text-sm font-medium text-slate-700 sm:col-span-3">Name
                    <input name="name" required maxlength="255" value="{{ old('name', $medicine?->name) }}" placeholder="e.g. Oxytetracycline LA" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">Formulation
                    <input name="formulation" maxlength="255" value="{{ old('formulation', $medicine?->formulation) }}" placeholder="Injectable solution" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">Concentration
                    <input name="concentration" maxlength="255" value="{{ old('concentration', $medicine?->concentration) }}" placeholder="200 mg/ml" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">Routes (comma separated)
                    <input name="allowed_routes" value="{{ old('allowed_routes', implode(', ', $medicine?->allowed_routes ?? [])) }}" placeholder="Injection (IM), Oral" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
            </div>
            <fieldset class="rounded-xl border border-slate-100 p-4">
                <legend class="px-1 text-sm font-semibold text-slate-800">Units</legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="text-sm font-medium text-slate-700">Dose is measured in
                        <input name="dose_unit" required maxlength="20" value="{{ old('dose_unit', $medicine?->dose_unit) }}" placeholder="ml, mg, dose" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Stock is counted in
                        <input name="stock_unit" required maxlength="20" value="{{ old('stock_unit', $medicine?->stock_unit) }}" placeholder="ml, vial, tablet" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">One stock unit holds
                        <input type="number" step="0.0001" min="0.0001" name="dose_units_per_stock_unit" required value="{{ old('dose_units_per_stock_unit', $medicine ? rtrim(rtrim((string) $medicine->dose_units_per_stock_unit, '0'), '.') : '1') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <span class="text-xs text-slate-500">dose units, e.g. 50 for a 50-dose vial, 1 for ml and ml</span>
                    </label>
                </div>
            </fieldset>
            <fieldset class="rounded-xl border border-amber-100 bg-amber-50/50 p-4">
                <legend class="px-1 text-sm font-semibold text-slate-800">Meat withdrawal</legend>
                <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
                    <label class="text-sm font-medium text-slate-700">Days
                        <input type="number" min="0" max="365" name="meat_withdrawal_days" value="{{ old('meat_withdrawal_days', $medicine?->meat_withdrawal_days) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Source
                        <input name="withdrawal_source_note" maxlength="1000" value="{{ old('withdrawal_source_note', $medicine?->withdrawal_source_note) }}" placeholder="e.g. Product label, batch leaflet" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
                <p class="mt-2 text-xs text-slate-600">Enter the days printed on the label. Leave blank only if the label gives none; a blank is shown as a caution before any sale, never as safe.</p>
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Reorder level (stock units, optional)
                    <input type="number" step="0.0001" min="0" name="reorder_level" value="{{ old('reorder_level', $medicine?->reorder_level) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">Status
                    <select name="is_active" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="1" @selected(old('is_active', $medicine?->is_active ?? true))>In use</option>
                        <option value="0" @selected(! old('is_active', $medicine?->is_active ?? true))>Not in use</option>
                    </select>
                </label>
            </div>
            <div class="flex justify-end"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Save medicine</button></div>
        </form>
    </div>
</x-app-layout>
