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
            'outcome'       => ['required', Rule::in(array_keys(HealthRecord::OUTCOMES))],
            'closed_at'     => ['required', 'date', 'before_or_equal:now'],
            'closure_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
