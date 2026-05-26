<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Investor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_investors') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:200'],
            'reference'               => ['nullable', 'string', 'max:50'],
            'investor_class'          => ['required', Rule::in([Investor::CLASS_EQUITY, Investor::CLASS_PREFERRED])],
            'counterparty_id'         => ['nullable', 'integer', 'exists:counterparties,id'],
            'linked_loan_id'          => ['nullable', 'integer', 'exists:loans,id'],
            'ownership_percentage'    => ['nullable', 'numeric', 'gte:0', 'lte:1'], // fraction
            'preferred_share_capital' => ['nullable', 'numeric', 'gte:0'],
            'preferred_return_rate'   => ['nullable', 'numeric', 'gte:0', 'lte:5'],
            'contact_email'           => ['nullable', 'email', 'max:200'],
            'contact_phone'           => ['nullable', 'string', 'max:50'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
