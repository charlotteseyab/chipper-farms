<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\InvestorProfitAllocationRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a profit-allocation run request.
 */
class AllocateProfitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_investors') ?? false;
    }

    public function rules(): array
    {
        return [
            'fiscal_year_id'   => ['required', 'integer', 'exists:fiscal_years,id'],
            'method'           => ['nullable', Rule::in([
                InvestorProfitAllocationRun::METHOD_OWNERSHIP_PCT,
                InvestorProfitAllocationRun::METHOD_FIXED_SCHEDULE,
            ])],
            'profit_pool'      => ['nullable', 'numeric', 'gte:0'],
            'allocation_date'  => ['nullable', 'date'],
            'fixed_schedule'   => ['nullable', 'array'],
            'fixed_schedule.*' => ['numeric', 'gte:0'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ];
    }
}
