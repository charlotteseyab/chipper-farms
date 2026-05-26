@php
    use App\Models\Finance\InventoryAccountMapping;
    $isCreate = ! $mapping->exists;
    $action = $isCreate
        ? route('inventory.account-mappings.store')
        : route('inventory.account-mappings.update', $mapping);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4 max-w-3xl">
    @csrf
    @unless($isCreate) @method('PUT') @endunless

    @if($errors->any())
        <div class="px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Item kind</label>
            <select name="item_kind" required class="w-full rounded-lg border-slate-200">
                @foreach($kinds as $k)
                    <option value="{{ $k }}" @selected(old('item_kind', $mapping->item_kind) === $k)>
                        {{ str_replace('_', ' ', $k) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">
                Default row
                <span class="text-slate-400 font-normal lowercase">(org-wide fallback)</span>
            </label>
            <label class="inline-flex items-center gap-2 mt-1">
                <input type="checkbox" name="is_default" value="1"
                       @checked(old('is_default', $mapping->is_default))
                       onchange="document.getElementById('category-input').value = this.checked ? '{{ InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN }}' : ''">
                <span class="text-sm text-slate-700">Mark as default for this kind</span>
            </label>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Category</label>
            <input id="category-input" type="text" name="category"
                   value="{{ old('category', $mapping->category) }}"
                   required maxlength="100"
                   class="w-full rounded-lg border-slate-200">
            <p class="mt-1 text-xs text-slate-400">
                Use <code class="text-slate-600">{{ InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN }}</code> for the default row, or a real category string (e.g. grain, protein) to override that category specifically.
            </p>
        </div>

        @foreach([
            'inventory_account_id'  => 'Inventory account (Dr at receipt)',
            'gr_ir_account_id'      => 'GR/IR account (Cr at PR-linked receipt)',
            'suspense_account_id'   => 'Suspense (Cr at direct receipt)',
            'adjustment_account_id' => 'Adjustment account',
            'cogs_account_id'       => 'COGS account',
            'ppv_account_id'        => 'PPV account',
        ] as $field => $label)
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ $label }}</label>
                <select name="{{ $field }}" class="w-full rounded-lg border-slate-200">
                    <option value="">— use fallback —</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" @selected(old($field, $mapping->{$field}) == $a->id)>
                            {{ $a->code }} — {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Notes</label>
        <textarea name="notes" rows="3" maxlength="2000"
                  class="w-full rounded-lg border-slate-200">{{ old('notes', $mapping->notes) }}</textarea>
    </div>

    <div class="flex items-center gap-2">
        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
            {{ $isCreate ? 'Create mapping' : 'Save changes' }}
        </button>
        <a href="{{ route('inventory.account-mappings.index') }}" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-900">Cancel</a>
    </div>
</form>
