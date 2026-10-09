<?php

namespace App\Http\Requests\Health;

use App\Models\VetExamination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VetExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('vetVisit'));
    }

    public function rules(): array
    {
        $org = (int) $this->user()->organization_id;

        return [
            'pig_id'               => ['required', 'integer', Rule::exists('pigs_management', 'id')->where('organization_id', $org)],
            'examined_at'          => ['nullable', 'date', 'before_or_equal:now'],
            'assessment'           => ['required', Rule::in(array_keys(VetExamination::ASSESSMENTS))],
            'findings'             => ['nullable', 'string', 'max:5000'],
            'assessment_text'      => ['nullable', 'string', 'max:5000'],
            'vet_notes'            => ['nullable', 'string', 'max:5000'],
            'recommendations'      => ['nullable', 'string', 'max:5000'],
            'tests_requested'      => ['nullable', 'string', 'max:2000'],
            'test_results'         => ['nullable', 'string', 'max:5000'],
            'follow_up_on'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'follow_up_not_needed' => ['nullable', 'boolean'],
            'is_repeat'            => ['nullable', 'boolean'],
            'open_case'            => ['nullable', 'boolean'],
            'case_condition'       => ['nullable', 'string', 'max:255'],
            'health_record_id'     => ['nullable', 'integer', Rule::exists('health_records', 'id')->where('organization_id', $org)],
            'amendment_reason'     => ['nullable', 'string', 'max:2000'],
        ];
    }
}
