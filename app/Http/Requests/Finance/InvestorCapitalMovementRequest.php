<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a contribution or distribution amount.
 */
class InvestorCapitalMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_investors') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount'          => ['required', 'numeric', 'gt:0'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'txn_date'        => ['nullable', 'date'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
