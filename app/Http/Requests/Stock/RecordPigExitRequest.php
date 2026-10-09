<?php

namespace App\Http\Requests\Stock;

use App\Models\EarTag;
use App\Models\PigExit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPigExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PigExit::class);
    }

    public function rules(): array
    {
        $type = $this->input('exit_type');

        return [
            'exit_type'           => ['required', Rule::in(array_keys(PigExit::TYPES))],
            'exit_date'           => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason'              => ['required', Rule::in(array_keys(PigExit::REASONS[$type] ?? []))],
            'reason_other'        => ['nullable', 'required_if:reason,other', 'string', 'max:255'],
            'destination'         => [$type === PigExit::TRANSFER_OUT ? 'required' : 'nullable', 'string', 'max:255'],
            'disposal'            => [$type === PigExit::CULL ? 'required' : 'nullable', Rule::in(array_keys(PigExit::DISPOSALS))],
            'final_weight'        => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'tag_outcome'         => ['required', Rule::in([EarTag::AVAILABLE, EarTag::PENDING_RECOVERY, EarTag::LOST, EarTag::DAMAGED, EarTag::RETIRED])],
            'tag_condition_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination.required' => 'Say which farm or owner the pig is going to.',
            'disposal.required'    => 'Say what happened to the culled animal.',
            'reason_other.required_if' => 'Describe the reason.',
        ];
    }
}
