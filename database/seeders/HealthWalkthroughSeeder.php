<?php

namespace Database\Seeders;

use App\Models\HealthRecord;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Models\VetExamination;
use App\Services\Health\HealthRecordService;
use App\Services\Health\MedicineCatalogueService;
use App\Services\Health\MedicineStockService;
use App\Services\Health\RecordDoseService;
use App\Services\Health\RecordVetVisitService;
use App\Services\Health\TreatmentCourseService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Health walkthrough data for Chipper Farms, entered the way staff would,
 * through the same services the screens use (so stock, withdrawal, audit and
 * schedules are real). Nothing here could not have been typed in by hand.
 *
 *   php artisan db:seed --class=HealthWalkthroughSeeder
 *
 * What it sets up, dated over the last three days:
 *   - five medicines with batches (one expiring soon, one that runs low)
 *   - a vet visit: one pig healthy, one coughing (case opened), one awaiting
 *     a blood test, one planned but not examined; visit finalised
 *   - a scours case in three weaners, a 3-day course: day 1 given to all,
 *     day 2 given to two and missed for one, day 3 due today
 *   - the coughing pig's course: yesterday given, today due
 *   - a PCV2 vaccination round from a 10-dose vial, no case
 *   - an Ivermectin dose typed as 20 ml instead of 2 ml, given by the farm
 *     manager and entered by data entry, to try correcting
 */
class HealthWalkthroughSeeder extends Seeder
{
    private const ORGANIZATION = 'Chipper Farms';

    private User $manager;

    private User $dataEntry;

    /** @var array<string, Medicine> */
    private array $medicines = [];

    public function run(): void
    {
        $org = Organization::where('name', self::ORGANIZATION)->firstOrFail();
        app(CurrentOrganization::class)->bind($org);
        if (Medicine::where('name', 'Amoxicillin LA')->exists()) {
            $this->command?->warn('Health walkthrough data is already loaded (Amoxicillin LA exists). Nothing was added.');

            return;
        }
        $this->manager = User::where('organization_id', $org->id)->where('name', 'Farm Manager')->firstOrFail();
        $this->dataEntry = User::where('organization_id', $org->id)->where('name', 'Data Entry')->firstOrFail();

        $this->setUpMedicines();
        $coughCase = $this->vetVisit();
        $this->scoursCase();
        $this->coughCourse($coughCase);
        $this->vaccination();
        $this->doseToCorrect();

        $this->command?->info('Health walkthrough data loaded for '.self::ORGANIZATION.'.');
    }

    /** Medicine Catalogue → Add a medicine, then Receive a batch (Farm Manager). */
    private function setUpMedicines(): void
    {
        $this->as($this->manager);
        $catalogue = app(MedicineCatalogueService::class);
        $stock = app(MedicineStockService::class);
        $today = CarbonImmutable::today();

        $setup = [
            // name, formulation, strength, dose unit, stock unit, conversion, withdrawal, route, reorder, batches [number, qty, cost, expiry]
            ['Amoxicillin LA', 'Injectable suspension', '150 mg/ml', 'ml', 'ml', '1', 14, 'Injection (IM)', '40',
                [['AMX-2604', '20', '0.80', $today->addDays(20)], ['AMX-2610', '250', '0.75', $today->addYear()]]],
            ['Meloxicam', 'Injectable solution', '20 mg/ml', 'ml', 'ml', '1', 5, 'Injection (IM)', '30',
                [['MLX-2609', '30', '1.20', $today->addMonths(8)]]],
            ['Ivermectin', 'Injectable solution', '1%', 'ml', 'ml', '1', 21, 'Injection (SC)', '10',
                [['IVM-2609', '50', '0.60', $today->addYear()]]],
            ['PCV2 vaccine', 'Injectable suspension', '', 'dose', 'vial', '10', 0, 'Injection (IM)', null,
                [['PCV-2608', '2', '45.00', $today->addMonths(6)]]],
            ['Iron dextran', 'Injectable solution', '200 mg/ml', 'ml', 'ml', '1', null, 'Injection (IM)', null,
                [['FE-2609', '100', '0.30', $today->addMonths(10)]]],
        ];
        foreach ($setup as [$name, $formulation, $strength, $doseUnit, $stockUnit, $factor, $withdrawal, $route, $reorder, $batches]) {
            $medicine = $catalogue->save($this->manager, [
                'name' => $name, 'formulation' => $formulation, 'concentration' => $strength, 'dose_unit' => $doseUnit, 'stock_unit' => $stockUnit,
                'dose_units_per_stock_unit' => $factor, 'allowed_routes' => [$route], 'meat_withdrawal_days' => $withdrawal,
                'withdrawal_source_note' => $withdrawal === null ? null : 'Product label', 'reorder_level' => $reorder, 'is_active' => true,
            ]);
            foreach ($batches as [$number, $quantity, $cost, $expiry]) {
                $stock->receive($this->manager, $medicine, ['batch_number' => $number, 'received_at' => $today->subDays(10)->toDateString(),
                    'expiry_date' => $expiry->toDateString(), 'quantity_received' => $quantity, 'unit_cost' => $cost, 'supplier' => 'Agro vet shop']);
            }
            $this->medicines[$name] = $medicine;
        }
    }

