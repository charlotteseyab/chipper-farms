<tr class="border-t border-gray-200" data-purchase-item-row>
    <td class="px-3 py-2">
        <select name="items[{{ $index }}][raw_material_id]"
                data-material-select
                data-native-select
                class="w-full rounded-lg border-gray-300 shadow-sm">
            <option value="">— free text —</option>
            @foreach($rawMaterials as $rm)
                <option value="{{ $rm->id }}" @selected((string) ($item['raw_material_id'] ?? '') === (string) $rm->id)>
                    {{ $rm->name }}
                </option>
            @endforeach
        </select>
    </td>
    <td class="px-3 py-2">
        <input type="text" name="items[{{ $index }}][description]"
               value="{{ $item['description'] ?? '' }}" data-item-description required
               class="w-full rounded-lg border-gray-300 shadow-sm">
    </td>
    <td class="px-3 py-2">
        <input type="text" name="items[{{ $index }}][unit]"
               value="{{ $item['unit'] ?? '' }}" data-item-unit maxlength="20"
               class="w-20 rounded-lg border-gray-300 shadow-sm">
    </td>
    <td class="px-3 py-2">
        <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]"
               value="{{ $item['quantity'] ?? 1 }}" data-item-quantity required
               class="w-24 rounded-lg border-gray-300 shadow-sm text-right font-mono">
    </td>
    <td class="px-3 py-2">
        <input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]"
               value="{{ $item['unit_price'] ?? 0 }}" data-item-price required
               class="w-28 rounded-lg border-gray-300 shadow-sm text-right font-mono">
    </td>
    <td class="px-3 py-2 text-sm font-semibold text-gray-700">
        <span data-item-total>0.00</span>
    </td>
    <td class="px-3 py-2">
        <button type="button" data-remove-item class="text-red-600 hover:text-red-800 text-sm font-medium">
            Remove
        </button>
    </td>
</tr>
