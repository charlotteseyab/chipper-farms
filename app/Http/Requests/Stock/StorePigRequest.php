<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates input for creating a new pig (all-stock / breeding / standard).
 * Rules preserved verbatim from PigManagementController::store (was line 287).
 */
class StorePigRequest extends FormRequest
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
            'stock_type'     => ['required', 'in:standard,breeding'],
            'date_of_birth'  => ['required', 'date', 'before_or_equal:today'],
            'ear_tag_number' => ['nullable', 'string', 'max:50'],
            'ear_tag_color_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('ear_tag_colors', 'id')->where(fn ($q) => $q->where('organization_id', $this->user()?->organization_id)->where('is_active', true)->where('normalized_name', '!=', 'unspecified'))],
            'father_id'      => ['nullable', 'exists:pigs_management,id'],
            'mother_id'      => ['nullable', 'exists:pigs_management,id'],
            'room_id'        => ['nullable', 'exists:rooms,id'],
            'group_id'       => ['nullable', 'exists:groups,id'],
            'acquisition_cost'   => ['nullable', 'numeric', 'min:0'],
            'acquisition_date'   => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_source' => ['nullable', 'string', 'max:50'],
        ];
    }
}
