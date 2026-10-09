<?php

namespace App\Http\Requests\Feed;

use App\Services\Feed\FeedStageResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One feeding round: a row per feeding stage. Rows left blank are skipped.
 */
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
        $organizationId = $this->user()?->organization_id;

        return [
            'issued_at'                  => ['nullable', 'date', 'before_or_equal:now'],
            'reference'                  => ['nullable', 'string', 'max:200'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
            'entries'                    => ['required', 'array', 'min:1'],
            'entries.*.stage'            => ['required', 'distinct', Rule::in(array_keys(FeedStageResolver::STAGES))],
            'entries.*.submission_key'   => ['nullable', 'uuid'],
            'entries.*.quantity_kg'      => ['nullable', 'decimal:0,4', 'numeric', 'gt:0', 'max:1000000'],
            'entries.*.feed_type_id'     => ['nullable', 'required_with:entries.*.quantity_kg', 'integer',
                Rule::exists('feed_types', 'id')->where('organization_id', $organizationId)],
        ];
    }

    public function messages(): array
    {
        return [
            'entries.*.feed_type_id.required_with' => 'Choose the feed given to each stage you entered a quantity for.',
            'entries.*.quantity_kg.gt'             => 'Feed quantities must be more than 0 kg.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if ($this->filledEntries() === []) {
                    $validator->errors()->add('entries', 'Enter the kg fed for at least one stage.');
                }
            },
        ];
    }

    /**
     * Rows that have a quantity entered.
     *
     * @return array<int, array{stage: string, feed_type_id: int, quantity_kg: float, submission_key: ?string}>
     */
    public function filledEntries(): array
    {
        return collect($this->input('entries', []))
            ->filter(fn ($entry) => is_array($entry) && ($entry['quantity_kg'] ?? '') !== '' && $entry['quantity_kg'] !== null)
            ->map(fn ($entry) => [
                'stage'          => (string) $entry['stage'],
                'feed_type_id'   => (int) $entry['feed_type_id'],
                'quantity_kg'    => (float) $entry['quantity_kg'],
                'submission_key' => $entry['submission_key'] ?? null,
            ])
            ->values()
            ->all();
    }
}
