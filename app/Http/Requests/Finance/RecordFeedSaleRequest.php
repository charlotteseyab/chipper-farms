<?php

namespace App\Http\Requests\Finance;

use App\Models\FeedSale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a feed-sale record request.
 */
class RecordFeedSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('record_feed_sales') ?? false;
    }

    public function rules(): array
    {
        return [
            'feed_type_id'    => ['required', 'integer', 'exists:feed_types,id'],
            'quantity_kg'     => ['required', 'numeric', 'gt:0'],
            'unit_price'      => ['required', 'numeric', 'gte:0'],
            'sale_date'       => ['nullable', 'date'],
            'channel'         => ['nullable', Rule::in([FeedSale::CHANNEL_DIRECT, FeedSale::CHANNEL_STOREFRONT])],
            'payment_status'  => ['nullable', Rule::in([FeedSale::PAYMENT_PENDING, FeedSale::PAYMENT_PAID, FeedSale::PAYMENT_PARTIAL])],
            'payment_method'  => ['nullable', 'string', 'max:50'],
            'counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
            'buyer_name'      => ['nullable', 'string', 'max:200'],
            'buyer_contact'   => ['nullable', 'string', 'max:200'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }
}
