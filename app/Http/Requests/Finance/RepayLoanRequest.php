<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a loan repayment's component breakdown. At least one of
 * principal/interest/fee/penalty must be positive (enforced in the service,
 * which throws if the total is zero).
 */
class RepayLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_loans') ?? false;
    }

    public function rules(): array
    {
        return [
            'principal'        => ['nullable', 'numeric', 'gte:0'],
            'interest'         => ['nullable', 'numeric', 'gte:0'],
            'fee'              => ['nullable', 'numeric', 'gte:0'],
            'penalty'          => ['nullable', 'numeric', 'gte:0'],
            'schedule_line_id' => ['nullable', 'integer', 'exists:loan_schedule_lines,id'],
            'bank_account_id'  => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'txn_date'         => ['nullable', 'date'],
        ];
    }
}
