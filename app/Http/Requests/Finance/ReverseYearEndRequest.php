<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 — validate a year-end reversal. Reason mandatory.
 */
class ReverseYearEndRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('override_period_close') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
