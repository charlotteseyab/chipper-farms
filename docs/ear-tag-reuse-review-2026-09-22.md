> Update: this is the historical 22 September review. See [23 September implementation and verification](ear-tag-reuse-implementation-2026-09-23.md) for current code, test evidence and migration blockers.

# Tag reuse implementation review — 22 September 2026

Status: **Not ready for UAT or production.** This is an interim review of the pre-existing uncommitted implementation, not a completion report.

## Confirmed existing behaviour

`pigs_management.id` remains the relational identity. Its model generates `P-` plus 12 hexadecimal characters when a code is absent. Existing parent, weight, health and breeding relationships use animal IDs. Preserve those relationships and code generation.

`EarTagNumberService` still proposes numbers after the maximum numeric legacy tag, including archived animals. The new `EarTagAllocator` instead proposes Available numeric tags followed by gaps, but scopes allocation by colour. Non-numeric Available tags are excluded by this implementation.

The foundation creates `ear_tag_colors`, `ear_tags`, and `ear_tag_assignments`. Physical uniqueness is organisation + colour + normalized number. Nullable `is_current` unique indexes guard one current assignment per tag and animal. This marker is separately editable from `released_at`; the schema does not enforce their equivalence. Individual foreign keys do not enforce that assignment, tag and animal organisations match. Ordinary model deletion of an assignment is not prohibited.

The subsequent activation migration drops the legacy organisation + tag unique index without reconciliation validation and has an empty rollback. The prior audit document describes reuse as disabled, so it no longer accurately describes the activation migration.

## Read-only database evidence

Executed `php artisan ear-tags:reconcile` against the configured local database after connection permission was granted. It reported 29 tagged animals, 29 assignments, zero missing assignments, zero mismatches and zero Pending Recovery tags. No backfill or database mutation was performed. These counts are the existing command's results, not independent certification of all constraints or historical records. Its query excludes animals without tags; it does not prove absence of every cross-organisation or overlapping-date conflict.

## Changes made during this review

- `app/Http/Controllers/Settings/EarTagReconciliationController.php`: recheck assignment state after acquiring the tag row lock, preventing stale validation from setting an assigned tag Available or an unassigned tag In Use.
- `tests/Feature/Stock/ReusableEarTagFoundationTest.php`: add two feature checks rejecting contradictory status changes.
- This review document records evidence, limitations and the direct-reference inventory.

All other uncommitted changes predate this review and have been preserved. No files removed. No schema changes made during this review.

## Required decisions and remaining implementation

The supplied specification requires organisation + number uniqueness, whereas existing code, tests and UI intentionally use organisation + colour + number. A clarification is pending before changing that existing identity model or migrating its data.

Release currently leaves the legacy current-tag cache populated. Historical reporting needs a central event-date resolver before clearing the cache, otherwise existing exports lose historical labels. Restore currently requires the former tag to be Available; it needs an alternative-tag path. Status transitions, corrections and audit data must be centralised and validated under locks. Reserved is absent from the reconciliation status choices. Assignment date validation, correction auditing and deletion protection remain necessary.

Death, sale, stock registration, purchasing, imports and weaning already have uncommitted integrations; their complete lifecycle and retry behaviour still require review. Every historical report/export and current/archive search must be classified and updated. Current passing tests check assignment ownership but do not prove isolation of every operational history or actual concurrent transactions.

Migration requires conflict detection, a fail-closed activation gate, a production-like copy rehearsal, reconciliation and a tested recovery procedure. No production deployment or migration has been performed. Manual UAT, concurrency certification, full regressions and production monitoring remain outstanding.

## Tests

Baseline: 12 existing tag foundation tests passed (36 assertions). Final focused run is reported in the conversation. No full regression, MariaDB concurrency or browser UAT claim is made.

## Direct legacy-field inventory

136 files reference `ear_tag_number` across application code, views and migrations. This is a search inventory, not a claim that all references have been individually verified. It includes stock, lifecycle, health, medication, weight, breeding, notifications, archive, inventory/storefront, finance, reports, exports, permissions-related views and audit display.

