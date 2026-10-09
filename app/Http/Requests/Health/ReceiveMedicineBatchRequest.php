<?php

namespace App\Http\Requests\Health;

use Illuminate\Foundation\Http\FormRequest;

/** Receive a batch of a medicine into stock. */
class ReceiveMedicineBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manageStock', $this->route('medicine'));
    }

    public function rules(): array
    {
        return [
            'batch_number'      => ['required', 'string', 'max:100'],
            'received_at'       => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expiry_date'       => ['required', 'date_format:Y-m-d', 'after_or_equal:received_at'],
            'quantity_received' => ['required', 'regex:/^\d{1,9}(\.\d{1,4})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'unit_cost'         => ['nullable', 'regex:/^\d{1,12}(\.\d{1,6})?$/'],
            'supplier'          => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'expiry_date.after_or_equal' => 'The expiry date is before the date received. Check the label.',
            'quantity_received.not_regex' => 'The quantity must be more than zero.',
            'quantity_received.regex' => 'Enter the quantity as a number with up to 4 decimal places.',
        ];
    }
}
