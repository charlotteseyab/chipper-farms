<?php

namespace App\Console\Commands;

use App\Models\HealthRecord;
use App\Models\HealthRecordAnimal;
use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings health records and doses recorded before the upgrade into the new
 * model (health upgrade plan, section 5). Dry run by default: prints what
 * would change and the records that need a person to look at them. Safe to
 * run again; rows already filled are left alone.
 *
 * It never creates stock movements, never guesses a batch, and leaves unknown
 * values (dose amounts, diagnosis confirmation) blank rather than inventing them.
 */
class BackfillHealthRecords extends Command
{
    protected $signature = 'health:backfill
                            {--apply : Write the changes. Without this nothing is changed.}
                            {--organization= : Limit to one organisation ID.}';

    protected $description = 'Backfill existing health records and doses into the upgraded health model (dry run unless --apply).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $orgFilter = $this->option('organization');
        $counts = array_fill_keys(['observed_at', 'animals', 'case_status', 'doses_legacy'], 0);
        $review = [];

        $records = HealthRecord::withoutGlobalScopes()->withTrashed()
            ->when($orgFilter, fn ($q) => $q->where('organization_id', $orgFilter))
            ->orderBy('id')->get();

        $pigs = PigManagement::withoutGlobalScopes()->withTrashed()->whereIn('id', $records->pluck('pig_id')->unique())->get()->keyBy('id');

        $work = function () use ($records, $pigs, $apply, &$counts, &$review, $orgFilter) {
            foreach ($records as $record) {
                $pig = $pigs->get($record->pig_id);
                if ($pig === null || $record->organization_id === null || (int) $pig->organization_id !== (int) $record->organization_id) {
                    $review[] = ['Health record', $record->id, $record->organization_id ?? '—', 'Pig missing or in another farm; not attached to anyone.'];

                    continue;
                }

                $changes = [];
                if ($record->observed_at === null) {
                    $changes['observed_at'] = $record->record_date->format('Y-m-d').' 00:00:00';
                    $counts['observed_at']++;
                }
                if ($record->case_status === null) {
                    [$status, $note] = $this->caseStatusFor($record);
                    $changes['case_status'] = $status;
                    $counts['case_status']++;
                    if ($note) {
                        $review[] = ['Health record', $record->id, $record->organization_id, $note];
                    }
                }
                if ($record->severity === null && $record->legacy_severity !== null) {
                    $review[] = ['Health record', $record->id, $record->organization_id, "Severity \"{$record->legacy_severity}\" could not be mapped; choose mild, moderate or severe."];
                }
                $hasAnimal = HealthRecordAnimal::withoutGlobalScopes()->where('health_record_id', $record->id)->exists();
                if (! $hasAnimal) {
                    $counts['animals']++;
                }

                if ($apply) {
                    if ($changes !== []) {
                        DB::table('health_records')->where('id', $record->id)->update($changes + ['updated_at' => now()]);
                    }
                    if (! $hasAnimal) {
                        HealthRecordAnimal::withoutGlobalScopes()->create([
                            'organization_id' => $record->organization_id, 'health_record_id' => $record->id,
                            'pig_id' => $pig->id, 'tag_label_snapshot' => $pig->tagLabelAt($record->record_date),
                        ]);
                    }
                }
            }

            $doses = MedicationAdministration::withoutGlobalScopes()->withTrashed()
                ->when($orgFilter, fn ($q) => $q->where('organization_id', $orgFilter))
                ->whereNull('medication_dosing_event_id')->where('is_legacy', false)
                ->orderBy('id')->get();
            foreach ($doses as $dose) {
                if ($dose->organization_id === null) {
                    $review[] = ['Dose', $dose->id, '—', 'Pig missing; farm unknown. Not changed.'];

                    continue;
                }
                $counts['doses_legacy']++;
                if ($apply) {
                    DB::table('medication_administrations')->where('id', $dose->id)->update([
                        'is_legacy'                => true,
                        'administered_at'          => $dose->administered_at ?? $dose->administration_date->format('Y-m-d').' 00:00:00',
                        'medicine_name_snapshot'   => $dose->medicine_name_snapshot ?? $dose->medication_name,
                        'withdrawal_days_snapshot' => $dose->withdrawal_period_days,
                        'withdrawal_basis'         => $dose->withdrawal_period_days !== null ? MedicationAdministration::BASIS_DOSE_DATE_PLUS_DAYS : null,
                        'status'                   => $this->doseStatusFor($dose),
                        'updated_at'               => now(),
                    ]);
                }
            }
        };

        $apply ? DB::transaction($work) : $work();

        $this->table(['What', $apply ? 'Changed' : 'Would change'], [
            ['Health records given an observed time (record date, 00:00)', $counts['observed_at']],
            ['Health records given their animal list (one row from the pig)', $counts['animals']],
            ['Health records given a case status', $counts['case_status']],
            ['Doses marked as legacy (no stock movement, nothing guessed)', $counts['doses_legacy']],
        ]);

        if ($review !== []) {
            $this->warn('Needs a person to review:');
            $this->table(['Record', 'ID', 'Organisation', 'Why'], $review);
        } else {
            $this->info('Nothing needs manual review.');
        }

        $this->line($apply ? 'Backfill applied.' : 'Dry run: nothing was changed. Re-run with --apply to write these changes.');

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: ?string} case status and a review note when the mapping is uncertain */
    private function caseStatusFor(HealthRecord $record): array
    {
        if ($record->resolved_at !== null) {
            return ['resolved', null];
        }

        return match ($record->treatment_status) {
            'ongoing' => ['under_treatment', null],
            'completed', 'stopped' => ['open', "Treatment marked {$record->treatment_status} but no resolved date; left open. Close it with an outcome if the animal recovered."],
            default => ['open', null],
        };
    }

    private function doseStatusFor(MedicationAdministration $dose): string
    {
        if ($dose->voided_at === null) {
            return MedicationAdministration::STATUS_RECORDED;
        }

        return MedicationAdministration::withoutGlobalScopes()->where('corrects_id', $dose->id)->exists()
            ? MedicationAdministration::STATUS_CORRECTED
            : MedicationAdministration::STATUS_VOIDED;
    }
}
