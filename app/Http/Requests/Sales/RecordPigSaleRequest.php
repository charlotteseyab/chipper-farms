<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for recording a new pig sale.
 *
 * Extracted from the inline ->validate() call in PigManagementController::recordSale()
 * as part of Phase 0.2. The rules below are the same rules that were in the
 * controller, with one deliberate change:
 *
 *   - The legacy `sold_by` field is no longer accepted from the request.
 *     The seller is always the authenticated user; allowing it to be overridden
 *     by form input was always wrong (it was the root of the sold_by audit bug).
 *     We drop the field from validation rather than leave a silent accept.
 *
 * Authorization is deferred to the controller + PigSalePolicy (introduced in
 * Phase 0.3). Returning true here means "the payload is well-formed"; the
 * policy layer decides "is this actor allowed to do this".
 */
class RecordPigSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_date' => ['required', 'date', 'before_or_equal:today'],
            'sale_weight' => ['required', 'numeric', 'min:0'],
            'weight_type' => ['required', 'in:live,carcass'],

            // Buyer info is optional per existing product behaviour.
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_contact' => ['nullable', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string', 'max:1000'],

            'sale_purpose' => ['required', 'in:market,breeding,slaughter,export,other'],
            'sale_purpose_other' => ['nullable', 'required_if:sale_purpose,other', 'string', 'max:255'],

            'payment_method' => ['nullable', 'in:cash,check,bank_transfer,mobile_money,other'],
            'payment_method_other' => ['nullable', 'required_if:payment_method,other', 'string', 'max:255'],

            'payment_status' => ['required', 'in:pending,paid,partial'],

            'notes' => ['nullable', 'string', 'max:1000'],

            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'transport_method' => ['nullable', 'in:pickup,delivery,farm_gate,other'],
            'tag_outcome' => ['sometimes', 'in:available,pending_recovery,lost,damaged,retired'],
            'tag_condition_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
