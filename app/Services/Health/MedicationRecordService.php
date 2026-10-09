<?php

namespace App\Services\Health;

use App\Models\MedicationAdministration;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;

/**
 * Corrections for legacy doses: those recorded as free text before the
 * health upgrade, which never moved stock. New doses go through
 * RecordDoseService and are corrected with CorrectDoseService.
 *
 * Write path for medication doses. A dose is never edited in place or
 * deleted, because its withdrawal period decides whether the pig may be
 * sold. A mistake is corrected (the original is voided and a replacement
 * linked to it) or voided; both keep the original readable and are audited.
 */
class MedicationRecordService
{
    public const FIELDS = [
        'administration_date', 'medication_name', 'dosage', 'purpose', 'route',
        'withdrawal_period_days', 'notes',
    ];

    public function __construct(
        protected HealthEntryDateGuard $dates,
        protected AuditTrailService $audit,
    ) {
    }

    /** Void the original and save the corrected dose, linked to it. Returns the replacement. */
    public function correct(User $actor, MedicationAdministration $original, array $data, string $reason): MedicationAdministration
    {
        $pig = $original->pig;
        $this->dates->check($actor, $pig, $data['administration_date'], $this->dates->hasLeft($pig) ? $reason : null, 'administration_date');

        return DB::transaction(function () use ($actor, $original, $data, $reason, $pig) {
            $original = MedicationAdministration::whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($original->isVoided()) {
                throw new \DomainException('This medication record has already been corrected or voided.');
            }
            $this->assertLegacy($original);

            $before = $this->snapshot($original);
            $original->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => 'Corrected: '.$reason,
                'status' => MedicationAdministration::STATUS_CORRECTED]);

            // The person who gave the dose does not change because someone
            // else fixed a typo in the record of it.
            $replacement = MedicationAdministration::create($this->fields($data) + [
                'pig_id'            => $original->pig_id,
                'administered_by'   => $original->administered_by,
                'corrects_id'       => $original->id,
                'correction_reason' => $reason,
            ]);

            $this->audit->log('health', 'medication_corrected', [
                'user'            => $actor,
                'auditable'       => $replacement,
                'organization_id' => $pig?->organization_id,
                'record_label'    => $pig?->code,
                'description'     => "Medication record #{$original->id} corrected as #{$replacement->id}: {$reason}",
                'old_values'      => $before,
                'new_values'      => $this->snapshot($replacement),
                'metadata'        => ['corrects_id' => $original->id],
            ]);

            return $replacement;
        });
    }

    public function void(User $actor, MedicationAdministration $dose, string $reason): MedicationAdministration
    {
        return DB::transaction(function () use ($actor, $dose, $reason) {
            $dose = MedicationAdministration::whereKey($dose->id)->lockForUpdate()->firstOrFail();
            if ($dose->isVoided()) {
                throw new \DomainException('This medication record has already been corrected or voided.');
            }
            $this->assertLegacy($dose);

            $dose->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason,
                'status' => MedicationAdministration::STATUS_VOIDED]);

            $this->audit->log('health', 'medication_voided', [
                'user'            => $actor,
                'auditable'       => $dose,
                'organization_id' => $dose->resolveOrganizationId(),
                'record_label'    => $dose->pig?->code,
                'description'     => "Medication record #{$dose->id} ({$dose->medication_name}) voided: {$reason}",
                'old_values'      => $this->snapshot($dose) + ['voided_at' => null],
                'new_values'      => ['voided_at' => (string) $dose->voided_at, 'void_reason' => $reason],
            ]);

            return $dose;
        });
    }

    /** Doses recorded against stock are corrected as a whole dose record, so the stock goes back too. */
    private function assertLegacy(MedicationAdministration $dose): void
    {
        if ($dose->medication_dosing_event_id) {
            throw new \DomainException('This dose took medicine from stock. Correct or void it from its dose record so the stock is put back.');
        }
    }

    private function fields(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FIELDS));
    }

    private function snapshot(MedicationAdministration $dose): array
    {
        return collect($dose->only([...self::FIELDS, 'withdrawal_ends_on']))
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)
            ->all();
    }
}
