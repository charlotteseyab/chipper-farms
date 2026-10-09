<?php

namespace App\Http\Requests\Health;

use Illuminate\Foundation\Http\FormRequest;

/** Voiding a health record or a dose: the policy decides who, this asks why. */
class VoidHealthEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('healthRecord') ?? $this->route('medication');

        return $entry !== null && (bool) $this->user()?->can('void', $entry);
    }

    public function rules(): array
    {
        return ['void_reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['void_reason.required' => 'Say why this record is being voided.'];
    }
}
