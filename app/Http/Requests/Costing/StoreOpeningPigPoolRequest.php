<?php

namespace App\Http\Requests\Costing;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpeningPigPoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('manage_opening_pig_pool');
    }

    public function rules(): array
    {
        return [
            'cutover_date'              => ['required', 'date', 'before_or_equal:today'],
            'notes'                     => ['nullable', 'string', 'max:2000'],

            'rows'                      => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.group_id'           => ['required', 'integer', 'exists:groups,id'],
            'rows.*.pig_count'          => ['required', 'integer', 'min:0', 'max:100000'],
            'rows.*.total_weight_kg'    => ['required', 'numeric', 'min:0'],
            'rows.*.total_value'        => ['required', 'numeric', 'min:0'],
        ];
    }
}
