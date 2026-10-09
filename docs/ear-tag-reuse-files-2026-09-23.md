# Tag reuse working-tree file inventory

Combined uncommitted upgrade relative to HEAD, including work already present at the start of this turn. No files were removed. The pre-existing chipper.zip archive is excluded.

| File | State | Purpose / review area |
| --- | --- | --- |
| `app/Console/Commands/ReconcileEarTags.php` | New / untracked | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Helpers/NavigationHelper.php` | Modified | Route or navigation integration (includes preserved prior navigation changes) |
| `app/Http/Controllers/PigArchiveController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/PigEarTagController.php` | New / untracked | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/PigHealthController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/PigManagementController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/PigStockController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/PurchaseStockIntakeController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/RoomController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/Settings/EarTagReconciliationController.php` | New / untracked | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/WeaningController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Controllers/WeightManagementController.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Requests/Deaths/RecordPigDeathRequest.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Requests/Sales/RecordPigSaleRequest.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Requests/Stock/StorePigRequest.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Requests/Stock/UpdateBreedingPigRequest.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Http/Requests/Stock/UpdateStandardPigRequest.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Models/EarTag.php` | New / untracked | Assignment relationships, archived-record access and identity preservation |
| `app/Models/EarTagAssignment.php` | New / untracked | Assignment relationships, archived-record access and identity preservation |
| `app/Models/EarTagColor.php` | New / untracked | Assignment relationships, archived-record access and identity preservation |
| `app/Models/Farrowing.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/Finance/InvoiceLine.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/Finance/PigMortalityWriteoff.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/HealthRecord.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/Mating.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/MedicationAdministration.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/PigManagement.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/PigWeight.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Models/Weaning.php` | Modified | Assignment relationships, archived-record access and identity preservation |
| `app/Services/Archive/RestoreArchivedRecordService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Deaths/DeathsExportService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Deaths/RecordPigDeathService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Reports/SystemReportService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Sales/RecordPigSaleService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Sales/SalesExportService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Stock/EarTagNumberService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Stock/Imports/StockExcelImportService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Stock/PigStockService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Stock/PurchaseStockIntakeService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Services/Stock/Tags/EarTagAdministration.php` | New / untracked | Central tag allocation, assignment, history, correction and audit behaviour |
| `app/Services/Stock/Tags/EarTagAllocator.php` | New / untracked | Central tag allocation, assignment, history, correction and audit behaviour |
| `app/Services/Stock/Tags/EarTagAssignmentService.php` | New / untracked | Central tag allocation, assignment, history, correction and audit behaviour |
| `app/Services/Stock/Tags/EarTagHistory.php` | New / untracked | Central tag allocation, assignment, history, correction and audit behaviour |
| `app/Services/Stock/Tags/EarTagShadowService.php` | New / untracked | Central tag allocation, assignment, history, correction and audit behaviour |
| `app/Services/Stock/Tags/ReplaceEarTagService.php` | New / untracked | Central tag allocation, assignment, history, correction and audit behaviour |
| `app/Services/Stock/WeanLitterToStockService.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Storefront/Support/ListingDetailPresenter.php` | Modified | Lifecycle, registration, validation, import/export, reporting or tenant-scoped tag integration |
| `app/Support/Features/FinanceNavigation.php` | Modified | Route or navigation integration (includes preserved prior navigation changes) |
| `database/migrations/2028_10_11_000000_create_reusable_ear_tag_foundation.php` | New / untracked | Register schema, activation gates, organisation constraints and assignment integrity |
| `database/migrations/2028_10_12_000000_activate_colour_scoped_reusable_ear_tags.php` | New / untracked | Register schema, activation gates, organisation constraints and assignment integrity |
| `database/migrations/2028_10_13_000000_enforce_organization_tag_identity.php` | New / untracked | Register schema, activation gates, organisation constraints and assignment integrity |
| `docs/ear-tag-reuse-audit.md` | New / untracked | Review, rollout evidence, file inventory and limitations |
| `docs/ear-tag-reuse-files-2026-09-23.md` | New / untracked | Review, rollout evidence, file inventory and limitations |
| `docs/ear-tag-reuse-implementation-2026-09-23.md` | New / untracked | Review, rollout evidence, file inventory and limitations |
| `docs/ear-tag-reuse-review-2026-09-22.md` | New / untracked | Review, rollout evidence, file inventory and limitations |
| `resources/views/breeding_stocks/edit.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/breeding_stocks/index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/components/ear-tag-label.blade.php` | New / untracked | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/costing/mortality-writeoffs/index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/deaths/_tables.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/deaths/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/exports/deaths-pdf.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/exports/sales-pdf.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/farrowing/index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/farrowing/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/finance/returns/index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/gestation/edit.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/gestation/index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/health/medications-index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/health/pdf.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/health/reports.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/health/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/mating/manage.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/medications/edit.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/medications/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/piglets/tracking.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/pigs/archived.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/pigs/death.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/pigs/sale.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/pigs/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/pigs/show_pdf.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/pigs/tags/replace.blade.php` | New / untracked | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/purchase-stock-intakes/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/sales/_table.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/sales/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/settings/ear-tags/history.blade.php` | New / untracked | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/settings/ear-tags/reconciliation.blade.php` | New / untracked | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/settings/reports.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/stocks/all/create.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/stocks/all/import/create.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/stocks/all/import/preview.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/stocks/all/index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/stocks/standard/edit.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/stocks/standard/index.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/storefront/admin/listings/show.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/weaning/dashboard.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/weaning/litter-preview.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/weights/all-pdf-report.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/weights/pdf-report.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `resources/views/weights/reports.blade.php` | Modified | Identity display, historical event labels, registration/replacement forms or tag history UI |
| `routes/web.php` | Modified | Route or navigation integration (includes preserved prior navigation changes) |
| `scripts/testing/rehearse-ear-tags.php` | New / untracked | Isolated migration, backup recovery and concurrency rehearsal |
| `tests/Concurrency/EarTagConcurrencyTest.php` | New / untracked | Tag lifecycle, allocation, isolation, audit, migration or existing compatibility verification |
| `tests/Feature/AnimalBreedSettingsTest.php` | Modified | Tag lifecycle, allocation, isolation, audit, migration or existing compatibility verification |
| `tests/Feature/Finance/FinanceSidebarTest.php` | Modified | Tag lifecycle, allocation, isolation, audit, migration or existing compatibility verification |
| `tests/Feature/Services/PigStockServiceTest.php` | Modified | Tag lifecycle, allocation, isolation, audit, migration or existing compatibility verification |
| `tests/Feature/Stock/PurchaseStockIntakeTest.php` | Modified | Tag lifecycle, allocation, isolation, audit, migration or existing compatibility verification |
| `tests/Feature/Stock/ReusableEarTagFoundationTest.php` | New / untracked | Tag lifecycle, allocation, isolation, audit, migration or existing compatibility verification |
| `tests/Feature/Storefront/StorefrontPigletSaleTest.php` | Modified | Tag lifecycle, allocation, isolation, audit, migration or existing compatibility verification |
