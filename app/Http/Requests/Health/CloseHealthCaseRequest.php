<?php

namespace App\Http\Requests\Health;

use App\Models\HealthRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseHealthCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('followUp', $this->route('healthRecord'));
    }

    public function rules(): array
    {
        return [
            'final_status'  => ['required', Rule::in(['resolved', 'closed'])],
            // "Resolved" means the animal got better and is being watched, so
            // it cannot go with an outcome like died or worsened.
            'outcome'       => ['required', Rule::in(array_keys(HealthRecord::OUTCOMES)),
                Rule::when($this->input('final_status') === 'resolved', [Rule::in(HealthRecord::RESOLVABLE_OUTCOMES)])],
            'closed_at'     => ['required', 'date', 'before_or_equal:now'],
            // When to check the animal again; on the worklist that day.
            'check_on'      => ['nullable', 'required_if:final_status,resolved', 'date', 'after_or_equal:closed_at'],
            'closure_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'outcome.in' => 'Resolved is for an animal that recovered or improved. For any other outcome, choose Closed.',
            'check_on.required_if' => 'Choose when to check the animal again.',
        ];
    }
}
