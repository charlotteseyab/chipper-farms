# Health and Medication Upgrade: Release Notes and Checklist

Release prepared 9 October 2026 on the `staging` branch. Not deployed. Every step under "Deploying" needs Charlotte's go-ahead before it is run against the live farm database.

## What is new

**For everyone recording health**

- **Vet visits.** Record the visit once, then each animal the vet examined, with its own findings and result: *no issue found*, *abnormal findings* or *pending*. Animals planned for the visit but not examined are listed separately and never counted as examined. An abnormal finding can open a health case straight from the examination.
- **Health cases** for one animal or a group. The animals in a group case are fixed when the case is opened, so pen moves later do not change who it was about. A case moves from open to under treatment, then resolved or closed with an outcome. It can be reopened with a reason.
- **Treatment courses.** A course has one or more medicines, each with a dose, route and frequency. The system schedules every dose for every animal. Vaccination and deworming courses do not need a case. Amending a course keeps the old version and cancels its remaining doses.
- **Record a dose.** Pick a catalogue medicine and the animals, choose who gave it (the person entering it is recorded separately), then enter the dose, either per animal or as a total for the group (for example in water). Stock is taken once for the whole group, from the batch that expires first unless you choose one. Expired batches, wrong units and doses larger than the stock on hand are refused.
- **Withdrawal.** Withdrawal comes from the medicine's label period at the moment the dose was given. Editing the catalogue later never changes past doses. A pig can be sold from the day after its last withdrawal ends. Direct sales, culls for food and the online shop all refuse a pig under withdrawal.
- **Treatment worklist.** One phone-friendly page shows today's doses, overdue doses and follow-ups. Use *Give to N*, or *Not given…* to mark a dose missed or cancelled.
- **Health overview cards** show open cases, doses due, follow-ups due and animals under withdrawal.
- **Files.** Lab results, photos and the vet's written report can be attached to a visit, an examination, a case or a follow-up (PDF or photo, up to 10 MB). Removing a file needs a manager and a reason, and the file is kept for the record.
- **Timeline.** Each pig's health page lists every examination, case, course, dose, correction and withdrawal end in date order, with the ear tag the pig wore that day. History stays with the animal when its tag is reused.

**For managers**

- **Medicine catalogue and stock.** Each medicine records its dose unit, stock unit, conversion and label withdrawal period. Managers receive batches (with expiry and cost) and adjust or count stock, always with a reason.
- **Corrections.** A wrong dose is corrected or voided on its dose record. The original is kept, its stock goes back to the batch, and withdrawal is recalculated. Health records and doses are never deleted.
- **Reminders** appear in the notification bell for farm managers and the person responsible:

  | Reminder | Sent |
  |---|---|
  | Doses due | From 06:00 on the day |
  | Overdue doses | 2 hours after they were due |
  | Follow-ups | 1 day ahead |
  | Withdrawal ending | 2 days ahead |
  | Batches expiring | 30 days ahead, if stock is left |
  | Medicine low | At or below its reorder level |

  Each reminder is sent once.
- **Reports**, each downloadable as CSV:
  - cases and outcomes;
  - doses given, missed and cancelled;
  - medicine usage and cost by medicine, batch, animal or group (checked against the stock ledger);
  - stock, expiry and withdrawal;
  - vet visits.

## Decisions applied

The plan's defaults D1–D13 were used as written.

| # | What the system does |
|---|---|
| D1 | Finishing a course does not resolve a case; a person closes it with an outcome. |
| D3 | Health entries for sold or dead pigs are allowed for dates before the sale or death, by managers only, with a reason. |
| D6 | The earliest-expiring batch is used first. A batch is usable on its expiry date and refused after it, with no override. |
| D7 | A pig is sellable from the day after dose date + withdrawal days. |
| D8 | Direct sale during withdrawal is blocked, with no override. |
| D10 | Medicine cost is kept in the stock ledger and reports only. There is no GL posting, and the period-close check ignores medicine stock. |
| D11 | No mortality or morbidity rates. |

## Permissions

| Permission | Managers (admin, farm manager, manager) | Data Entry |
|---|---|---|
| View health records, record cases, follow-ups, visits and doses | yes | yes |
| Finalise vet visits; prescribe courses; medicine catalogue and stock | yes | no |
| `correct_health_records`: correct or void any case or dose (new) | yes | no |
| `export_health_reports`: CSV downloads and the pig health PDF (new) | yes | yes |

`delete_health_records` and `manage_medication_records` are no longer used by any screen. They stay in the database until it is confirmed that no custom role relies on them.

## Deploying

Do these in order, after a full database backup.

1. **Back up** the database and note the time. Record per-organisation counts of `health_records` and `medication_administrations`.
2. **Deploy the code**, then run `composer install --no-dev`, `npm ci && npm run build` and `php artisan optimize:clear`. Health files are stored on the `local` disk under `health/` unless `HEALTH_ATTACHMENTS_DISK` is set; include that folder in backups.
3. **Run the migrations** with `php artisan migrate --force`. These are the health migrations, all additive:
   - `2028_11_01_000000_add_health_phase0_safety_fields`
   - `2028_11_02_000000_create_pig_exits_table`
   - `2028_11_03_000000_create_vet_visit_tables`
   - `2028_11_03_000100_extend_health_records_into_cases`
   - `2028_11_03_000200_create_medicine_tables`
   - `2028_11_03_000300_create_treatment_course_tables`
   - `2028_11_03_000400_create_dosing_tables_and_extend_administrations`
   - `2028_11_04_000000_create_medicine_stock_adjustments_table`
   - `2028_11_05_000000_create_health_reminders_table`
   - `2028_11_06_000000_add_dose_recorder_and_snapshots`
