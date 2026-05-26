<?php

namespace App\Http\Requests\Inventory;

use App\Models\Finance\InventoryOpeningBalanceImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('manage_opening_balances');
    }

    public function rules(): array
    {
        $kind = $this->input('item_kind');

        $idRule = $kind === InventoryOpeningBalanceImport::KIND_FINISHED_FEED
            ? ['required', 'integer', 'exists:feed_types,id']
            : ['required', 'integer', 'exists:raw_materials,id'];

        return [
            'item_kind'    => ['required', Rule::in(InventoryOpeningBalanceImport::ALLOWED_KINDS)],
            'cutover_date' => ['required', 'date', 'before_or_equal:today'],
            'notes'        => ['nullable', 'string', 'max:2000'],

            'rows'                     => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.quantity'          => ['required', 'numeric', 'min:0'],
            'rows.*.unit_cost'         => ['required', 'numeric', 'min:0'],

            // Kind-specific id rule. Only one of these is filled per row;
            // we apply both rules and the unused one is benignly inactive.
            'rows.*.raw_material_id'   => $kind === InventoryOpeningBalanceImport::KIND_RAW_MATERIAL ? $idRule : ['nullable'],
            'rows.*.feed_type_id'      => $kind === InventoryOpeningBalanceImport::KIND_FINISHED_FEED ? $idRule : ['nullable'],
        ];
    }
}
