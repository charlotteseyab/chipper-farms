<div class="quote-item-row rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
    <div class="mb-3 flex items-center justify-between">
        <p class="quote-item-title text-sm font-bold text-slate-700">Item {{ is_numeric($index) ? ((int) $index + 1) : '' }}</p>
        <button type="button" class="quote-remove-item text-xs font-semibold text-red-600">Remove</button>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
        <label class="text-sm lg:col-span-3"><span class="text-slate-600">Category</span><select data-quote-category name="items[{{ $index }}][kind]" required class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm">@foreach($productKinds as $value => $label)<option value="{{ $value }}" @selected(($item['kind'] ?? 'live_pig') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-sm lg:col-span-5"><span class="text-slate-600">Description</span><input name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" required placeholder="e.g. Large White weaners" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></label>
        <label class="text-sm lg:col-span-2"><span class="text-slate-600">Quantity</span><input type="number" min="0.0001" step="0.0001" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" required class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></label>
        <label class="text-sm lg:col-span-2"><span class="text-slate-600">Unit</span><select data-quote-unit data-selected-unit="{{ $item['quantity_unit'] ?? '' }}" name="items[{{ $index }}][quantity_unit]" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></select><span class="mt-1 block text-[11px] text-slate-400">Options match the category.</span></label>
        <label class="text-sm sm:col-span-2 lg:col-span-12"><span class="text-slate-600">Item notes <span class="text-slate-400">(optional)</span></span><input name="items[{{ $index }}][line_note]" value="{{ $item['line_note'] ?? '' }}" placeholder="Preferred breed, sex, weight, packaging or other requirements" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></label>
    </div>
</div>
