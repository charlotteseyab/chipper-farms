<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceiveAgainstPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('receive_against_pr');
    }

    public function rules(): array
    {
        return [
            'purchase_request_item_id' => ['required', 'integer', 'exists:purchase_request_items,id'],
            'quantity'                 => ['required', 'numeric', 'gt:0'],
            'unit_cost'                => ['required', 'numeric', 'min:0'],
            'reference'                => ['nullable', 'string', 'max:200'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'allow_over_receipt'       => ['nullable', 'boolean'],
        ];
    }
}