- `app/Console/Commands/ReconcileEarTags.php`
- `app/Services/Stock/EarTagNumberService.php`
- `app/Services/Stock/Imports/StockExcelImportService.php`
- `app/Services/Stock/Tags/EarTagShadowService.php`
- `app/Services/Stock/Tags/EarTagAssignmentService.php`
- `app/Services/Stock/Tags/ReplaceEarTagService.php`
- `app/Services/Stock/PurchaseStockIntakeService.php`
- `app/Services/Stock/PigRelationshipAnalysisService.php`
- `app/Services/Stock/PigStockService.php`
- `app/Services/Stock/WeanLitterToStockService.php`
- `app/Services/Weights/WeightAlertGenerationService.php`
- `app/Services/Archive/RestoreArchivedRecordService.php`
- `app/Services/Sales/SalesExportService.php`
- `app/Services/Sales/RecordPigSaleService.php`
- `app/Services/Sales/AbortPigSaleService.php`
- `app/Services/RealTimeNotificationService.php`
- `app/Services/Deaths/RecordPigDeathService.php`
- `app/Services/Deaths/DeathsExportService.php`
- `app/Services/Reports/PlatformReportingService.php`
- `app/Services/Reports/SystemReportService.php`
- `app/Notifications/WeightDueSoonNotification.php`
- `app/Notifications/WeightOverdueNotification.php`
- `app/Notifications/GestationDueTodayNotification.php`
- `app/Notifications/PigletWeaningDueNotification.php`
- `app/Notifications/GestationOverdueNotification.php`
- `app/Notifications/GestationDueSoonNotification.php`
- `app/Models/PigWeight.php`
- `app/Models/PigManagement.php`
- `app/Models/WeightAlert.php`
- `app/Finance/Subscribers/Sales/PigSaleInvoicingSubscriber.php`
- `app/Storefront/Support/ListingDetailPresenter.php`
- `app/Storefront/Services/StorefrontPigAllocationService.php`
- `app/Storefront/Services/StorefrontPigPoolService.php`
- `app/Storefront/Services/StorefrontPurchaseLineService.php`
- `app/Storefront/Services/StorefrontPigPricingService.php`
- `app/Storefront/Services/StorefrontPigletMaterialisationService.php`
- `app/Observers/PigSaleObserver.php`
- `app/Observers/PigWeightObserver.php`
- `app/Http/Controllers/FarmManagerDashboardController.php`
- `app/Http/Controllers/WeaningController.php`
- `app/Http/Controllers/RoomController.php`
- `app/Http/Controllers/MatingController.php`
- `app/Http/Controllers/FarrowingController.php`
- `app/Http/Controllers/WeightAlertsController.php`
- `app/Http/Controllers/DataEntryController.php`
- `app/Http/Controllers/PigEarTagController.php`
- `app/Http/Controllers/PurchaseStockIntakeController.php`
- `app/Http/Controllers/Audit/AuditTrailController.php`
- `app/Http/Controllers/PigStockController.php`
- `app/Http/Controllers/PigArchiveController.php`
- `app/Http/Controllers/PigHealthController.php`
- `app/Http/Controllers/SuperuserController.php`
- `app/Http/Controllers/Storefront/PushToStorefrontController.php`
- `app/Http/Controllers/Storefront/StorefrontController.php`
- `app/Http/Controllers/WeightManagementController.php`
- `app/Http/Requests/Stock/UpdateStandardPigRequest.php`
- `app/Http/Requests/Stock/StorePigRequest.php`
- `app/Http/Requests/Stock/UpdateBreedingPigRequest.php`
- `app/Http/Middleware/NotifySuccessfulAction.php`
- `resources/views/medications/edit.blade.php`
- `resources/views/medications/show.blade.php`
- `resources/views/weights/all-pdf-report.blade.php`
- `resources/views/weights/edit.blade.php`
- `resources/views/weights/pdf-report.blade.php`
- `resources/views/weights/create.blade.php`
- `resources/views/weights/reports.blade.php`
- `resources/views/weights/index.blade.php`
- `resources/views/weights/audit.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/mating/index.blade.php`
- `resources/views/mating/manage.blade.php`
- `resources/views/storefront/admin/listings/push.blade.php`
- `resources/views/storefront/admin/listings/edit.blade.php`
- `resources/views/storefront/admin/listings/show.blade.php`
- `resources/views/storefront/admin/orders/partials/pig-selection-panel.blade.php`
- `resources/views/stocks/all/import/preview.blade.php`
- `resources/views/stocks/all/create.blade.php`
- `resources/views/stocks/all/pdf.blade.php`
- `resources/views/stocks/all/index.blade.php`
- `resources/views/stocks/standard/edit.blade.php`
- `resources/views/stocks/standard/pdf.blade.php`
- `resources/views/stocks/standard/index.blade.php`
- `resources/views/weight-alerts/create.blade.php`
- `resources/views/weight-alerts/index.blade.php`
- `resources/views/weight-alerts/dashboard.blade.php`
- `resources/views/weight-alerts/show.blade.php`
- `resources/views/superuser/dashboard.blade.php`
- `resources/views/breeding_stocks/edit.blade.php`
- `resources/views/breeding_stocks/pdf.blade.php`
- `resources/views/breeding_stocks/index.blade.php`
- `resources/views/breeding_stocks/create_old.blade.php`
- `resources/views/dataentry/dashboard.blade.php`
- `resources/views/components/ear-tag-label.blade.php`
- `resources/views/purchase-stock-intakes/show.blade.php`
- `resources/views/settings/reports.blade.php`
- `resources/views/settings/ear-tags/reconciliation.blade.php`
- `resources/views/farrowing/edit.blade.php`
- `resources/views/farrowing/index.blade.php`
- `resources/views/farrowing/show.blade.php`
- `resources/views/sales/_table.blade.php`
- `resources/views/sales/show.blade.php`
- `resources/views/sales/audit.blade.php`
- `resources/views/weaning/litter-preview.blade.php`
- `resources/views/weaning/dashboard.blade.php`
- `resources/views/gestation/edit.blade.php`
- `resources/views/gestation/index.blade.php`
- `resources/views/rooms/show.blade.php`
- `resources/views/piglets/tracking.blade.php`
- `resources/views/audit-trail/index.blade.php`
- `resources/views/costing/mortality-writeoffs/index.blade.php`
- `resources/views/health/edit.blade.php`
- `resources/views/health/reports.blade.php`
- `resources/views/health/pdf.blade.php`
- `resources/views/health/index.blade.php`
- `resources/views/health/show.blade.php`
- `resources/views/health/create-health.blade.php`
- `resources/views/health/create-medication.blade.php`
- `resources/views/health/medications-index.blade.php`
- `resources/views/deaths/_tables.blade.php`
- `resources/views/deaths/show.blade.php`
- `resources/views/pigs/death.blade.php`
- `resources/views/pigs/show_pdf.blade.php`
- `resources/views/pigs/tags/replace.blade.php`
- `resources/views/pigs/sale.blade.php`
- `resources/views/pigs/show.blade.php`
- `resources/views/pigs/archived.blade.php`
- `resources/views/manager/dashboard.blade.php`
- `resources/views/exports/sales-pdf.blade.php`
- `resources/views/exports/deaths-pdf.blade.php`
- `resources/views/finance/returns/index.blade.php`
- `database/seeders/PigManagementSeeder.php`
- `database/migrations/2025_08_05_095459_update_pig_codes_and_ear_tags_format.php`
- `database/migrations/2028_10_09_000000_scope_pig_identifiers_to_organization.php`
- `database/migrations/2028_10_12_000000_activate_colour_scoped_reusable_ear_tags.php`
- `database/migrations/2025_08_05_145821_update_breed_codes_and_ear_tag_format.php`
- `database/migrations/2025_07_25_105917_create_pigs_management_table.php`
