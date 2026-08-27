<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'receipts'                           => ['required', 'array', 'min:1'],
            'receipts.*.selected'                => ['nullable', 'boolean'],
            'receipts.*.purchase_request_item_id'=> ['required', 'integer', 'distinct', 'exists:purchase_request_items,id'],
            'receipts.*.quantity'                => ['nullable', 'numeric', 'gt:0'],
            'receipts.*.unit_cost'               => ['nullable', 'numeric', 'min:0'],
            'receipts.*.notes'                   => ['nullable', 'string', 'max:500'],
            'reference'                          => ['nullable', 'string', 'max:200'],
            'notes'                              => ['nullable', 'string', 'max:2000'],
            'allow_over_receipt'                 => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $selected = collect($this->input('receipts', []))
                ->filter(fn ($receipt) => is_array($receipt)
                    && filter_var($receipt['selected'] ?? false, FILTER_VALIDATE_BOOLEAN));

            if ($selected->isEmpty()) {
                $validator->errors()->add('receipts', 'Select at least one material received in this delivery.');
            }

            $selected->each(function (array $receipt, $key) use ($validator): void {
                if (! isset($receipt['quantity']) || $receipt['quantity'] === '') {
                    $validator->errors()->add("receipts.{$key}.quantity", 'Enter the quantity received for every selected material.');
                }
                if (! isset($receipt['unit_cost']) || $receipt['unit_cost'] === '') {
                    $validator->errors()->add("receipts.{$key}.unit_cost", 'Enter the actual unit cost for every selected material.');
                }
            });
        }];
    }
}
