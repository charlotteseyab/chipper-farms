<?php

namespace App\Http\Requests\Health;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Correcting a legacy (free-text) dose. Doses recorded against stock are
 * corrected through RecordDoseRequest on their dose record instead.
 */
class CorrectMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('correct', $this->route('medication'));
    }

    public function rules(): array
    {
        return [
            'administration_date'    => ['required', 'date_format:Y-m-d'],
            'medication_name'        => ['required', 'string', 'max:255'],
            'dosage'                 => ['required', 'string', 'max:255'],
            'purpose'                => ['nullable', 'string', 'max:255'],
            'route'                  => ['nullable', 'string', 'max:100'],
            // As printed on the label. Blank means none was recorded, which is
            // shown as a caution, never as "safe". Above a year is a typo.
            'withdrawal_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            'correction_reason'      => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['correction_reason.required' => 'Say what was wrong with the original record.'];
    }
}
