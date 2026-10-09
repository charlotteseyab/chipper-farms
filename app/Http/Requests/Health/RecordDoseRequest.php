<?php

namespace App\Http\Requests\Health;

use App\Models\MedicationDosingEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Record a dose (or correct one): medicine, batch, amount, animals, time. Stock and unit rules live in RecordDoseService. */
class RecordDoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('dosingEvent');

        return (bool) ($event ? $this->user()?->can('correct', $event) : $this->user()?->can('create', MedicationDosingEvent::class));
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('healthPig') && ! $this->filled('pig_ids')) {
            $this->merge(['pig_ids' => [$this->route('healthPig')->id]]);
        }
        $this->merge(['dose_amount' => is_string($this->input('dose_amount')) ? trim($this->input('dose_amount')) : $this->input('dose_amount')]);
    }

    public function rules(): array
    {
        $org = (int) $this->user()->organization_id;
        $correcting = $this->route('dosingEvent') !== null;

        return [
            'medicine_id'          => ['required', 'integer', Rule::exists('medicines', 'id')->where('organization_id', $org)],
            'batch_id'             => ['nullable', 'integer', Rule::exists('medicine_batches', 'id')->where('organization_id', $org)],
            // Dates before birth, after the pig left or in the future are checked by the service.
            'administered_at'      => ['required', 'date'],
            'amount_basis'         => ['required', Rule::in(['per_animal', 'total'])],
            'dose_amount'          => ['required', 'regex:/^\d{1,9}(\.\d{1,4})?$/'],
            'dose_unit'            => ['required', 'string', 'max:20'],
            'route'                => ['nullable', 'string', 'max:50'],
            // Who gave the dose; defaults to the person entering it.
            'administered_by'      => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $org)],
            'purpose'              => ['nullable', 'string', 'max:255'],
            'notes'                => ['nullable', 'string', 'max:5000'],
            'health_record_id'     => ['nullable', 'integer', Rule::exists('health_records', 'id')->where('organization_id', $org)],
            'scheduled_dose_ids'   => ['nullable', 'array', 'max:500'],
            'scheduled_dose_ids.*' => ['integer', 'distinct', Rule::exists('scheduled_doses', 'id')->where('organization_id', $org)],
            'pig_ids'              => ['required', 'array', 'min:1', 'max:500'],
            'pig_ids.*'            => ['integer', 'distinct', Rule::exists('pigs_management', 'id')->where('organization_id', $org)],
            'idempotency_key'      => [$correcting ? 'nullable' : 'required', 'uuid'],
            'late_entry_reason'    => ['nullable', 'string', 'max:2000'],
            'correction_reason'    => [$correcting ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pig_ids.required' => 'Choose the animals that were dosed.',
            'dose_amount.regex' => 'Enter the amount as a number with up to 4 decimal places.',
            'idempotency_key.required' => 'The form expired. Reload the page and enter the dose again.',
            'correction_reason.required' => 'Say what was wrong with the original record.',
        ];
    }
}
