<?php

namespace App\Http\Requests\Health;

use App\Models\TreatmentCourse;
use App\Models\TreatmentCourseMedicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Prescribe or amend a course: animals, one or more medicines, and their schedule. */
class TreatmentCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('treatmentCourse');

        return (bool) ($course ? $this->user()?->can('change', $course) : $this->user()?->can('create', TreatmentCourse::class));
    }

    public function rules(): array
    {
        $org = (int) $this->user()->organization_id;
        $amending = $this->route('treatmentCourse') !== null;

        return [
            'purpose'                      => [$amending ? 'nullable' : 'required', Rule::in(array_keys(TreatmentCourse::PURPOSES))],
            'health_record_id'             => ['nullable', 'integer', Rule::exists('health_records', 'id')->where('organization_id', $org)],
            'pig_ids'                      => ['required', 'array', 'min:1', 'max:500'],
            'pig_ids.*'                    => ['integer', 'distinct', Rule::exists('pigs_management', 'id')->where('organization_id', $org)->whereNull('deleted_at')],
            'prescriber_name'              => ['nullable', 'string', 'max:255'],
            'responsible_user_id'          => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $org)],
            'follow_up_on'                 => ['nullable', 'date_format:Y-m-d'],
            'notes'                        => ['nullable', 'string', 'max:5000'],
            'medicines'                    => ['required', 'array', 'min:1', 'max:10'],
            'medicines.*.medicine_id'      => ['required', 'integer', Rule::exists('medicines', 'id')->where('organization_id', $org)],
            'medicines.*.dose_amount'      => ['required', 'numeric', 'gt:0', 'max:100000'],
            'medicines.*.dose_unit'        => ['required', 'string', 'max:20'],
            'medicines.*.route'            => ['nullable', 'string', 'max:50'],
            'medicines.*.frequency'        => ['required', Rule::in(array_keys(TreatmentCourseMedicine::FREQUENCIES))],
            'medicines.*.interval'         => ['nullable', 'integer', 'min:1', 'max:365'],
            'medicines.*.number_of_doses'  => ['nullable', 'integer', 'min:1', 'max:200'],
            'medicines.*.start_at'         => ['required', 'date'],
            'medicines.*.instructions'     => ['nullable', 'string', 'max:2000'],
            'amendment_reason'             => [$amending ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['pig_ids.required' => 'Choose the animals this course is for.', 'amendment_reason.required' => 'Say why the course is being changed.'];
    }
}
