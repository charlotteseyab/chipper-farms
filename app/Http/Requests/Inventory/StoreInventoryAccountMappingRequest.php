<?php

namespace App\Http\Requests\Inventory;

use App\Models\Finance\InventoryAccountMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 4 — validates inventory account mapping create/update.
 *
 * Key invariants:
 *   - is_default=true AND category=DEFAULT_CATEGORY_TOKEN → OK
 *   - is_default=true AND category!=DEFAULT_CATEGORY_TOKEN → REJECT
 *   - is_default=false AND category=DEFAULT_CATEGORY_TOKEN → REJECT
 *   - (org, item_kind, category) must be unique within an org
 */
class StoreInventoryAccountMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('manage_inventory_mappings');
    }

    public function rules(): array
    {
        $mappingId = $this->route('mapping')?->id;

        return [
            'item_kind' => ['required', Rule::in(InventoryAccountMapping::ALLOWED_KINDS)],

            'is_default' => ['nullable', 'boolean'],

            'category' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_account_mappings', 'category')
                    ->where(fn ($q) => $q->where('item_kind', $this->input('item_kind')))
                    ->ignore($mappingId),
            ],

            'inventory_account_id'  => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'gr_ir_account_id'      => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'adjustment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'cogs_account_id'       => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'ppv_account_id'        => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'suspense_account_id'   => ['nullable', 'integer', 'exists:chart_of_accounts,id'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isDefault = (bool) $this->input('is_default', false);
            $category  = (string) $this->input('category', '');

            if ($isDefault && $category !== InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN) {
                $validator->errors()->add('category', sprintf(
                    "Default mappings must use the sentinel category '%s'.",
                    InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
                ));
            }
            if (! $isDefault && $category === InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN) {
                $validator->errors()->add('category', sprintf(
                    "Category '%s' is reserved for the default row. Use is_default=true.",
                    InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
                ));
            }
        });
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        return $data;
    }
}
