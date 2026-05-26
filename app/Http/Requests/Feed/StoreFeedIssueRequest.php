<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('manage_feed_issues');
    }

    public function rules(): array
    {
        return [
            'group_id'     => ['required', 'integer', 'exists:groups,id'],
            'feed_type_id' => ['required', 'integer', 'exists:feed_types,id'],
            'quantity_kg'  => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'issued_at'    => ['nullable', 'date', 'before_or_equal:now'],
            'reference'    => ['nullable', 'string', 'max:200'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ];
    }
}
