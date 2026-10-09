<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates input for updating a pig via the legacy breeding-stocks resource
 * update endpoint. Rules preserved from PigManagementController::update
 * (was line 632).
 */
class UpdateBreedingPigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'breed'          => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Setting::animalBreeds()))],
            'gender'         => ['required', 'in:M,F'],
            'stock_type'     => ['required', 'in:breeding,standard'],
            'date_of_birth'  => ['required', 'date', 'before_or_equal:today'],
            'ear_tag_number' => [
                'required',
                'string',
                'max:50',
            ],
            'father_id'      => ['nullable', 'exists:pigs_management,id'],
            'mother_id'      => ['nullable', 'exists:pigs_management,id'],
            'room_id'        => ['nullable', 'exists:rooms,id'],
        ];
    }

}
