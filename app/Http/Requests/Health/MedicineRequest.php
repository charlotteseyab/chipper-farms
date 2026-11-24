<?php

namespace App\Http\Requests\Health;

use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicine = $this->route('medicine');

        return (bool) ($medicine ? $this->user()?->can('update', $medicine) : $this->user()?->can('create', Medicine::class));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('allowed_routes'))) {
            $this->merge(['allowed_routes' => array_filter(array_map('trim', explode(',', $this->input('allowed_routes'))))]);
        }
    }

    public function rules(): array
    {
        return [
            'name'                      => ['required', 'string', 'max:255'],
            'formulation'               => ['nullable', 'string', 'max:255'],
            'concentration'             => ['nullable', 'string', 'max:255'],
            'description'               => ['nullable', 'string', 'max:2000'],
            'used_for'                  => ['nullable', 'string', 'max:2000'],
            'storage_instructions'      => ['nullable', 'string', 'max:2000'],
            'precautions'               => ['nullable', 'string', 'max:2000'],
            'dose_unit'                 => ['required', 'string', 'max:20'],
            'stock_unit'                => ['required', 'string', 'max:20'],
            'dose_units_per_stock_unit' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'allowed_routes'            => ['nullable', 'array', 'max:10'],
            'allowed_routes.*'          => ['string', 'max:50'],
            'meat_withdrawal_days'      => ['nullable', 'integer', 'min:0', 'max:365'],
            'withdrawal_source_note'    => ['nullable', 'required_with:meat_withdrawal_days', 'string', 'max:1000'],
            'reorder_level'             => ['nullable', 'numeric', 'min:0'],
            'is_active'                 => ['required', 'boolean'],
            // A photo of the pack or bottle; shown when picking the medicine.
            'photo'                     => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'remove_photo'              => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'withdrawal_source_note.required_with' => 'Say where the withdrawal period comes from, for example the product label.',
            'photo.mimetypes' => 'Use a JPEG, PNG or WebP photo.',
            'photo.max' => 'The photo can be up to 5 MB.',
        ];
    }
}
