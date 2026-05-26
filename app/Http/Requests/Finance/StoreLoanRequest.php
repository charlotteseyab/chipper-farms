<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new loan + its terms. The schedule is generated from these.
 */
class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_loans') ?? false;
    }

    public function rules(): array
    {
        return [
            'reference'            => ['required', 'string', 'max:50'],
            'loan_type'            => ['required', Rule::in(array_keys((array) config('loans.types')))],
            'lender_name'          => ['required', 'string', 'max:200'],
            'counterparty_id'      => ['nullable', 'integer', 'exists:counterparties,id'],
            'principal_amount'     => ['required', 'numeric', 'gt:0'],
            'annual_interest_rate' => ['required', 'numeric', 'gte:0', 'lte:5'], // 0..500%
            'term_months'          => ['required', 'integer', 'min:1', 'max:600'],
            'amortisation_method'  => ['required', Rule::in([Loan::METHOD_REDUCING_BALANCE, Loan::METHOD_STRAIGHT_LINE])],
            'disbursement_date'    => ['nullable', 'date'],
            'first_payment_date'   => ['required', 'date'],
            'origination_fee'      => ['nullable', 'numeric', 'gte:0'],
            'bank_account_id'      => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }
}