    /** Vet Visits → New visit, examinations, Finalise. Returns the case opened for the coughing pig. */
    private function vetVisit(): HealthRecord
    {
        $this->as($this->dataEntry);
        $visits = app(RecordVetVisitService::class);
        $at = CarbonImmutable::now()->subDays(3)->setTime(10, 0);
        $planned = ['CFPG-2607-10', 'CFPG-2607-3-001', 'CFPG-2605-1-001', 'CFPG-2607-11'];
        $visit = $visits->create($this->dataEntry, [
            'visited_at' => $at->toDateTimeString(), 'veterinarian_name' => 'Dr Mensah', 'clinic' => 'Techiman Vet Clinic',
            'reason' => 'Routine check of growers; one coughing', 'general_notes' => 'Ventilation in sty 2 poor in the mornings.',
            'recommendations' => 'Open the sty 2 vents before 7am.',
        ], PigManagement::whereIn('code', $planned)->pluck('id')->all());

        $visits->examine($this->dataEntry, $visit, $this->pig('CFPG-2607-10'), [
            'examined_at' => $at->toDateTimeString(), 'assessment' => 'no_issue_found', 'vet_notes' => 'Good condition, eating well.', 'follow_up_not_needed' => true,
        ]);
        $exam = $visits->examine($this->dataEntry, $visit, $this->pig('CFPG-2607-3-001'), [
            'examined_at' => $at->addMinutes(10)->toDateTimeString(), 'assessment' => 'abnormal_findings', 'findings' => 'Dry cough, temperature 40.1 °C',
            'assessment_text' => 'Suspected respiratory infection', 'recommendations' => 'Antibiotic and anti-inflammatory; isolate if worse',
            'follow_up_on' => CarbonImmutable::today()->addDay()->toDateString(), 'open_case' => true, 'case_condition' => 'Respiratory infection (suspected)',
        ]);
        $visits->examine($this->dataEntry, $visit, $this->pig('CFPG-2605-1-001'), [
            'examined_at' => $at->addMinutes(20)->toDateTimeString(), 'assessment' => 'pending_assessment', 'findings' => 'Slightly pale',
            'tests_requested' => 'Blood sample for anaemia', 'follow_up_on' => CarbonImmutable::today()->addDays(2)->toDateString(),
        ]);
        // CFPG-2607-11 was planned but not examined: it is listed, not counted.

        $this->as($this->manager);
        $visits->finalise($this->manager, $visit);

        return HealthRecord::findOrFail(VetExamination::findOrFail($exam->id)->health_record_id);
    }

    /** A group case, its course, and doses over three days: one missed. */
    private function scoursCase(): void
    {
        $this->as($this->dataEntry);
        $pigs = $this->pigs(['CFPG-2607-12', 'CFPG-2607-13', 'CFPG-2607-14']);
        $start = CarbonImmutable::now()->subDays(2)->setTime(7, 30);
        $case = app(HealthRecordService::class)->openCase($this->dataEntry, $pigs, [
            'observed_at' => $start->toDateTimeString(), 'condition' => 'Scours (diarrhoea)', 'severity' => 'moderate', 'group_label' => 'Weaners sty 6–8',
            'symptoms' => 'Loose yellow droppings, two off feed', 'diagnosis_status' => 'unconfirmed', 'responsible_user_id' => $this->dataEntry->id,
        ]);

        $this->as($this->manager);
        $course = app(TreatmentCourseService::class)->prescribe($this->manager, [
            'purpose' => 'treatment', 'health_record_id' => $case->id, 'prescriber_name' => 'Dr Mensah (by phone)',
            'responsible_user_id' => $this->dataEntry->id, 'follow_up_on' => CarbonImmutable::today()->addDay()->toDateString(),
        ], $pigs, [[
            'medicine_id' => $this->medicines['Amoxicillin LA']->id, 'dose_amount' => '1', 'dose_unit' => 'ml', 'route' => 'Injection (IM)',
            'frequency' => 'daily', 'number_of_doses' => 3, 'start_at' => $start->setTime(8, 0)->toDateTimeString(),
        ]]);

        $this->as($this->dataEntry);
        $this->giveDue($course, $start->setTime(8, 0), skip: []);
        $this->giveDue($course, $start->addDay()->setTime(8, 0), skip: ['CFPG-2607-12' => 'Off feed, could not catch her']);
        // Day 3 (today 08:00) is left for you on the worklist.
    }

