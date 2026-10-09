<?php

namespace App\Services\Health;

use App\Models\Medicine;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the farm's medicine catalogue. Withdrawal days come from the product
 * label and are entered by someone with the catalogue permission; blank means
 * none was recorded. Every change is audited. Doses copy the values when
 * given, so changing a medicine never rewrites a past dose.
 */
class MedicineCatalogueService
{
    public const FIELDS = [
        'name', 'formulation', 'concentration', 'dose_unit', 'stock_unit', 'dose_units_per_stock_unit',
        'allowed_routes', 'meat_withdrawal_days', 'withdrawal_source_note', 'reorder_level', 'is_active',
    ];

    public function __construct(protected AuditTrailService $audit)
    {
    }

    public function save(User $actor, array $data, ?Medicine $medicine = null): Medicine
    {
        $data['formulation'] = trim((string) ($data['formulation'] ?? ''));
        $data['concentration'] = trim((string) ($data['concentration'] ?? ''));
        $data['allowed_routes'] = array_values(array_filter(array_map('trim', (array) ($data['allowed_routes'] ?? []))));

        return DB::transaction(function () use ($actor, $data, $medicine) {
            $duplicate = Medicine::where('name', $data['name'])->where('formulation', $data['formulation'])
                ->where('concentration', $data['concentration'])->when($medicine, fn ($q) => $q->whereKeyNot($medicine->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'This medicine is already in the catalogue.']);
            }

            // Stock on hand is counted in these units; changing them would silently change what it means.
            if ($medicine && $medicine->batches()->exists()) {
                foreach (['dose_unit' => 'dose unit', 'stock_unit' => 'stock unit', 'dose_units_per_stock_unit' => 'conversion'] as $field => $label) {
                    if (! array_key_exists($field, $data)) {
                        continue;
                    }
                    $changed = $field === 'dose_units_per_stock_unit'
                        ? bccomp((string) $data[$field], (string) $medicine->{$field}, 4) !== 0
                        : (string) $data[$field] !== (string) $medicine->{$field};
                    if ($changed) {
                        throw ValidationException::withMessages([$field => "Batches of this medicine are already in stock, so its {$label} cannot change. Add the new presentation as a separate medicine."]);
                    }
                }
            }

            $before = $medicine?->only(self::FIELDS) ?? [];
            $fields = array_intersect_key($data, array_flip(self::FIELDS));
            if ($medicine) {
                $medicine->update($fields);
            } else {
                $medicine = Medicine::create($fields + ['created_by' => $actor->id]);
            }

            $after = $medicine->only(self::FIELDS);
            $this->audit->log('health', $before === [] ? 'medicine_added' : 'medicine_updated', [
                'user' => $actor, 'auditable' => $medicine, 'organization_id' => $medicine->organization_id,
                'record_label' => $medicine->name,
                'description' => ($before === [] ? 'Medicine added: ' : 'Medicine updated: ').$medicine->display_name
                    .((($before['meat_withdrawal_days'] ?? null) !== ($after['meat_withdrawal_days'] ?? null) && $before !== []) ? '. Withdrawal days changed; past doses keep the days they were given with.' : '.'),
                'old_values' => $before === [] ? null : array_map(fn ($v) => is_array($v) ? implode(', ', $v) : $v, $before),
                'new_values' => array_map(fn ($v) => is_array($v) ? implode(', ', $v) : $v, $after),
            ]);

            return $medicine;
        });
    }
}
