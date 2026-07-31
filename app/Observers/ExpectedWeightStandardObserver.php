<?php

namespace App\Observers;

use App\Models\ExpectedWeightStandard;
use App\Services\Audit\AuditTrailService;

class ExpectedWeightStandardObserver
{
    private const AUDITED_FIELDS = [
        'breed',
        'gender',
        'stock_type',
        'week_number',
        'expected_min_weight',
        'expected_max_weight',
        'optimal_weight',
        'notes',
        'is_active',
    ];

    public function __construct(protected AuditTrailService $audit)
    {
    }

    public function created(ExpectedWeightStandard $standard): void
    {
        $this->audit->log('weight_standards', 'created', [
            'auditable' => $standard,
            'record_label' => $this->label($standard),
            'description' => 'Expected weight standard created.',
            'new_values' => $standard->only(self::AUDITED_FIELDS),
        ]);
    }

    public function updated(ExpectedWeightStandard $standard): void
    {
        $changes = collect($standard->getChanges())
            ->only(self::AUDITED_FIELDS)
            ->all();

        if ($changes === []) {
            return;
        }

        $before = [];
        foreach (array_keys($changes) as $field) {
            $before[$field] = $standard->getRawOriginal($field);
        }

        $this->audit->logModelChanges(
            $standard,
            'weight_standards',
            'updated',
            $before,
            $changes,
            [
                'record_label' => $this->label($standard),
                'description' => 'Expected weight standard updated.',
            ]
        );
    }

    public function deleted(ExpectedWeightStandard $standard): void
    {
        $this->audit->log('weight_standards', 'deleted', [
            'auditable_type' => ExpectedWeightStandard::class,
            'auditable_id' => $standard->id,
            'record_label' => $this->label($standard),
            'description' => 'Expected weight standard deleted.',
            'old_values' => $standard->only(self::AUDITED_FIELDS),
        ]);
    }

    private function label(ExpectedWeightStandard $standard): string
    {
        return $standard->breed_display.' · '.$standard->gender_display.' · '
            .$standard->stock_type_display.' · Week '.$standard->week_number;
    }
}
