<?php

namespace App\Http\Requests\Health;

use App\Models\MedicineStockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add, remove or count one batch's stock, with a reason. */
class AdjustMedicineStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manageStock', $this->route('medicineBatch')?->medicine);
    }

    public function rules(): array
    {
        return [
            'type'     => ['required', Rule::in(array_keys(MedicineStockAdjustment::TYPES))],
            // A count may be zero (the batch is used up); add and remove need an amount.
            'quantity' => ['required', 'regex:/^\d{1,9}(\.\d{1,4})?$/', Rule::when($this->input('type') !== 'count', ['not_regex:/^0+(\.0+)?$/'])],
            'reason'   => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['quantity.not_regex' => 'The amount must be more than zero.', 'reason.required' => 'Say why the stock is changing.'];
    }
}
