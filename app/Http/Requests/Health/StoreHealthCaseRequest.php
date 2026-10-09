<?php

namespace App\Http\Requests\Health;

use App\Models\HealthRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Open a health case for one animal or a group (frozen list of animals). */
class StoreHealthCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('manage_health_records');
    }

    protected function prepareForValidation(): void
    {
        // The per-pig form sends the pig in the route; the older form sends record_date only.
        if ($this->route('healthPig')) {
            $this->merge(['pig_ids' => [$this->route('healthPig')->id]]);
        } elseif ($this->filled('pig_id') && ! $this->filled('pig_ids')) {
            $this->merge(['pig_ids' => [$this->input('pig_id')]]);
        }
        if (! $this->filled('observed_at') && $this->filled('record_date')) {
            $this->merge(['observed_at' => $this->input('record_date').' 00:00']);
        }
    }

    public function rules(): array
    {
        $org = (int) $this->user()->organization_id;

        return [
            'pig_ids'             => ['required', 'array', 'min:1', 'max:500'],
            'pig_ids.*'           => ['integer', 'distinct', Rule::exists('pigs_management', 'id')->where('organization_id', $org)],
            'group_label'         => ['nullable', 'string', 'max:255'],
            // Future dates, and dates before birth or after the pig left, are checked by HealthEntryDateGuard.
            'observed_at'         => ['required', 'date'],
            'record_date'         => ['nullable', 'date_format:Y-m-d'],
            'condition'           => ['required', 'string', 'max:255'],
            'severity'            => ['nullable', Rule::in(array_keys(HealthRecord::SEVERITIES))],
            'symptoms'            => ['nullable', 'string', 'max:5000'],
            'diagnosis'           => ['nullable', 'string', 'max:5000'],
            'diagnosis_status'    => ['nullable', Rule::in(array_keys(HealthRecord::DIAGNOSIS_STATUSES))],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $org)],
            'treatment_plan'      => ['nullable', 'string', 'max:5000'],
            'treatment_status'    => ['nullable', Rule::in(array_keys(HealthRecord::TREATMENT_STATUSES))],
            'outcome'             => ['nullable', Rule::in(array_keys(HealthRecord::OUTCOMES))],
            'resolved_at'         => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'resolution_notes'    => ['nullable', 'string', 'max:5000'],
            'late_entry_reason'   => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['pig_ids.required' => 'Choose the animal or animals this case is about.'];
    }
}