4. **Seed permissions** with `php artisan db:seed --class=FarmOperationsPermissionsSeeder --force`. It is safe to re-run. Without it, managers cannot correct anything and nobody can export.
5. **Backfill existing records.**
   - Run `php artisan health:backfill` first. It is a dry run that only reports.
   - Check the counts and the records it lists for review: records whose pig is in another farm, and severities it could not map.
   - Then run `php artisan health:backfill --apply`.
   - Old doses become legacy rows. They keep their withdrawal exactly as recorded and never move stock.
6. **Check the scheduler.** `php artisan schedule:list` must show `health:notify` every 15 minutes, and the server's cron must run `php artisan schedule:run` every minute.
7. **Set up medicines** before staff record doses. See the user guide, *Setting up medicines*. Until a medicine and a batch exist, the *Record a dose* form has nothing to choose.
8. **Smoke test** as a manager:
   - open Health, the Worklist and each Reports tab;
   - receive a test batch;
   - record and void one dose on a test pig.

   The void puts the stock back, which you can confirm on the medicine page.

**Optional settings** (defaults shown):

```
HEALTH_DOSES_DUE_FROM=06:00
HEALTH_OVERDUE_AFTER_HOURS=2
```

## Rolling back

- **Before anyone uses the new screens:** run `php artisan migrate:rollback` back to the previous release's batch and redeploy the previous code. Each migration's `down()` refuses to run once its tables hold data (for example *"Doses have been recorded or backfilled under the new model; rollback would lose that history"*). This guard is deliberate.
- **After the backfill or new records:** do not roll back migrations. Either:
  - redeploy the previous code and leave the new columns and tables in place (they are additive, so the old code ignores them); or
  - restore the backup from step 1, accepting that anything recorded since is lost.
- **Medicine stock** lives in the shared inventory ledger as item kind `medicine_batch`. Older code ignores that kind, and finance totals already exclude it.

## Acceptance scenarios (plan section 7)

| # | Scenario | Where it is proven |
|---|---|---|
| 1 | Case, two medicines, several doses, follow-up, close; full timeline | `HealthAcceptanceTest::test_scenario_1_…` |
| 2 | Preventive vaccination without a case | `HealthWorkflowTest::test_a_preventive_course_needs_no_case_…` |
| 3 | Group treated in part, then a pen change | `HealthAcceptanceTest::test_scenario_3_…`, `HealthWorkflowTest::test_a_group_case_keeps_its_animals_…` |
| 4 | Zero/negative amounts, wrong units, end before start, conversion | `MedicineStockAndDosesTest::test_amounts_and_units_…`, `…_convert_to_stock_units_…`, `HealthAcceptanceTest::test_scenario_4_…` |
| 5 | Not enough stock, expired batch, two batches, simultaneous recording, double submit | `MedicineStockAndDosesTest` (stock, expiry, earliest-expiry, double submit); `tests/Concurrency/HealthDoseConcurrencyTest` (simultaneous: **needs MariaDB, not yet run**) |
| 6 | Stock failure saves nothing; correction reverses and reconciles | `MedicineStockAndDosesTest::test_a_stock_failure_…`, `…_correcting_puts_the_old_stock_back_…` |
| 7 | Missed, cancelled, amended; overdue reminders; scheduler twice | `MedicineStockAndDosesTest::test_giving_and_missing_…`, `HealthWorkflowTest::test_amending_…`, `HealthRemindersAndReportsTest::test_dose_reminders_…` |
| 8 | Overlapping withdrawal, last dose changed, sale and shop refused | `HealthAcceptanceTest::test_scenario_8_…`, `HealthSafetyFixesTest::test_direct_sale_is_refused_…` |
| 9 | Sold or culled, tag reused; history stays with the original pig | `HealthAcceptanceTest::test_scenarios_9_and_13_…` |
| 10 | Roles against forms, URLs, reports, exports; other farm returns 404 | `HealthRemindersAndReportsTest` (exports, permissions), 404 tests in each health test file |
| 11 | Migration totals; legacy doses move no stock | `HealthDataModelTest` (backfill dry run, idempotence, legacy doses) |
| 12 | Vet visit with three pigs (healthy, sick, pending) | `HealthWorkflowTest::test_a_visit_checking_three_animals_…` |
| 13 | Repeat visit, pen change, tag reuse; finalised notes amended with history | `HealthAcceptanceTest::test_scenarios_9_and_13_…` |
| 14 | Regression | Full suite: see the test results in the release message |

Still to do with Charlotte: walk the scenarios on representative data in the browser, and run the MariaDB concurrency suite (`vendor/bin/phpunit -c phpunit.concurrency.xml`) on a machine that has MariaDB.

## Known limitations

- **Withdrawal dates.** Withdrawal is counted from the dose date, not the hour (D7).
- **Doses.** There is no weight-based dosing, no reservation of stock for planned courses, and no supplier ordering.
- **Reminders** are in-app only, with no email.
- **The concurrency tests** cannot run on the development machine because it has no MariaDB.
- **The one failing test** in the full suite, `Storefront\FinanceIntegrationTest`, predates this work. It fails because no accounting period is defined for the test date.
