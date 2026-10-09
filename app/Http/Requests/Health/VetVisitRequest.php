<?php

namespace App\Http\Requests\Health;

use App\Models\VetVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VetVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('vetVisit');

        return (bool) ($visit ? $this->user()?->can('update', $visit) : $this->user()?->can('create', VetVisit::class));
    }

    public function rules(): array
    {
        $org = (int) $this->user()->organization_id;

        return [
            'visited_at'        => ['required', 'date', 'before_or_equal:now'],
            'veterinarian_name' => ['required', 'string', 'max:255'],
            'clinic'            => ['nullable', 'string', 'max:255'],
            'contact'           => ['nullable', 'string', 'max:255'],
            'room_id'           => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('organization_id', $org)],
            'location'          => ['nullable', 'string', 'max:255'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'general_notes'     => ['nullable', 'string', 'max:10000'],
            'recommendations'   => ['nullable', 'string', 'max:10000'],
            'planned_pig_ids'   => ['nullable', 'array', 'max:500'],
            'planned_pig_ids.*' => ['integer', 'distinct', Rule::exists('pigs_management', 'id')->where('organization_id', $org)],
            'amendment_reason'  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
