<?php

namespace App\Http\Requests\Health;

use App\Models\HealthRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Amends one chosen record; also used by the status update, which sends a subset. */
class UpdateHealthRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('healthRecord'));
    }

    public function rules(): array
    {
        $statusOnly = $this->routeIs('health.records.status');

        return [
            'condition'        => [$statusOnly ? 'sometimes' : 'required', 'string', 'max:255'],
            'severity'         => ['nullable', Rule::in(array_keys(HealthRecord::SEVERITIES))],
            'symptoms'         => ['sometimes', 'nullable', 'string', 'max:5000'],
            'diagnosis'        => ['sometimes', 'nullable', 'string', 'max:5000'],
            'treatment_plan'   => ['sometimes', 'nullable', 'string', 'max:5000'],
            'treatment_status' => [$statusOnly ? 'required' : 'nullable', Rule::in(array_keys(HealthRecord::TREATMENT_STATUSES))],
            'outcome'          => ['nullable', Rule::in(array_keys(HealthRecord::OUTCOMES))],
            'resolved_at'      => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
