<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates input for updating a standard-stock pig. Rules preserved from
 * PigManagementController::standardUpdate (was line 372).
 *
 * The controller route param is `{pig}` (model-bound), so the current pig
 * id for the unique-ear-tag exception is read from that route parameter.
 */
class UpdateStandardPigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pig = $this->route('pig');
        $pigId = $pig ? $pig->id : null;

        return [
            'ear_tag_number' => ['required', 'string', 'max:50', 'unique:pigs_management,ear_tag_number,' . $pigId],
            'breed'          => ['required', 'string', 'max:50'],
            'gender'         => ['required', 'in:M,F'],
            'date_of_birth'  => ['required', 'date'],
            'structure_id'   => ['nullable', 'exists:structures,id'],
            'room_id'        => ['nullable', 'exists:rooms,id'],
            'father_id'      => ['nullable', 'exists:pigs_management,id'],
            'mother_id'      => ['nullable', 'exists:pigs_management,id'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}