    /** The coughing pig's course: two medicines, yesterday given, today due. */
    private function coughCourse(HealthRecord $case): void
    {
        $this->as($this->manager);
        $yesterday = CarbonImmutable::now()->subDay()->setTime(9, 0);
        $course = app(TreatmentCourseService::class)->prescribe($this->manager, [
            'purpose' => 'treatment', 'health_record_id' => $case->id, 'prescriber_name' => 'Dr Mensah', 'responsible_user_id' => $this->manager->id,
        ], [$this->pig('CFPG-2607-3-001')], [
            ['medicine_id' => $this->medicines['Amoxicillin LA']->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'route' => 'Injection (IM)',
                'frequency' => 'daily', 'number_of_doses' => 2, 'start_at' => $yesterday->toDateTimeString()],
            ['medicine_id' => $this->medicines['Meloxicam']->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'route' => 'Injection (IM)',
                'frequency' => 'once', 'number_of_doses' => 1, 'start_at' => $yesterday->toDateTimeString()],
        ]);
        $this->as($this->dataEntry);
        $this->giveDue($course, $yesterday, skip: []);
        app(HealthRecordService::class)->addFollowUp($this->dataEntry, $case, [
            'observed_at' => CarbonImmutable::now()->subHours(3)->toDateTimeString(), 'response' => 'improving', 'findings' => 'Coughing less, eating',
        ]);
    }

    /** Prescribe a course → Vaccination, no case; given from a 10-dose vial. */
    private function vaccination(): void
    {
        $this->as($this->manager);
        $at = CarbonImmutable::now()->subDays(2)->setTime(14, 0);
        $course = app(TreatmentCourseService::class)->prescribe($this->manager, ['purpose' => 'vaccination'],
            $this->pigs(['CFPG-2607-10', 'CFPG-2607-11', 'CFPG-2607-15']), [[
                'medicine_id' => $this->medicines['PCV2 vaccine']->id, 'dose_amount' => '1', 'dose_unit' => 'dose', 'route' => 'Injection (IM)',
                'frequency' => 'once', 'number_of_doses' => 1, 'start_at' => $at->toDateTimeString(),
            ]]);
        $this->as($this->dataEntry);
        $this->giveDue($course, $at, skip: []);
    }

    /** Record a dose: typed 20 ml instead of 2 ml. Given by the farm manager, entered by data entry. */
    private function doseToCorrect(): void
    {
        $this->as($this->dataEntry);
        app(RecordDoseService::class)->record($this->dataEntry, [
            'medicine_id' => $this->medicines['Ivermectin']->id, 'administered_at' => CarbonImmutable::now()->subDay()->setTime(15, 0)->toDateTimeString(),
            'amount_basis' => 'per_animal', 'dose_amount' => '20', 'dose_unit' => 'ml', 'route' => 'Injection (SC)', 'purpose' => 'Deworming',
            'administered_by' => $this->manager->id, 'idempotency_key' => (string) Str::uuid(),
        ], [$this->pig('CFPG-2510-18')]);
    }

    /**
     * Give every dose of a course due at one time, as "Give to N" on the
     * worklist does; animals in $skip are marked missed with the reason.
     *
     * @param  array<string, string>  $skip  pig code => reason
     */
    private function giveDue(TreatmentCourse $course, CarbonImmutable $due, array $skip): void
    {
        foreach ($course->medicines as $line) {
            $doses = ScheduledDose::with('pig')->where('treatment_course_medicine_id', $line->id)->where('due_at', $due)->where('status', 'scheduled')->get();
            foreach ($doses->filter(fn ($d) => isset($skip[$d->pig->code])) as $dose) {
                app(\App\Services\Health\ScheduledDoseService::class)->close($this->dataEntry, $dose, ScheduledDose::MISSED, $skip[$dose->pig->code]);
            }
            $given = $doses->reject(fn ($d) => isset($skip[$d->pig->code]));
            if ($given->isEmpty()) {
                continue;
            }
            app(RecordDoseService::class)->record($this->dataEntry, [
                'medicine_id' => $line->medicine_id, 'administered_at' => $due->addMinutes(10)->toDateTimeString(), 'amount_basis' => 'per_animal',
                'dose_amount' => (string) $line->dose_amount, 'dose_unit' => $line->dose_unit, 'route' => $line->route,
                'purpose' => ucfirst($course->purpose), 'health_record_id' => $course->health_record_id,
                'scheduled_dose_ids' => $given->modelKeys(), 'idempotency_key' => (string) Str::uuid(),
            ], $given->pluck('pig')->all());
        }
    }

    private function pig(string $code): PigManagement
    {
        return PigManagement::where('code', $code)->firstOrFail();
    }

    private function pigs(array $codes): array
    {
        return array_map(fn ($code) => $this->pig($code), $codes);
    }

    private function as(User $user): void
    {
        auth()->login($user);
    }
}
