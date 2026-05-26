<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for updating the payment status of a pig sale.
 * Rules preserved from the inline validation in
 * PigManagementController::updatePaymentStatus (was line 2441).
 */
class UpdatePaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // Authorization handled by policy.
    }

    public function rules(): array
    {
        return [
            'payment_status' => ['required', 'in:pending,paid,partial'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}