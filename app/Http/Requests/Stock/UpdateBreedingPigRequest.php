<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates input for updating a pig via the legacy breeding-stocks resource
 * update endpoint. Rules preserved from PigManagementController::update
 * (was line 632). Note: the original method accepts an $id (integer) not a
 * model binding, so the controller must pass the id manually.
 */
class UpdateBreedingPigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pigId = $this->route('breedingStock') ?? $this->route('id');

        return [
            'breed'          => ['required', 'in:LW,DR,LWDR'],
            'gender'         => ['required', 'in:M,F'],
            'stock_type'     => ['required', 'in:breeding,standard'],
            'date_of_birth'  => ['required', 'date', 'before_or_equal:today'],
            'ear_tag_number' => ['required', 'string', 'max:50', 'unique:pigs_management,ear_tag_number,' . $pigId],
            'father_id'      => ['nullable', 'exists:pigs_management,id'],
            'mother_id'      => ['nullable', 'exists:pigs_management,id'],
            'room_id'        => ['nullable', 'exists:rooms,id'],
        ];
    }
}