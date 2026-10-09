<?php

namespace App\Http\Requests\Health;

use App\Models\ScheduledDose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mark a scheduled dose missed or cancelled. */
class CloseScheduledDoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('close', $this->route('scheduledDose'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ScheduledDose::MISSED, ScheduledDose::CANCELLED])],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
