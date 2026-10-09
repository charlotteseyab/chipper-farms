<?php

namespace App\Http\Requests\Health;

use App\Models\HealthFollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HealthFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('followUp', $this->route('healthRecord'));
    }

    public function rules(): array
    {
        return [
            'observed_at'              => ['required', 'date', 'before_or_equal:now'],
            'response'                 => ['nullable', Rule::in(array_keys(HealthFollowUp::RESPONSES))],
            'findings'                 => ['nullable', 'string', 'max:5000'],
            'complications'            => ['nullable', 'string', 'max:5000'],
            'adverse_reaction'         => ['nullable', 'boolean'],
            'adverse_reaction_details' => ['nullable', 'required_if:adverse_reaction,1', 'string', 'max:5000'],
        ];
    }
}
