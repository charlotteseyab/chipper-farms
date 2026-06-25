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
            'group_id'     => ['nullable', 'required_without:new_group_name', 'integer', 'exists:groups,id'],
            'new_group_name' => ['nullable', 'required_without:group_id', 'string', 'max:120'],
            'new_group_type' => ['nullable', 'string', 'max:60'],
            'feed_type_id' => ['required', 'integer', 'exists:feed_types,id'],
            'quantity_kg'  => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'issued_at'    => ['nullable', 'date', 'before_or_equal:now'],
            'reference'    => ['nullable', 'string', 'max:200'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ];
    }
}
