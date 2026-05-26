<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\PeriodCloseRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 6 — validate a period close submission.
 *
 * overrides is a map of check_key => reason. Only soft (overridable) checks
 * may be overridden; the service rejects attempts to override hard checks.
 */
class ClosePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_period_close') ?? false;
    }

    public function rules(): array
    {
        return [
            'close_level'   => ['nullable', Rule::in([PeriodCloseRun::LEVEL_SOFT, PeriodCloseRun::LEVEL_HARD])],
            'overrides'     => ['nullable', 'array'],
            'overrides.*'   => ['nullable', 'string', 'max:1000'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Drop empty override reasons so they don't count as overrides.
        if ($this->has('overrides') && is_array($this->input('overrides'))) {
            $clean = array_filter(
                $this->input('overrides'),
                fn ($reason) => is_string($reason) && trim($reason) !== '',
            );
            $this->merge(['overrides' => $clean]);
        }
    }
}
