<?php

namespace App\Http\Requests\Health;

use App\Models\HealthProgramme;
use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HealthProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_treatment_courses');
    }

    public function rules(): array
    {
        $org = (int) $this->user()->organization_id;

        return [
            'name'                => ['required', 'string', 'max:255'],
            'purpose'             => ['required', Rule::in(array_keys(HealthProgramme::PURPOSES))],
            'medicine_id'         => ['required', 'integer', Rule::exists('medicines', 'id')->where('organization_id', $org)->where('is_active', true)],
            'dose_amount'         => ['required', 'numeric', 'gt:0', 'max:100000'],
            'route'               => ['nullable', 'string', 'max:50'],
            'instructions'        => ['nullable', 'string', 'max:2000'],
            'trigger'             => ['required', Rule::in(array_keys(HealthProgramme::TRIGGERS))],
            'days'                => ['required', 'integer', 'min:0', 'max:3650'],
            'applies_to'          => ['required', Rule::in(array_keys(HealthProgramme::APPLIES_TO))],
            'due_time'            => ['required', 'date_format:H:i'],
            'starts_on'           => ['required', 'date_format:Y-m-d'],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $org)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('trigger') === 'interval' && (int) $this->input('days') < 1) {
                $validator->errors()->add('days', 'Repeat at least every 1 day.');
            }
            $medicine = Medicine::find($this->input('medicine_id'));
            $routes = (array) ($medicine?->allowed_routes ?? []);
            if ($routes !== [] && $this->filled('route') && ! in_array($this->input('route'), $routes, true)) {
                $validator->errors()->add('route', "{$medicine->name} is given by: ".implode(', ', $routes).'.');
            }
        }];
    }

    /** The dose unit is the medicine's own (decision D5); breeding-cycle rules only ever apply to sows. */
    public function programme(): array
    {
        $data = $this->validated();
        $data['dose_unit'] = Medicine::findOrFail($data['medicine_id'])->dose_unit;
        if (in_array($data['trigger'], HealthProgramme::BREEDING_TRIGGERS, true)) {
            $data['applies_to'] = 'breeding_females';
        }

        return $data;
    }

    public function messages(): array
    {
        return ['days.required' => 'Say how many days.', 'medicine_id.exists' => 'Choose an active medicine from the catalogue.'];
    }
}
