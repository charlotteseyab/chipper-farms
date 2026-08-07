<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PigHealthController;
use App\Http\Controllers\FarmManagerDashboardController;
use App\Http\Controllers\SuperuserController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\PerformanceMetricController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\FeedProductionController;
use App\Http\Controllers\FeedPlanningController;
use App\Http\Controllers\Inventory\GrIrAgeingController;
use App\Http\Controllers\Inventory\InventoryAccountMappingController;
use App\Http\Controllers\Inventory\InventoryValueController;
use App\Http\Controllers\Inventory\OpeningBalanceController;
use App\Http\Controllers\Procurement\ReceiveAgainstPurchaseRequestController;
use App\Http\Controllers\Costing\GroupCostPoolController;
use App\Http\Controllers\Costing\OpeningPigPoolController;
use App\Http\Controllers\Costing\PigMortalityWriteoffController;
use App\Http\Controllers\Feed\FeedIssueController;
use App\Http\Controllers\Finance\BalanceSheetController;
use App\Http\Controllers\Finance\CashFlowController;
use App\Http\Controllers\Finance\CloseRunController;
use App\Http\Controllers\Finance\IncomeStatementController;
use App\Http\Controllers\Finance\PeriodCloseController;
use App\Http\Controllers\Finance\YearEndCloseController;
use App\Http\Controllers\Finance\BiologicalAssetController;
use App\Http\Controllers\Finance\LoanController;
use App\Http\Controllers\Finance\TreasuryController;
use App\Http\Controllers\Finance\InvestorController;
use App\Http\Controllers\Finance\FeedSaleController;
use App\Http\Controllers\Audit\AuditTrailController;




/*
|--------------------------------------------------------------------------
| Health and readiness (CF-P1-022)
|--------------------------------------------------------------------------
|
| Deliberately unauthenticated and outside every middleware group: a readiness
| probe must answer while sessions, the database or the cache are broken, which
| is exactly when auth middleware cannot run.
|
| Laravel's own /up (bootstrap/app.php) stays as the LIVENESS probe — "did PHP
| boot". These answer readiness — "can this instance serve traffic" — and return
| 503 when it cannot, so an orchestrator stops routing to it.
|
| Detailed output requires HEALTH_CHECK_TOKEN; without it the response carries
| only pass/fail per named check and never an exception message, which would
| leak host, port, database and username.
*/
Route::get('/health/ready', [\App\Http\Controllers\HealthController::class, 'ready'])
    ->name('health.ready');
Route::get('/health/metrics', [\App\Http\Controllers\HealthController::class, 'metrics'])
    ->name('health.metrics');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/terms-of-service', function () {
    return view('terms', [
        'terms' => config('app.name') . ' terms of service.',
    ]);
})->name('terms.show');

Route::get('/privacy-policy', function () {
    return view('policy', [
        'policy' => config('app.name') . ' privacy policy.',
    ]);
})->name('policy.show');

$protectedMiddleware = [
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'activity',
];

Route::middleware($protectedMiddleware)->group(function () {
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Health
    Route::get('/health', [PigHealthController::class, 'index'])->middleware('permission:view_health_records')->name('health.index');
    Route::put('/health/{pig}', [PigHealthController::class, 'update'])->middleware('permission:manage_health_records')->name('health.update');
    Route::get('/health/create-health', [PigHealthController::class, 'createHealth'])->middleware('permission:manage_health_records')->name('health.create');
    Route::get('/health/create-medication', [PigHealthController::class, 'createMedication'])->middleware('permission:administer_medication')->name('health.create-medication');
    Route::post('/health/store-health', [PigHealthController::class, 'storeHealth'])->middleware('permission:manage_health_records')->name('health.store');
    Route::post('/health/store-medication', [PigHealthController::class, 'storeMedication'])->middleware('permission:administer_medication')->name('medications.store');
    Route::get('/health/{pig}', [PigHealthController::class, 'show'])->middleware('permission:view_health_records')->name('health.show');
    Route::patch('/health/{pig}/status', [PigHealthController::class, 'updateHealthStatus'])->middleware('permission:manage_health_records')->name('health.status.update');
    Route::get('/health/{pig}/export-pdf', [PigHealthController::class, 'exportPigHealthPdf'])->middleware('permission:view_health_records')->name('health.export-pdf');
    Route::get('/health/{pig}/create-health', [PigHealthController::class, 'createHealth'])->middleware('permission:manage_health_records')->name('health.createForPig');
    Route::get('/health/{pig}/create-medication', [PigHealthController::class, 'createMedication'])->middleware('permission:administer_medication')->name('medications.createForPig');
    Route::post('/health/{pig}/health', [PigHealthController::class, 'storeHealth'])->middleware('permission:manage_health_records')->name('health.storeForPig');
    Route::post('/health/{pig}/medication', [PigHealthController::class, 'storeMedication'])->middleware('permission:administer_medication')->name('medications.storeForPig');
    Route::get('/medications/{medication}', [PigHealthController::class, 'showMedication'])->middleware('permission:view_health_records')->name('medications.show');
    Route::get('/medications/{medication}/edit', [PigHealthController::class, 'editMedication'])->middleware('permission:manage_medication_records')->name('medications.edit');
    Route::put('/medications/{medication}', [PigHealthController::class, 'updateMedication'])->middleware('permission:manage_medication_records')->name('medications.update');
    Route::delete('/medications/{medication}', [PigHealthController::class, 'destroyMedication'])->middleware('permission:manage_medication_records')->name('medications.destroy');

    // Medications index & Health reports
    Route::get('/medications', [PigHealthController::class, 'medicationsIndex'])->middleware('permission:view_health_records')->name('medications.index');
    Route::get('/health-reports', [PigHealthController::class, 'reports'])->middleware('permission:view_health_records')->name('health.reports');
    Route::get('/health/{pig}/edit', [PigHealthController::class, 'edit'])->middleware('permission:manage_health_records')->name('health.edit');
    Route::delete('/health/{pig}', [PigHealthController::class, 'destroy'])->middleware('permission:delete_health_records')->name('health.destroy');
    Route::get('/export/health.csv', [PigHealthController::class, 'exportHealthCsv'])->middleware('permission:view_health_records')->name('health.export');
    Route::get('/export/medications.csv', [PigHealthController::class, 'exportMedicationsCsv'])->middleware('permission:view_health_records')->name('medications.export');

    // NOTE: the duplicate GET /dashboard that used to sit here has been removed
    // (CF-P2-024). It was byte-identical to the definition further down, which
    // wins registration anyway — Laravel keys its route lookup on method+URI, so
    // the later declaration silently replaced this one. Two identical routes
    // with the same name are a maintenance trap: editing the first has no effect
    // whatsoever, with no error to say so. The surviving definition is the one
    // carrying the explanatory comment about role-based dispatch.

    // Each dashboard requires its own role. Superuser bypass is built into
    // the `role` middleware (App\Http\Middleware\RoleMiddleware), so the
    // explicit 'superuser' on each line is belt-and-suspenders.
    Route::middleware('role:admin,superuser')->group(function () {
        Route::get('/admin/dashboard',           [App\Http\Controllers\AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/admin/weight-trends-data',  [App\Http\Controllers\AdminController::class, 'getWeightTrendsData'])->name('admin.weight-trends-data');
    });

    Route::middleware('role:manager,admin,superuser')->group(function () {
        Route::get('/manager/dashboard', FarmManagerDashboardController::class)->name('manager.dashboard');
    });

    Route::middleware('role:superuser')->group(function () {
        Route::get('/superuser/dashboard',                [SuperuserController::class, 'dashboard'])->name('superuser.dashboard');
        Route::get('/superuser/dashboard/export/pdf',     [SuperuserController::class, 'exportPdf'])->name('superuser.dashboard.export.pdf');
        Route::get('/superuser/dashboard/export/csv',     [SuperuserController::class, 'exportCsv'])->name('superuser.dashboard.export.csv');
    });

    Route::middleware('role:dataentry,admin,superuser')->group(function () {
        Route::get('/dataentry/dashboard',     [App\Http\Controllers\DataEntryController::class, 'dashboard'])->name('dataentry.dashboard');
        Route::get('/api/daily-weights-data',  [App\Http\Controllers\DataEntryController::class, 'getDailyWeightsData'])->name('api.daily-weights-data');
    });

    // Generic /dashboard remains accessible to any authenticated user — it
    // just renders dashboard.blade.php which itself does role-based dispatch.
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // All Stock Routes
    Route::get('all-stocks', [App\Http\Controllers\PigManagementController::class, 'allIndex'])->name('all-stocks.index');
    Route::get('all-stocks/create', [App\Http\Controllers\PigManagementController::class, 'allCreate'])->name('all-stocks.create');
    Route::get('all-stocks/import', [App\Http\Controllers\PigStockController::class, 'allImportCreate'])->name('all-stocks.import.create');
    Route::post('all-stocks/import/preview', [App\Http\Controllers\PigStockController::class, 'allImportPreview'])->name('all-stocks.import.preview');
    Route::post('all-stocks/import/confirm', [App\Http\Controllers\PigStockController::class, 'allImportConfirm'])->name('all-stocks.import.confirm');
    Route::get('all-stocks/import/sample', [App\Http\Controllers\PigStockController::class, 'allImportSample'])->name('all-stocks.import.sample');
    Route::get('all-stocks-export/excel', [App\Http\Controllers\PigStockController::class, 'allExportExcel'])->name('all-stocks.export.excel');
    Route::get('all-stocks-export/csv', [App\Http\Controllers\PigManagementController::class, 'allExportCsv'])->name('all-stocks.export.csv');
    Route::get('all-stocks-export/pdf', [App\Http\Controllers\PigManagementController::class, 'allExportPdf'])->name('all-stocks.export.pdf');
    Route::resource('purchase-stock-intakes', App\Http\Controllers\PurchaseStockIntakeController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['purchase-stock-intakes' => 'purchaseStockIntake']);
    Route::post('purchase-stock-intakes/{purchaseStockIntake}/receive', [App\Http\Controllers\PurchaseStockIntakeController::class, 'receive'])->name('purchase-stock-intakes.receive');
    Route::post('purchase-stock-intakes/{purchaseStockIntake}/quarantine', [App\Http\Controllers\PurchaseStockIntakeController::class, 'quarantine'])->name('purchase-stock-intakes.quarantine');
    Route::post('purchase-stock-intakes/{purchaseStockIntake}/approve', [App\Http\Controllers\PurchaseStockIntakeController::class, 'approve'])->name('purchase-stock-intakes.approve');
    Route::post('purchase-stock-intakes/{purchaseStockIntake}/add-to-stock', [App\Http\Controllers\PurchaseStockIntakeController::class, 'addToStock'])->name('purchase-stock-intakes.add-to-stock');

    // Breeding Stock Routes (existing)
    Route::resource('breeding-stocks', 'App\Http\Controllers\PigManagementController');
    Route::get('breeding-stocks-export/csv', [App\Http\Controllers\PigManagementController::class, 'exportCsv'])->name('breeding-stocks.export.csv');
    Route::get('breeding-stocks-export/pdf', [App\Http\Controllers\PigManagementController::class, 'exportPdf'])->name('breeding-stocks.export.pdf');

    // Inbreeding Check API
    Route::post('check-relationship', [App\Http\Controllers\PigManagementController::class, 'checkRelationship'])->name('check-relationship');

    // Ear Tag Validation API
    Route::post('api/check-ear-tag', [App\Http\Controllers\PigManagementController::class, 'checkEarTag'])->name('api.check-ear-tag');

    // Mating Management Routes
    Route::get('mating', [App\Http\Controllers\PigManagementController::class, 'matingIndex'])->name('mating.index');
    Route::post('mating/plan', [App\Http\Controllers\PigManagementController::class, 'planMating'])->name('mating.plan');
    Route::get('mating/manage', [App\Http\Controllers\PigManagementController::class, 'matingManage'])->name('mating.manage');
    Route::post('mating/{id}/pregnancy-check', [App\Http\Controllers\PigManagementController::class, 'recordPregnancyCheck'])->name('mating.pregnancy-check');
    Route::post('mating/{id}/complete', [App\Http\Controllers\PigManagementController::class, 'completeMating'])->name('mating.complete');
    Route::post('mating/{id}/cancel', [App\Http\Controllers\PigManagementController::class, 'cancelMating'])->name('mating.cancel');
    Route::delete('mating/{id}', [App\Http\Controllers\PigManagementController::class, 'destroyMating'])->name('mating.destroy');

    // Gestation Period Management Routes
    Route::get('gestation', [App\Http\Controllers\PigManagementController::class, 'gestationIndex'])->name('gestation.index');
    Route::get('gestation/{mating}/edit', [App\Http\Controllers\PigManagementController::class, 'editGestation'])->name('gestation.edit');
    Route::put('gestation/{mating}', [App\Http\Controllers\PigManagementController::class, 'updateGestation'])->name('gestation.update');
    Route::delete('gestation/{mating}', [App\Http\Controllers\PigManagementController::class, 'destroyGestation'])->name('gestation.destroy');

    // Farrowing routes
    Route::get('farrowing', [App\Http\Controllers\FarrowingController::class, 'index'])->middleware('permission:view_farrowing')->name('farrowing.index');
    Route::get('farrowing/{farrowing}', [App\Http\Controllers\FarrowingController::class, 'show'])->middleware('permission:view_farrowing')->name('farrowing.show');
    Route::get('farrowing/{farrowing}/edit', [App\Http\Controllers\FarrowingController::class, 'edit'])->middleware('permission:manage_farrowing')->name('farrowing.edit');
    Route::put('farrowing/{farrowing}', [App\Http\Controllers\FarrowingController::class, 'update'])->middleware('permission:manage_farrowing')->name('farrowing.update');
    Route::post('farrowing/{farrowing}/piglet-deaths', [App\Http\Controllers\FarrowingController::class, 'recordPigletDeath'])->middleware('permission:manage_piglet_deaths')->name('farrowing.piglet-deaths.record');
    Route::put('farrowing/piglet-deaths/{pigletDeath}', [App\Http\Controllers\FarrowingController::class, 'updatePigletDeath'])->middleware('permission:manage_piglet_deaths')->name('farrowing.piglet-deaths.update');
    Route::delete('farrowing/piglet-deaths/{pigletDeath}', [App\Http\Controllers\FarrowingController::class, 'destroyPigletDeath'])->middleware('permission:manage_piglet_deaths')->name('farrowing.piglet-deaths.destroy');
    Route::post('gestation/{id}/farrowing', [App\Http\Controllers\PigManagementController::class, 'recordFarrowing'])->name('gestation.farrowing');
    Route::post('gestation/{id}/update-status', [App\Http\Controllers\PigManagementController::class, 'updateGestationStatus'])->name('gestation.update-status');

    // Piglet Tracking routes
    Route::get('piglets/tracking', [App\Http\Controllers\FarrowingController::class, 'pigletTracking'])->middleware('permission:view_farrowing')->name('piglets.tracking');

    // Audit trail and archived records
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');
    Route::get('pigs/archived', fn () => redirect()->route('audit-trail.index', ['tab' => 'archived']))->name('pigs.archived');
    Route::patch('pigs/{id}/restore', [App\Http\Controllers\PigManagementController::class, 'restorePig'])->name('pigs.restore');
    Route::patch('piglet-deaths/{id}/restore', [App\Http\Controllers\PigManagementController::class, 'restorePigletDeath'])->name('pigs.piglet-death-restore');

    // Unified Weaning Management Dashboard
    Route::get('weaning', [App\Http\Controllers\PigManagementController::class, 'weaningDashboard'])->name('weaning.dashboard');
    
    // Legacy routes (redirect to unified dashboard)
    Route::get('weaning/pending', function() { return redirect()->route('weaning.dashboard'); });
    Route::get('weaning/in-progress', function() { return redirect()->route('weaning.dashboard'); });
    Route::get('weaning/weaned', function() { return redirect()->route('weaning.dashboard'); });
    Route::get('weaning/overdue', function() { return redirect()->route('weaning.dashboard'); });
    Route::post('weaning/{id}/wean', [App\Http\Controllers\PigManagementController::class, 'recordWeaning'])->name('weaning.record');
    Route::post('weaning/litter/{farrowing}/wean', [App\Http\Controllers\PigManagementController::class, 'weanLitter'])->name('weaning.litter-wean');
    Route::post('weaning/{id}/complete-maturity', [App\Http\Controllers\PigManagementController::class, 'completeMaturity'])->name('weaning.complete-maturity');
    Route::post('weaning/{id}/update-status', [App\Http\Controllers\PigManagementController::class, 'updateWeaningStatus'])->name('weaning.update-status');
    Route::post('weaning/{id}/update-weight', [App\Http\Controllers\PigManagementController::class, 'updateWeaningWeight'])->name('weaning.update-weight');


    // Stock Type Management Routes
    Route::post('pigs/{id}/promote-to-breeding', [App\Http\Controllers\PigManagementController::class, 'promoteToBreeding'])->name('pigs.promote-to-breeding');
    Route::post('pigs/{id}/demote-to-standard', [App\Http\Controllers\PigManagementController::class, 'demoteToStandard'])->name('pigs.demote-to-standard');

    // Pig Detail View Route
    Route::get('pigs/{id}', [App\Http\Controllers\PigManagementController::class, 'show'])->name('pigs.show');
    Route::get('pigs/{id}/export/pdf', [App\Http\Controllers\PigManagementController::class, 'exportPigPdf'])->name('pigs.export.pdf');
    Route::delete('pigs/{id}', [App\Http\Controllers\PigManagementController::class, 'destroy'])->name('pigs.destroy');

    // Sale Management Routes
    Route::get('pigs/{id}/sale', [App\Http\Controllers\PigManagementController::class, 'saleForm'])->name('pigs.sale-form');
    Route::post('pigs/{id}/sale', [App\Http\Controllers\PigManagementController::class, 'recordSale'])->name('pigs.record-sale');
    Route::get('sales', [App\Http\Controllers\PigManagementController::class, 'salesIndex'])->name('sales.index');
    Route::get('sales/{id}', [App\Http\Controllers\PigManagementController::class, 'saleShow'])->name('sales.show');
    Route::post('sales/{id}/payment-status', [App\Http\Controllers\PigManagementController::class, 'updatePaymentStatus'])->name('sales.update-payment-status');
    Route::post('sales/{id}/abort', [App\Http\Controllers\PigSaleController::class, 'abort'])->name('sales.abort');
    Route::get('sales/{id}/audit', [App\Http\Controllers\PigSaleController::class, 'audit'])->name('sales.audit');
    Route::get('sales-export/csv', [App\Http\Controllers\PigManagementController::class, 'salesExportCsv'])->name('sales.export.csv');
    Route::get('sales-export/pdf', [App\Http\Controllers\PigManagementController::class, 'salesExportPdf'])->name('sales.export.pdf');

    // Standard Stock Routes
    Route::get('standard-stocks', [App\Http\Controllers\PigManagementController::class, 'standardIndex'])->name('standard-stocks.index');
    Route::get('standard-stocks/create', [App\Http\Controllers\PigManagementController::class, 'standardCreate'])->name('standard-stocks.create');
    Route::get('standard-stocks/{pig}/edit', [App\Http\Controllers\PigManagementController::class, 'standardEdit'])->name('standard-stocks.edit');
    Route::put('standard-stocks/{pig}', [App\Http\Controllers\PigManagementController::class, 'standardUpdate'])->name('standard-stocks.update');
    Route::get('standard-stocks-export/csv', [App\Http\Controllers\PigManagementController::class, 'standardExportCsv'])->name('standard-stocks.export.csv');
    Route::get('standard-stocks-export/pdf', [App\Http\Controllers\PigManagementController::class, 'standardExportPdf'])->name('standard-stocks.export.pdf');

    // Weight Management Routes
    Route::get('weights', [App\Http\Controllers\WeightManagementController::class, 'index'])->middleware('permission:view_weights')->name('weights.index');
    Route::get('weights/audit', [App\Http\Controllers\WeightManagementController::class, 'auditIndex'])->middleware('permission:view_weights')->name('weights.audit.index');
    Route::get('weights/create', [App\Http\Controllers\WeightManagementController::class, 'create'])->middleware('permission:record_weights')->name('weights.create');
    Route::post('weights', [App\Http\Controllers\WeightManagementController::class, 'store'])->middleware('permission:record_weights')->name('weights.store');
    Route::get('weights/{weight}/edit', [App\Http\Controllers\WeightManagementController::class, 'edit'])->middleware('permission:record_weights')->name('weights.edit');
    Route::get('weights/{weight}/audit', [App\Http\Controllers\WeightManagementController::class, 'audit'])->middleware('permission:view_weights')->name('weights.audit');
    Route::put('weights/{weight}', [App\Http\Controllers\WeightManagementController::class, 'update'])->middleware('permission:record_weights')->name('weights.update');
    Route::get('weights/reports', [App\Http\Controllers\WeightManagementController::class, 'reports'])->middleware('permission:view_weights')->name('weights.reports');
    Route::get('weights/export/excel', [App\Http\Controllers\WeightManagementController::class, 'exportWeeklyExcel'])->middleware('permission:view_weights')->name('weights.index.export-excel');
    Route::get('api/pigs/{pig}/previous-weight', [App\Http\Controllers\WeightManagementController::class, 'getPreviousWeight'])->middleware('permission:view_weights')->name('api.pigs.previous-weight');
    Route::get('api/pigs/{pig}/expected-weight', [App\Http\Controllers\WeightManagementController::class, 'getExpectedWeight'])->middleware('permission:view_weights')->name('api.pigs.expected-weight');
    Route::get('weights/reports/export-pdf', [App\Http\Controllers\WeightManagementController::class, 'exportPDF'])->middleware('permission:view_weights')->name('weights.reports.export-pdf');

    // PDF export for all weights (filtered)
    Route::get('weights/export/pdf', [App\Http\Controllers\WeightManagementController::class, 'exportAllPDF'])->middleware('permission:view_weights')->name('weights.index.export-pdf');

    // Weight Standards Routes (Admin, Manager and Superuser only)
    Route::middleware(['role:admin,manager,superuser'])->group(function () {
        Route::resource('weight-standards', App\Http\Controllers\WeightStandardsController::class);
        Route::get('weight-standards-bulk/create', [App\Http\Controllers\WeightStandardsController::class, 'bulkCreate'])->name('weight-standards.bulk-create');
        Route::post('weight-standards-bulk', [App\Http\Controllers\WeightStandardsController::class, 'bulkStore'])->name('weight-standards.bulk-store');
    });

    // Weight Alerts Management
    //
    // Acknowledgement below is already restricted to admin/superuser via the
    // role middleware; the resource itself was ungated (CF-P0-002).
    // NOTE ON ORDER: the read resource is constrained with whereNumber so that
    // GET /weight-alerts/{weight_alert} cannot capture the literal
    // /weight-alerts/create. Splitting a resource by permission reorders its
    // routes, and without the constraint `create` resolves as a show id and 404s.
    Route::resource('weight-alerts', App\Http\Controllers\WeightAlertsController::class)
        ->only(['index', 'show'])
        ->middleware('permission:view_weights')
        ->whereNumber('weight_alert');
    Route::resource('weight-alerts', App\Http\Controllers\WeightAlertsController::class)
        ->except(['edit', 'update', 'index', 'show'])
        ->middleware('permission:record_weights')
        ->whereNumber('weight_alert');
    Route::middleware(['role:admin,superuser'])->group(function () {
        Route::post('weight-alerts/{weightAlert}/acknowledge', [App\Http\Controllers\WeightAlertsController::class, 'acknowledge'])->name('weight-alerts.acknowledge');
        Route::post('weight-alerts/bulk-acknowledge', [App\Http\Controllers\WeightAlertsController::class, 'bulkAcknowledge'])->name('weight-alerts.bulk-acknowledge');
    });
    Route::get('weight-alerts-dashboard', [App\Http\Controllers\WeightAlertsController::class, 'dashboard'])->name('weight-alerts.dashboard');

    // Weight System Demo
    Route::get('weight-demo', function () {
        return view('weight-demo');
    })->name('weight-demo');


    // Structure and Room Management Routes
    //
    // CF-P0-002: this resource previously sat behind nothing but the broad
    // authenticated group, so any active user could create, rename or delete
    // structures. Reads and writes are split — deleting a structure detaches
    // every room and pen assignment beneath it.
    // whereNumber for the same reason as weight-alerts above: without it
    // GET /structures/{structure} swallows GET /structures/create.
    Route::resource('structures', App\Http\Controllers\StructureController::class)
        ->only(['index', 'show'])
        ->middleware('permission:view_facilities')
        ->whereNumber('structure');
    Route::resource('structures', App\Http\Controllers\StructureController::class)
        ->except(['index', 'show'])
        ->middleware('permission:manage_facilities')
        ->whereNumber('structure');
    Route::post('structures/{structure}/rooms', [App\Http\Controllers\RoomController::class, 'store'])->middleware('permission:manage_facilities')->name('structures.rooms.store');
    Route::delete('structures/{structure}/rooms/{room}', [App\Http\Controllers\RoomController::class, 'destroy'])->middleware('permission:manage_facilities')->name('structures.rooms.destroy');
    Route::get('rooms/{room}', [App\Http\Controllers\RoomController::class, 'show'])->middleware('permission:view_facilities')->name('rooms.show');
    Route::get('rooms/{room}/edit', [App\Http\Controllers\RoomController::class, 'edit'])->middleware('permission:manage_facilities')->name('rooms.edit');
    Route::put('rooms/{room}', [App\Http\Controllers\RoomController::class, 'update'])->middleware('permission:manage_facilities')->name('rooms.update');
    Route::post('rooms', [App\Http\Controllers\RoomController::class, 'storeGlobal'])->middleware('permission:manage_facilities')->name('rooms.store');
    Route::get('api/structures/{structure}/rooms', [App\Http\Controllers\RoomController::class, 'listByStructure'])->middleware('permission:view_facilities')->name('api.structures.rooms');

    // Settings Routes
    Route::get('settings/setup', [App\Http\Controllers\SettingsController::class, 'setup'])->name('settings.setup');
    Route::post('settings/setup/breeding', [App\Http\Controllers\SettingsController::class, 'saveBreeding'])->name('settings.setup.breeding.save');
    Route::post('settings/setup/breeding/reset', [App\Http\Controllers\SettingsController::class, 'resetBreeding'])->name('settings.setup.breeding.reset');
    Route::get('settings/activity-log', [App\Http\Controllers\SettingsController::class, 'activityLog'])->name('settings.activity-log');

    // Modules & Features (Phase 1). Guarded by permission only — deliberately
    // NOT by a feature flag, because gating the flag screen behind a flag can
    // lock an administrator out of the switch that turns it back on.
    Route::get('settings/modules', [App\Http\Controllers\Settings\ModuleFeatureController::class, 'index'])
        ->middleware('permission:manage_feature_flags')
        ->name('settings.modules');
    Route::put('settings/modules', [App\Http\Controllers\Settings\ModuleFeatureController::class, 'update'])
        ->middleware('permission:manage_feature_flags')
        ->name('settings.modules.update');
    Route::post('settings/modules/preset', [App\Http\Controllers\Settings\ModuleFeatureController::class, 'applyPreset'])
        ->middleware('permission:manage_feature_flags')
        ->name('settings.modules.preset');
    
    // User Management Routes (Superuser only)
    Route::get('settings/users', [App\Http\Controllers\SettingsController::class, 'users'])->name('settings.users');
    Route::post('settings/users', [App\Http\Controllers\SettingsController::class, 'storeUser'])->name('settings.users.store');
    Route::put('settings/users/{user}', [App\Http\Controllers\SettingsController::class, 'updateUser'])->name('settings.users.update');
    Route::patch('settings/users/{user}/status', [App\Http\Controllers\SettingsController::class, 'updateUserStatus'])->name('settings.users.status');
    Route::delete('settings/users/{user}', [App\Http\Controllers\SettingsController::class, 'deleteUser'])->name('settings.users.delete');


    //Feed Management Routes
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
    Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
    Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
    Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');
    Route::delete('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');

    Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
    Route::post('/purchase-requests/{purchaseRequest}/decline', [PurchaseRequestController::class, 'decline'])->name('purchase-requests.decline');
    Route::post('/purchase-requests/{purchaseRequest}/mark-paid', [PurchaseRequestController::class, 'markAsPaid'])->name('purchase-requests.mark-paid');
    Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
    Route::get('/raw-materials-stock', [RawMaterialController::class, 'index'])->middleware('permission:view_raw_materials')->name('raw-materials.index');
    Route::post('/raw-materials-stock', [RawMaterialController::class, 'store'])->middleware('permission:manage_raw_materials')->name('raw-materials.store');
    Route::put('/raw-materials-stock/{rawMaterial}', [RawMaterialController::class, 'update'])->middleware('permission:manage_raw_materials')->name('raw-materials.update');
    Route::post('/raw-materials-stock/{rawMaterial}/receive', [RawMaterialController::class, 'receiveStock'])->middleware('permission:manage_raw_materials')->name('raw-materials.receive');
    Route::post('/raw-materials-stock/{rawMaterial}/adjust', [RawMaterialController::class, 'adjustStock'])->middleware('permission:manage_raw_materials')->name('raw-materials.adjust');
    Route::get('/feed-production', [FeedProductionController::class, 'index'])->middleware('permission:view_feed_production')->name('feed-production.index');
    Route::post('/feed-production/feed-types', [FeedProductionController::class, 'storeFeedType'])->middleware('permission:manage_feed_production')->name('feed-production.feed-types.store');
    Route::post('/feed-production/recipes', [FeedProductionController::class, 'storeRecipe'])->middleware('permission:manage_feed_production')->name('feed-production.recipes.store');
    Route::post('/feed-production/mix', [FeedProductionController::class, 'mixFeed'])->middleware('permission:manage_feed_production')->name('feed-production.mix');
    Route::get('/planning-reports', [FeedPlanningController::class, 'index'])->name('planning-reports.index');

    // Email Settings Routes (Superuser only)
    Route::get('settings/email', [App\Http\Controllers\EmailSettingController::class, 'index'])->name('settings.email');
    Route::put('settings/email', [App\Http\Controllers\EmailSettingController::class, 'update'])->name('settings.email.update');
    Route::post('settings/email/test-connection', [App\Http\Controllers\EmailSettingController::class, 'testConnection'])->name('settings.email.test-connection');
    Route::post('settings/email/send-test', [App\Http\Controllers\EmailSettingController::class, 'sendTestEmail'])->name('settings.email.send-test');

    //General Settings Route(Superuser and Amdin only)
    // Add 'settings.' to the name here
    Route::get('settings/general-settings', [SettingsController::class, 'general'])->name('settings.general');
    Route::put('settings/general-settings', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');

    // PERFORMANCE METRICS ROUTES
    Route::get('metrics', [PerformanceMetricController::class, 'index'])->name('performance_metrics.index');
    Route::get('settings/metrics', [PerformanceMetricController::class, 'index'])->name('settings.metrics');

    
    // System Reports Routes
    Route::get('settings/reports', [App\Http\Controllers\SettingsController::class, 'systemReports'])->name('settings.reports');
    Route::get('settings/reports/export/pdf', [App\Http\Controllers\SettingsController::class, 'exportSystemReportsPdf'])->name('settings.reports.export.pdf');
    Route::get('settings/reports/export/csv', [App\Http\Controllers\SettingsController::class, 'exportSystemReportsCsv'])->name('settings.reports.export.csv');

    // Death Management Routes
    Route::get('pigs/{id}/death', [App\Http\Controllers\PigManagementController::class, 'deathForm'])->name('pigs.death-form');
    Route::post('pigs/{id}/death', [App\Http\Controllers\PigManagementController::class, 'recordDeath'])->name('pigs.record-death');
    Route::get('deaths', [App\Http\Controllers\PigManagementController::class, 'deathsIndex'])->name('deaths.index');
    Route::get('deaths/{id}', [App\Http\Controllers\PigManagementController::class, 'deathShow'])->name('deaths.show');
    Route::get('deaths-export/csv', [App\Http\Controllers\PigManagementController::class, 'deathsExportCsv'])->name('deaths.export.csv');
    Route::get('deaths-export/pdf', [App\Http\Controllers\PigManagementController::class, 'deathsExportPdf'])->name('deaths.export.pdf');


    // === Location API Routes (NEW) ===

    // API routes for the cascading Structure/Room/Pig selection
    Route::prefix('api')->group(function () {
    // 1. Fetches Rooms belonging to a selected Structure
    Route::get('structures/{structureId}/rooms', [RoomController::class, 'apiListRoomsByStructure']);

    // 2. Fetches Pigs belonging to a selected Room
    Route::get('rooms/{roomId}/pigs', [RoomController::class, 'apiListPigsByRoom']);
    });


        /*
    |---------------------------------------------------------------------
    | Finance routes (Phase 1d; outer boundary added in remediation Phase 2)
    |
    | Two layers of authorisation:
    |  1. Outer: `finance.access` requires the user to hold at least one
    |     finance-module permission. A user with none cannot reach any
    |     /finance URL. This is NOT `permission:view_finance` on purpose —
    |     farmmanager and mill_operator legitimately hold narrow finance
    |     permissions (vendor invoices, financial reports, inventory value,
    |     loans, treasury) without holding view_finance, and a blanket gate
    |     would either lock them out or require granting them the whole
    |     module. See EnsureFinanceAccess for the seeded role evidence.
    |  2. Inner: `permission:` middleware enforces the specific action on
    |     every route. Reads that expose balances, ledgers, statements,
    |     suppliers, payments and refunds require `view_finance`.
    |
    | CF-P0-003 / CF-P0-004: before remediation this group had NO outer
    | middleware and 69 of 207 finance routes had no permission gate at all,
    | including state-changing bank-account, reconciliation, credit-hold and
    | WHT-certificate operations. Both layers above now exist; keep them.
    |---------------------------------------------------------------------
    */
    Route::prefix('finance')->name('finance.')->middleware('finance.access')->group(function () {

        // Dashboard + trial balance — view_finance gate.
        Route::middleware('permission:view_finance')->group(function () {
            Route::get('/', \App\Http\Controllers\Finance\FinanceDashboardController::class)
                ->name('dashboard');

            // Farm-facing money screen (Phase 3). Gated on the Finance Overview
            // feature AND view_finance, per the standing rule that a flag hides
            // a surface but never authorises one. The ledger dashboard above is
            // kept and moves under Advanced Accounting rather than being
            // deleted (blueprint §25).
            Route::get('/overview', \App\Http\Controllers\Finance\FinanceOverviewController::class)
                ->middleware('feature:' . \App\Support\Features\FeatureCatalog::FINANCE_OVERVIEW)
                ->name('overview');

            Route::get('/trial-balance', \App\Http\Controllers\Finance\TrialBalanceController::class)
                ->name('trial-balance');
        });

        // Record Expense (Phase 4). Feature-gated for presentation, permission-
        // gated for access. record_expenses is deliberately narrower than
        // manage_finance: recording routine spend is a daily operational act,
        // not an accounting one.
        Route::prefix('expenses')->name('expenses.')
            ->middleware('feature:' . \App\Support\Features\FeatureCatalog::FINANCE_QUICK_EXPENSE)
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Finance\QuickExpenseController::class, 'index'])
                    ->middleware('permission:record_expenses,view_finance')
                    ->name('index');
                Route::get('/create', [\App\Http\Controllers\Finance\QuickExpenseController::class, 'create'])
                    ->middleware('permission:record_expenses')
                    ->name('create');
                Route::post('/', [\App\Http\Controllers\Finance\QuickExpenseController::class, 'store'])
                    ->middleware('permission:record_expenses')
                    ->name('store');
            });

        // Chart of Accounts.
        Route::prefix('coa')->name('coa.')->group(function () {
            // Listing/show open to view_finance.
            Route::middleware('permission:view_finance')->group(function () {
                Route::get('/', [\App\Http\Controllers\Finance\CoaController::class, 'index'])->name('index');
                Route::get('/{account}', [\App\Http\Controllers\Finance\CoaController::class, 'show'])
                    ->whereNumber('account')
                    ->name('show');
            });

            // Create / edit / activate — manage_coa gate.
            Route::middleware('permission:manage_coa')->group(function () {
                Route::get('/create', fn() => view('finance.coa.create'))->name('create');
                Route::post('/', [\App\Http\Controllers\Finance\CoaController::class, 'store'])->name('store');
                Route::get('/{account}/edit', fn(\App\Models\Finance\Account $account) => view('finance.coa.edit', compact('account')))
                    ->whereNumber('account')
                    ->name('edit');
                Route::patch('/{account}', [\App\Http\Controllers\Finance\CoaController::class, 'update'])
                    ->whereNumber('account')
                    ->name('update');
                Route::patch('/{account}/toggle-active', [\App\Http\Controllers\Finance\CoaController::class, 'toggleActive'])
                    ->whereNumber('account')
                    ->name('toggle-active');
            });
        });

        // Journals (manual entry + approval queue).
        Route::prefix('journals')->name('journals.')->group(function () {
            // Index + show open to view_finance.
            Route::middleware('permission:view_finance')->group(function () {
                Route::get('/', [\App\Http\Controllers\Finance\JournalController::class, 'index'])->name('index');
                Route::get('/{batch}', [\App\Http\Controllers\Finance\JournalController::class, 'show'])
                    ->whereNumber('batch')
                    ->name('show');
            });

            // Create + propose — propose_manual_journal.
            Route::middleware('permission:propose_manual_journal')->group(function () {
                Route::get('/create/new', [\App\Http\Controllers\Finance\JournalController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Finance\JournalController::class, 'store'])->name('store');
            });

            // Override path — approve_own_journals.
            Route::middleware('permission:approve_own_journals')->group(function () {
                Route::post('/store-and-approve', [\App\Http\Controllers\Finance\JournalController::class, 'storeAndApprove'])
                    ->name('store-and-approve');
            });

            // Approve + reject — approve_journal.
            Route::middleware('permission:approve_journal')->group(function () {
                Route::post('/{batch}/approve', [\App\Http\Controllers\Finance\JournalController::class, 'approve'])
                    ->whereNumber('batch')
                    ->name('approve');
                Route::post('/{batch}/reject', [\App\Http\Controllers\Finance\JournalController::class, 'reject'])
                    ->whereNumber('batch')
                    ->name('reject');
            });

            // Reverse — reverse_journal.
            Route::middleware('permission:reverse_journal')->post('/{batch}/reverse',
                [\App\Http\Controllers\Finance\JournalController::class, 'reverse']
            )
            ->whereNumber('batch')
            ->name('reverse');
        });

                /*
        |---------------------------------------------------------------
        | Customer master (Phase 2a)
        |---------------------------------------------------------------
        */
        Route::prefix('customers')->name('customers.')->group(function () {

            // Listing/show — view_finance.
            Route::middleware('permission:view_finance')->group(function () {
                Route::get('/', [\App\Http\Controllers\Finance\CustomerController::class, 'index'])->name('index');
                Route::get('/{customer}', [\App\Http\Controllers\Finance\CustomerController::class, 'show'])
                    ->whereNumber('customer')
                    ->name('show');
            });

            // Create/edit/credit-hold — manage_customers.
            Route::middleware('permission:manage_customers')->group(function () {
                Route::get('/create/new', [\App\Http\Controllers\Finance\CustomerController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Finance\CustomerController::class, 'store'])->name('store');

                Route::get('/{customer}/edit', function (\App\Models\Counterparty $customer, \App\Support\CurrentOrganization $currentOrg) {
                    $orgId = $currentOrg->resolve()?->id;
                    if ($orgId !== null && $customer->organization_id !== null && (int) $customer->organization_id !== (int) $orgId) {
                        abort(403);
                    }
                    $categories = \App\Models\CustomerCategory::active()
                        ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                        ->get();
                    return view('finance.customers.edit', compact('customer', 'categories'));
                })->whereNumber('customer')->name('edit');

                Route::patch('/{customer}', [\App\Http\Controllers\Finance\CustomerController::class, 'update'])
                    ->whereNumber('customer')
                    ->name('update');

                Route::post('/{customer}/place-on-hold', [\App\Http\Controllers\Finance\CustomerController::class, 'placeOnHold'])
                    ->whereNumber('customer')
                    ->name('place-on-hold');
                Route::patch('/{customer}/clear-hold', [\App\Http\Controllers\Finance\CustomerController::class, 'clearHold'])
                    ->whereNumber('customer')
                    ->name('clear-hold');

                // Addresses
                Route::post('/{customer}/addresses', [\App\Http\Controllers\Finance\CustomerController::class, 'storeAddress'])
                    ->whereNumber('customer')
                    ->name('addresses.store');
                Route::delete('/addresses/{address}', [\App\Http\Controllers\Finance\CustomerController::class, 'deleteAddress'])
                    ->whereNumber('address')
                    ->name('addresses.destroy');

                // Contacts
                Route::post('/{customer}/contacts', [\App\Http\Controllers\Finance\CustomerController::class, 'storeContact'])
                    ->whereNumber('customer')
                    ->name('contacts.store');
                Route::delete('/contacts/{contact}', [\App\Http\Controllers\Finance\CustomerController::class, 'deleteContact'])
                    ->whereNumber('contact')
                    ->name('contacts.destroy');

                Route::get('/customers/{customer}/ledger', [\App\Http\Controllers\Finance\CustomerController::class, 'ledger'])
                    ->whereNumber('customer')
                    ->name('customers.ledger');
            });
        });

                /*
        |---------------------------------------------------------------
        | Invoices (Phase 2b)
        |---------------------------------------------------------------
        */
        Route::prefix('invoices')->name('invoices.')->group(function () {

            // Listing/show — view_finance.
            Route::middleware('permission:view_finance')->group(function () {
                Route::get('/', [\App\Http\Controllers\Finance\InvoiceController::class, 'index'])->name('index');
                Route::get('/{invoice}', [\App\Http\Controllers\Finance\InvoiceController::class, 'show'])
                    ->whereNumber('invoice')
                    ->name('show');
            });

            // Create + cancel-draft — create_invoice.
            Route::middleware('permission:create_invoice')->group(function () {
                Route::get('/create/new', [\App\Http\Controllers\Finance\InvoiceController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Finance\InvoiceController::class, 'store'])->name('store');
                Route::post('/{invoice}/cancel', [\App\Http\Controllers\Finance\InvoiceController::class, 'cancel'])
                    ->whereNumber('invoice')
                    ->name('cancel');
            });

            // Post to GL — post_invoice.
            Route::middleware('permission:post_invoice')->group(function () {
                Route::post('/{invoice}/post', [\App\Http\Controllers\Finance\InvoiceController::class, 'post'])
                    ->whereNumber('invoice')
                    ->name('post');
            });

            // Reverse — reverse_journal (reuses existing permission).
            Route::middleware('permission:reverse_journal')->group(function () {
                Route::post('/{invoice}/reverse', [\App\Http\Controllers\Finance\InvoiceController::class, 'reverse'])
                    ->whereNumber('invoice')
                    ->name('reverse');
            });
        });

            /* Receipts (Phase 2c) */
        Route::prefix('receipts')->name('receipts.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Finance\ReceiptController::class, 'index'])
                ->middleware('permission:view_finance')
                ->name('index');
            Route::get('/create',          [\App\Http\Controllers\Finance\ReceiptController::class, 'create'])
                ->middleware('permission:create_receipt')
                ->name('create');
            Route::post('/',               [\App\Http\Controllers\Finance\ReceiptController::class, 'store'])
                ->middleware('permission:create_receipt')
                ->name('store');
            Route::get('/{receipt}',       [\App\Http\Controllers\Finance\ReceiptController::class, 'show'])
                ->whereNumber('receipt')
                ->middleware('permission:view_finance')
                ->name('show');
            Route::post('/{receipt}/allocations',
                                        [\App\Http\Controllers\Finance\ReceiptController::class, 'storeAllocation'])
                ->whereNumber('receipt')
                ->middleware('permission:create_receipt')
                ->name('allocations.store');
            Route::patch('/allocations/{allocation}/reverse',
                                        [\App\Http\Controllers\Finance\ReceiptController::class, 'reverseAllocation'])
                ->whereNumber('allocation')
                ->middleware('permission:create_receipt')
                ->name('allocations.reverse');
            Route::post('/{receipt}/reverse',
                                        [\App\Http\Controllers\Finance\ReceiptController::class, 'reverse'])
                ->whereNumber('receipt')
                ->middleware('permission:reverse_journal')
                ->name('reverse');
        });

        /* Customer ledger (Phase 2c) — discloses the full AR transaction
           history for one customer. */
        Route::get('/customers/{customer}/ledger',
                                        [\App\Http\Controllers\Finance\CustomerController::class, 'ledger'])
            ->whereNumber('customer')
            ->middleware('permission:view_finance')
            ->name('customers.ledger');

        /* Credit hold governs whether a customer can transact on credit —
           placing or clearing a hold is a credit-control decision. */
        Route::get('/customers/{customer}/credit-hold',
                              [\App\Http\Controllers\Finance\CustomerController::class, 'editCreditHold'])
            ->whereNumber('customer')
            ->middleware('permission:manage_customers')
            ->name('customers.credit-hold.edit');
        Route::post('/customers/{customer}/credit-hold',
                              [\App\Http\Controllers\Finance\CustomerController::class, 'updateCreditHold'])
            ->whereNumber('customer')
            ->middleware('permission:manage_customers')
            ->name('customers.credit-hold.update');


            /* AR Ageing (Phase 2d) */
            Route::prefix('ageing')->name('ageing.')->group(function () {
                // AR (existing — back-compat aliases). These expose customer
                // receivable balances and were previously ungated.
                Route::get('/',        [\App\Http\Controllers\Finance\AgeingController::class, 'index'])
                    ->middleware('permission:view_finance')->name('index');
                Route::get('/ar',      [\App\Http\Controllers\Finance\AgeingController::class, 'ar'])
                    ->middleware('permission:view_finance')->name('ar');
                Route::get('/export',  [\App\Http\Controllers\Finance\AgeingController::class, 'exportAr'])
                    ->middleware('permission:export_finance')->name('export');
    
                // AP (new in 3d)
                Route::get('/ap',         [\App\Http\Controllers\Finance\AgeingController::class, 'ap'])
                    ->middleware('permission:view_ap_ageing')->name('ap');
                Route::get('/export-ap',  [\App\Http\Controllers\Finance\AgeingController::class, 'exportAp'])
                    ->middleware('permission:view_ap_ageing')->name('export-ap');
            });

        /* Customer Statements (Phase 2d) — the PDF is the same disclosure as
           the HTML view, so both carry the same gate. */
        Route::get('/customers/{customer}/statement',
                                        [\App\Http\Controllers\Finance\StatementController::class, 'show'])
            ->whereNumber('customer')
            ->middleware('permission:view_finance')
            ->name('statements.show');
        Route::get('/customers/{customer}/statement/pdf',
                                        [\App\Http\Controllers\Finance\StatementController::class, 'download'])
            ->whereNumber('customer')
            ->middleware('permission:view_finance')
            ->name('statements.download');

        /* Refunds (Phase 2d) */
        Route::prefix('refunds')->name('refunds.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Finance\RefundController::class, 'index'])
                ->middleware('permission:view_finance')
                ->name('index');
            Route::get('/create',          [\App\Http\Controllers\Finance\RefundController::class, 'create'])
                ->middleware('permission:create_refund')
                ->name('create');
            Route::post('/',               [\App\Http\Controllers\Finance\RefundController::class, 'store'])
                ->middleware('permission:create_refund')
                ->name('store');
            Route::get('/{refund}',        [\App\Http\Controllers\Finance\RefundController::class, 'show'])
                ->whereNumber('refund')
                ->middleware('permission:view_finance')
                ->name('show');
            Route::post('/{refund}/post',  [\App\Http\Controllers\Finance\RefundController::class, 'post'])
                ->whereNumber('refund')
                ->middleware('permission:approve_refund')
                ->name('post');
            Route::post('/{refund}/reverse',
                                        [\App\Http\Controllers\Finance\RefundController::class, 'reverse'])
                ->whereNumber('refund')
                ->middleware('permission:reverse_journal')
                ->name('reverse');
            Route::post('/{refund}/cancel',[\App\Http\Controllers\Finance\RefundController::class, 'cancel'])
                ->whereNumber('refund')
                ->middleware('permission:create_refund')
                ->name('cancel');
        });

        /* Finance Attachments (Phase 2d) */
        Route::prefix('attachments')->name('attachments.')->group(function () {
            // The {type} constraint is derived from AttachmentController::PARENT_MAP
            // so the route, the resolver and the permission map cannot drift
            // apart again (CF-P1-019). Authorisation for all three actions is
            // parent-aware and lives in the controller, not in route middleware,
            // because the required permission depends on the parent record.
            Route::post('/{type}/{id}',    [\App\Http\Controllers\Finance\AttachmentController::class, 'store'])
                ->where([
                    'type' => \App\Http\Controllers\Finance\AttachmentController::supportedTypePattern(),
                    'id'   => '[0-9]+',
                ])
                ->name('store');
            Route::get('/{attachment}/download',
                                        [\App\Http\Controllers\Finance\AttachmentController::class, 'download'])
                ->whereNumber('attachment')
                ->name('download');
            Route::delete('/{attachment}', [\App\Http\Controllers\Finance\AttachmentController::class, 'destroy'])
                ->whereNumber('attachment')
                ->name('destroy');
        });

        
            /* Suppliers (Phase 3a) */
        Route::prefix('suppliers')->name('suppliers.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Finance\SupplierController::class, 'index'])
                ->middleware('permission:view_finance')
                ->name('index');
            Route::get('/create',            [\App\Http\Controllers\Finance\SupplierController::class, 'create'])
                ->middleware('permission:manage_suppliers')
                ->name('create');
            Route::post('/',                 [\App\Http\Controllers\Finance\SupplierController::class, 'store'])
                ->middleware('permission:manage_suppliers')
                ->name('store');
            Route::get('/{supplier}',        [\App\Http\Controllers\Finance\SupplierController::class, 'show'])
                ->whereNumber('supplier')
                ->middleware('permission:view_finance')
                ->name('show');
            Route::get('/{supplier}/edit',   [\App\Http\Controllers\Finance\SupplierController::class, 'edit'])
                ->whereNumber('supplier')
                ->middleware('permission:manage_suppliers')
                ->name('edit');
            Route::patch('/{supplier}',      [\App\Http\Controllers\Finance\SupplierController::class, 'update'])
                ->whereNumber('supplier')
                ->middleware('permission:manage_suppliers')
                ->name('update');
            Route::post('/{supplier}/deactivate',
                                            [\App\Http\Controllers\Finance\SupplierController::class, 'deactivate'])
                ->whereNumber('supplier')
                ->middleware('permission:manage_suppliers')
                ->name('deactivate');
        });

                /* Supplier Statements (Phase 3d) */
                Route::prefix('supplier-statements')->name('supplier-statements.')->group(function () {
                    // Reads: supplier balances and statement detail. OR'd with
                    // manage_supplier_statements because the roles that
                    // administer statements must be able to read them, and
                    // view_finance already admits the suppliers index.
                    Route::middleware('permission:manage_supplier_statements,view_finance')->group(function () {
                        Route::get('/',                             [\App\Http\Controllers\Finance\SupplierStatementController::class, 'index'])->name('index');
                        Route::get('/{statement}',                  [\App\Http\Controllers\Finance\SupplierStatementController::class, 'show'])->whereNumber('statement')->name('show');
                    });

                    // Writes: creating statements and mutating their lines or
                    // reconciliation state.
                    Route::middleware('permission:manage_supplier_statements')->group(function () {
                    Route::get('/create',                           [\App\Http\Controllers\Finance\SupplierStatementController::class, 'create'])->name('create');
                    Route::post('/',                                [\App\Http\Controllers\Finance\SupplierStatementController::class, 'store'])->name('store');

                    Route::post('/{statement}/lines',               [\App\Http\Controllers\Finance\SupplierStatementController::class, 'storeLine'])->name('lines.store');
                    Route::post('/{statement}/lines/csv',           [\App\Http\Controllers\Finance\SupplierStatementController::class, 'uploadCsv'])->name('lines.csv');
                    Route::delete('/{statement}/lines/{line}',      [\App\Http\Controllers\Finance\SupplierStatementController::class, 'destroyLine'])->name('lines.destroy');
                    Route::post('/{statement}/lines/{line}/resolve',[\App\Http\Controllers\Finance\SupplierStatementController::class, 'resolveManually'])->name('lines.resolve');
        
                    Route::post('/{statement}/reconcile',           [\App\Http\Controllers\Finance\SupplierStatementController::class, 'reconcile'])->name('reconcile');
                    Route::post('/{statement}/flag',                [\App\Http\Controllers\Finance\SupplierStatementController::class, 'markFlagged'])->name('flag');
                    Route::post('/{statement}/reconciled',          [\App\Http\Controllers\Finance\SupplierStatementController::class, 'markReconciled'])->name('reconciled');
                    Route::post('/{statement}/archive',             [\App\Http\Controllers\Finance\SupplierStatementController::class, 'archive'])->name('archive');
                    });
                });

           /* Vendor Invoices (Phase 3b) */
        Route::prefix('vendor-invoices')->name('vendor-invoices.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'index'])
                ->middleware('permission:view_finance,create_purchase_request_vendor_invoice')
                ->name('index');
            Route::get('/create',                    [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'create'])
                ->middleware('permission:create_vendor_invoice,create_purchase_request_vendor_invoice')
                ->name('create');
            Route::post('/',                         [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'store'])
                ->middleware('permission:create_vendor_invoice,create_purchase_request_vendor_invoice')
                ->name('store');
            Route::get('/{invoice}',                 [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'show'])
                ->whereNumber('invoice')
                ->middleware('permission:view_finance,create_purchase_request_vendor_invoice')
                ->name('show');
            Route::post('/{invoice}/submit',         [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'submitForApproval'])
                ->whereNumber('invoice')
                ->middleware('permission:create_vendor_invoice,create_purchase_request_vendor_invoice')
                ->name('submit');
            Route::post('/{invoice}/post',           [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'post'])
                ->whereNumber('invoice')
                ->middleware('permission:approve_vendor_invoice')
                ->name('post');
            Route::post('/{invoice}/cancel',         [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'cancel'])
                ->whereNumber('invoice')
                ->middleware('permission:create_vendor_invoice,create_purchase_request_vendor_invoice')
                ->name('cancel');
            Route::post('/{invoice}/reverse',        [\App\Http\Controllers\Finance\VendorInvoiceController::class, 'reverse'])
                ->whereNumber('invoice')
                ->middleware('permission:reverse_journal')
                ->name('reverse');
        });

            /* Payments (Phase 3c) */
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Finance\PaymentController::class, 'index'])
                ->middleware('permission:view_finance')
                ->name('index');
            Route::get('/create',                    [\App\Http\Controllers\Finance\PaymentController::class, 'create'])
                ->middleware('permission:create_payment')
                ->name('create');
            Route::post('/',                         [\App\Http\Controllers\Finance\PaymentController::class, 'store'])
                ->middleware('permission:create_payment')
                ->name('store');
            Route::get('/{payment}',                 [\App\Http\Controllers\Finance\PaymentController::class, 'show'])
                ->whereNumber('payment')
                ->middleware('permission:view_finance')
                ->name('show');
            Route::post('/{payment}/allocate',       [\App\Http\Controllers\Finance\PaymentController::class, 'allocate'])
                ->whereNumber('payment')
                ->middleware('permission:create_payment')
                ->name('allocate');
            Route::post('/{payment}/auto-allocate',  [\App\Http\Controllers\Finance\PaymentController::class, 'autoAllocate'])
                ->whereNumber('payment')
                ->middleware('permission:create_payment')
                ->name('auto-allocate');
            Route::post('/allocations/{allocation}/reverse', [\App\Http\Controllers\Finance\PaymentController::class, 'reverseAllocation'])
                ->whereNumber('allocation')
                ->middleware('permission:create_payment')
                ->name('reverse-allocation');
            Route::post('/{payment}/reverse',        [\App\Http\Controllers\Finance\PaymentController::class, 'reverse'])
                ->whereNumber('payment')
                ->middleware('permission:reverse_journal')
                ->name('reverse');
            Route::post('/{payment}/reconcile',      [\App\Http\Controllers\Finance\PaymentController::class, 'reconcile'])
                ->whereNumber('payment')
                ->middleware('permission:create_payment')
                ->name('reconcile');
            Route::post('/{payment}/unreconcile',    [\App\Http\Controllers\Finance\PaymentController::class, 'unreconcile'])
                ->whereNumber('payment')
                ->middleware('permission:create_payment')
                ->name('unreconcile');
        });

        /* Payment Runs (Phase 3c) */
        Route::prefix('payment-runs')->name('payment-runs.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Finance\PaymentRunController::class, 'index'])
                ->middleware('permission:view_finance')
                ->name('index');
            Route::get('/create',                    [\App\Http\Controllers\Finance\PaymentRunController::class, 'create'])
                ->middleware('permission:create_payment')
                ->name('create');
            Route::post('/',                         [\App\Http\Controllers\Finance\PaymentRunController::class, 'store'])
                ->middleware('permission:create_payment')
                ->name('store');
            Route::get('/{run}',                     [\App\Http\Controllers\Finance\PaymentRunController::class, 'show'])
                ->whereNumber('run')
                ->middleware('permission:view_finance')
                ->name('show');
            Route::post('/{run}/lines',              [\App\Http\Controllers\Finance\PaymentRunController::class, 'addLine'])
                ->whereNumber('run')
                ->middleware('permission:create_payment')
                ->name('add-line');
            Route::post('/lines/{line}/remove',      [\App\Http\Controllers\Finance\PaymentRunController::class, 'removeLine'])
                ->whereNumber('line')
                ->middleware('permission:create_payment')
                ->name('remove-line');
            Route::post('/{run}/execute',            [\App\Http\Controllers\Finance\PaymentRunController::class, 'execute'])
                ->whereNumber('run')
                ->middleware('permission:create_payment')
                ->name('execute');
            Route::post('/{run}/cancel',             [\App\Http\Controllers\Finance\PaymentRunController::class, 'cancel'])
                ->whereNumber('run')
                ->middleware('permission:create_payment')
                ->name('cancel');
            Route::post('/{run}/reverse',            [\App\Http\Controllers\Finance\PaymentRunController::class, 'reverse'])
                ->whereNumber('run')
                ->middleware('permission:reverse_journal')
                ->name('reverse');
        });

        Route::prefix('wht-certificates')->name('wht-certificates.')->group(function () {
            // Reads: tax certificates disclose supplier and withholding detail.
            // Phase3dPermissionsSeeder defines manage_wht_certificates as
            // covering "draft, refresh, view, void, download", and does not
            // grant view_finance to the roles that manage certificates — so
            // either permission admits a read. Gating on view_finance alone
            // would lock accountants out of certificates they administer.
            Route::middleware('permission:manage_wht_certificates,view_finance')->group(function () {
                Route::get('/',                  [\App\Http\Controllers\Finance\WhtCertificateController::class, 'index'])->name('index');
                Route::get('/{cert}',            [\App\Http\Controllers\Finance\WhtCertificateController::class, 'show'])->whereNumber('cert')->name('show');
                Route::get('/{cert}/pdf',        [\App\Http\Controllers\Finance\WhtCertificateController::class, 'downloadPdf'])->name('pdf');
            });

            // Writes: creating/refreshing/voiding a certificate.
            Route::middleware('permission:manage_wht_certificates')->group(function () {
                Route::get('/create',            [\App\Http\Controllers\Finance\WhtCertificateController::class, 'create'])->name('create');
                Route::post('/',                 [\App\Http\Controllers\Finance\WhtCertificateController::class, 'store'])->name('store');
                Route::post('/{cert}/refresh',   [\App\Http\Controllers\Finance\WhtCertificateController::class, 'refresh'])->name('refresh');
                Route::post('/{cert}/void',      [\App\Http\Controllers\Finance\WhtCertificateController::class, 'voidCertificate'])->name('void');
            });

            // Issuance is the statutory act and has its own permission.
            Route::middleware('permission:issue_wht_certificate')
                ->post('/{cert}/issue',          [\App\Http\Controllers\Finance\WhtCertificateController::class, 'issue'])->name('issue');
        });

                // ============================================================
        // Bank Accounts (Phase 3e)
        // ============================================================
        Route::prefix('bank-accounts')->name('bank-accounts.')->group(function () {
            // Reads: account numbers, SWIFT codes and opening balances.
            // Deliberately NOT OR'd with view_finance: Phase3ePermissionsSeeder
            // separates view_bank_accounts from view_finance precisely because
            // account configuration is more sensitive than general finance data
            // ("Default is narrower; sites that want it broad can grant
            // explicitly"). Adding view_finance here would widen access against
            // that documented intent.
            Route::middleware('permission:view_bank_accounts')->group(function () {
                Route::get('/',              [\App\Http\Controllers\Finance\BankAccountController::class, 'index'])->name('index');
                Route::get('/{bankAccount}', [\App\Http\Controllers\Finance\BankAccountController::class, 'show'])->whereNumber('bankAccount')->name('show');
            });

            // Writes: creating or altering a bank account is a treasury-grade
            // action — it determines where money is paid out to.
            Route::middleware('permission:manage_bank_accounts')->group(function () {
                Route::get('/create',        [\App\Http\Controllers\Finance\BankAccountController::class, 'create'])->name('create');
                Route::post('/',             [\App\Http\Controllers\Finance\BankAccountController::class, 'store'])->name('store');
                Route::get('/{bankAccount}/edit',   [\App\Http\Controllers\Finance\BankAccountController::class, 'edit'])->name('edit');
                Route::patch('/{bankAccount}',      [\App\Http\Controllers\Finance\BankAccountController::class, 'update'])->name('update');
                Route::post('/{bankAccount}/activate',   [\App\Http\Controllers\Finance\BankAccountController::class, 'activate'])->name('activate');
                Route::post('/{bankAccount}/deactivate', [\App\Http\Controllers\Finance\BankAccountController::class, 'deactivate'])->name('deactivate');
            });
        });

                // ============================================================
        // Bank Statements (Phase 3e)
        // ============================================================
        Route::prefix('bank-statements')->name('bank-statements.')->group(function () {
            // Reads: statement lines are raw bank transaction data, and the
            // file download is the same disclosure. Gated on the bank-handling
            // permissions rather than view_finance, consistent with Phase 3e
            // treating bank data as narrower than general finance visibility —
            // this keeps manager/dataentry (who hold view_finance) out.
            Route::middleware('permission:import_bank_statement,reconcile_bank_statement')->group(function () {
                Route::get('/',                [\App\Http\Controllers\Finance\BankStatementController::class, 'index'])->name('index');
                Route::get('/{bankStatement}', [\App\Http\Controllers\Finance\BankStatementController::class, 'show'])->whereNumber('bankStatement')->name('show');
                Route::get('/{bankStatement}/file',  [\App\Http\Controllers\Finance\BankStatementController::class, 'downloadFile'])->name('download');
            });

            // Import.
            Route::middleware('permission:import_bank_statement')->group(function () {
                Route::get('/create',          [\App\Http\Controllers\Finance\BankStatementController::class, 'create'])->name('create');
                Route::post('/',               [\App\Http\Controllers\Finance\BankStatementController::class, 'store'])->name('store');
            });

            // Matching and voiding change reconciliation state.
            Route::middleware('permission:reconcile_bank_statement')->group(function () {
                Route::post('/{bankStatement}/match', [\App\Http\Controllers\Finance\BankStatementController::class, 'runMatch'])->name('match');
                Route::post('/{bankStatement}/void',  [\App\Http\Controllers\Finance\BankStatementController::class, 'voidStatement'])->name('void');

                // Per-line actions.
                Route::post('/lines/{bankStatementLine}/match',   [\App\Http\Controllers\Finance\BankStatementController::class, 'manualMatch'])->name('lines.match');
                Route::post('/lines/{bankStatementLine}/unmatch', [\App\Http\Controllers\Finance\BankStatementController::class, 'unmatch'])->name('lines.unmatch');
                Route::post('/lines/{bankStatementLine}/resolve', [\App\Http\Controllers\Finance\BankStatementController::class, 'markResolve'])->name('lines.resolve');
            });
        });

                // ============================================================
        // Bank Reconciliation (Phase 3e)
        // ============================================================
        Route::prefix('bank-reconciliation')->name('bank-reconciliation.')->group(function () {
            // Reads — same narrower bank-data boundary as bank statements.
            Route::middleware('permission:import_bank_statement,reconcile_bank_statement')->group(function () {
                Route::get('/',         [\App\Http\Controllers\Finance\BankReconciliationController::class, 'index'])->name('index');
                Route::get('/{bankReconciliation}',          [\App\Http\Controllers\Finance\BankReconciliationController::class, 'show'])->whereNumber('bankReconciliation')->name('show');
            });

            // Every write here moves reconciliation state, including reopen
            // and archive, which can unwind a closed reconciliation.
            Route::middleware('permission:reconcile_bank_statement')->group(function () {
                Route::get('/create',   [\App\Http\Controllers\Finance\BankReconciliationController::class, 'create'])->name('create');
                Route::post('/',        [\App\Http\Controllers\Finance\BankReconciliationController::class, 'store'])->name('store');
                Route::post('/{bankReconciliation}/refresh', [\App\Http\Controllers\Finance\BankReconciliationController::class, 'refresh'])->name('refresh');
                Route::post('/{bankReconciliation}/reconcile', [\App\Http\Controllers\Finance\BankReconciliationController::class, 'reconcile'])->name('reconcile');
                Route::post('/{bankReconciliation}/flag',    [\App\Http\Controllers\Finance\BankReconciliationController::class, 'flag'])->name('flag');
                Route::post('/{bankReconciliation}/reopen',  [\App\Http\Controllers\Finance\BankReconciliationController::class, 'reopen'])->name('reopen');
                Route::post('/{bankReconciliation}/archive', [\App\Http\Controllers\Finance\BankReconciliationController::class, 'archive'])->name('archive');
            });
        });


        /*
        |--------------------------------------------------------------------------
        | Inventory value (read-only)
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:view_inventory_value')
        ->prefix('inventory')
        ->name('inventory.')
        ->group(function () {
            Route::get('/value',                       [InventoryValueController::class, 'index'])->name('value.index');
            Route::get('/value/{itemKind}/{itemId}',   [InventoryValueController::class, 'show'])->name('value.show');
        });


        /*
        |--------------------------------------------------------------------------
        | Inventory account mappings (CRUD)
        |--------------------------------------------------------------------------
        */
        Route::prefix('inventory/account-mappings')
        ->name('inventory.account-mappings.')
        ->group(function () {
            Route::get('/',                       [InventoryAccountMappingController::class, 'index'])
                ->middleware('permission:view_inventory_value')
                ->name('index');
            Route::get('/create',                 [InventoryAccountMappingController::class, 'create'])
                ->middleware('permission:manage_inventory_mappings')
                ->name('create');
            Route::post('/',                      [InventoryAccountMappingController::class, 'store'])
                ->middleware('permission:manage_inventory_mappings')
                ->name('store');
            Route::get('/{mapping}/edit',         [InventoryAccountMappingController::class, 'edit'])
                ->middleware('permission:manage_inventory_mappings')
                ->name('edit');
            Route::put('/{mapping}',              [InventoryAccountMappingController::class, 'update'])
                ->middleware('permission:manage_inventory_mappings')
                ->name('update');
            Route::delete('/{mapping}',           [InventoryAccountMappingController::class, 'destroy'])
                ->middleware('permission:manage_inventory_mappings')
                ->name('destroy');
        });


       /*
        |--------------------------------------------------------------------------
        | Opening balances
        |--------------------------------------------------------------------------
        */
        Route::prefix('inventory/opening-balances')
        ->name('inventory.opening-balances.')
        ->group(function () {
            Route::get('/',               [OpeningBalanceController::class, 'index'])
                ->middleware('permission:view_inventory_value')
                ->name('index');
            Route::get('/create',         [OpeningBalanceController::class, 'create'])
                ->middleware('permission:manage_opening_balances')
                ->name('create');
            Route::post('/',              [OpeningBalanceController::class, 'store'])
                ->middleware('permission:manage_opening_balances')
                ->name('store');
            Route::get('/import',         [OpeningBalanceController::class, 'showImport'])
                ->middleware('permission:manage_opening_balances')
                ->name('import.show');
            Route::post('/import',        [OpeningBalanceController::class, 'import'])
                ->middleware('permission:manage_opening_balances')
                ->name('import');
            Route::get('/{import}',       [OpeningBalanceController::class, 'show'])
                ->middleware('permission:view_inventory_value')
                ->name('show');
        }); 


        /*
        |--------------------------------------------------------------------------
        | GR/IR ageing report
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:view_gr_ir_ageing')
        ->prefix('inventory/gr-ir-ageing')
        ->name('inventory.gr-ir-ageing.')
        ->group(function () {
            Route::get('/',                    [GrIrAgeingController::class, 'index'])->name('index');
            Route::get('/supplier/{supplier}', [GrIrAgeingController::class, 'show'])->name('show');
        });

        /*
        |--------------------------------------------------------------------------
        | Receive against PR (procurement)
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:receive_against_pr')
        ->prefix('procurement/purchase-requests/{purchaseRequest}/receive')
        ->name('procurement.receive-against-pr.')
        ->group(function () {
            Route::get('/',  [ReceiveAgainstPurchaseRequestController::class, 'create'])->name('create');
            Route::post('/', [ReceiveAgainstPurchaseRequestController::class, 'store'])->name('store');
        });


        /*
        |--------------------------------------------------------------------------
        | Feed Issues (operational + read)
        |--------------------------------------------------------------------------
        */
        Route::prefix('feed/issues')
        ->name('feed.issues.')
        ->group(function () {
            Route::get('/',          [FeedIssueController::class, 'index'])
                ->middleware('permission:view_group_pools,manage_feed_issues')
                ->name('index');
            Route::get('/create',    [FeedIssueController::class, 'create'])
                ->middleware('permission:manage_feed_issues')
                ->name('create');
            Route::post('/',         [FeedIssueController::class, 'store'])
                ->middleware('permission:manage_feed_issues')
                ->name('store');
            Route::get('/{issue}',   [FeedIssueController::class, 'show'])
                ->middleware('permission:view_group_pools,manage_feed_issues')
                ->name('show');
        });

        /*
        |--------------------------------------------------------------------------
        | Group Cost Pools (read-only)
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:view_group_pools')
        ->prefix('costing/group-pools')
        ->name('costing.group-pools.')
        ->group(function () {
            Route::get('/',          [GroupCostPoolController::class, 'index'])->name('index');
            Route::get('/{pool}',    [GroupCostPoolController::class, 'show'])->name('show');
        });

        /*
        |--------------------------------------------------------------------------
        | Opening Pig Inventory (cutover)
        |--------------------------------------------------------------------------
        */
        Route::prefix('costing/opening-pig-inventory')
        ->name('costing.opening-pig-inventory.')
        ->group(function () {
            Route::get('/',         [OpeningPigPoolController::class, 'index'])
                ->middleware('permission:view_group_pools')
                ->name('index');
            Route::get('/create',   [OpeningPigPoolController::class, 'create'])
                ->middleware('permission:manage_opening_pig_pool')
                ->name('create');
            Route::post('/',        [OpeningPigPoolController::class, 'store'])
                ->middleware('permission:manage_opening_pig_pool')
                ->name('store');
            Route::get('/{import}', [OpeningPigPoolController::class, 'show'])
                ->middleware('permission:view_group_pools')
                ->name('show');
        });

        /*
        |--------------------------------------------------------------------------
        | Mortality Writeoffs (read-only audit)
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:manage_pig_mortality')
        ->prefix('costing/mortality-writeoffs')
        ->name('costing.mortality-writeoffs.')
        ->group(function () {
            Route::get('/', [PigMortalityWriteoffController::class, 'index'])->name('index');
        });


        /*
        | Financial statements (read).
        */
        Route::middleware('permission:view_financial_reports')
        ->prefix('reports')
        ->name('reports.')
        ->group(function () {
            Route::get('/income-statement', IncomeStatementController::class)->name('income-statement');
            Route::get('/balance-sheet',    BalanceSheetController::class)->name('balance-sheet');
            Route::get('/cash-flow',        CashFlowController::class)->name('cash-flow');
        });


        /*
        | Period close workflow.
        */
        Route::prefix('close')->name('close.')->group(function () {
            Route::get('/', [PeriodCloseController::class, 'index'])
                ->middleware('permission:view_period_close')->name('index');

            Route::get('/period/{period}', [PeriodCloseController::class, 'show'])
                ->middleware('permission:view_period_close')->name('show');

            Route::post('/period/{period}/close', [PeriodCloseController::class, 'close'])
                ->middleware('permission:manage_period_close')->name('close');

            Route::post('/period/{period}/reopen', [PeriodCloseController::class, 'reopen'])
                ->middleware('permission:override_period_close')->name('reopen');

            // Close pack viewer.
            Route::get('/runs/{run}', [CloseRunController::class, 'show'])
                ->middleware('permission:view_period_close')->name('run');
        });

        /*
        | Year-end close.
        */
        Route::prefix('year-end')->name('year-end.')->group(function () {
            Route::get('/', [YearEndCloseController::class, 'index'])
                ->middleware('permission:view_period_close')->name('index');

            Route::post('/{year}/run', [YearEndCloseController::class, 'run'])
                ->middleware('permission:manage_year_end_close')->name('run');

            Route::post('/{year}/reverse', [YearEndCloseController::class, 'reverse'])
                ->middleware('permission:override_period_close')->name('reverse');
        });

        Route::middleware('permission:view_financial_reports')
        ->prefix('biological-assets')
        ->name('biological-assets.')
        ->group(function () {
            Route::get('/',           [BiologicalAssetController::class, 'index'])->name('index');
            Route::get('/fair-value', [BiologicalAssetController::class, 'fairValue'])->name('fair-value');
        });


        Route::prefix('loans')->name('loans.')->group(function () {
            Route::get('/',                       [LoanController::class, 'index'])->middleware('permission:view_loans')->name('index');
            Route::get('/create',                 [LoanController::class, 'create'])->middleware('permission:view_loans')->name('create');
            Route::post('/',                      [LoanController::class, 'store'])->middleware('permission:manage_loans')->name('store');
            Route::get('/reclassification',       [LoanController::class, 'reclassification'])->middleware('permission:view_loans')->name('reclassification');
            Route::get('/{loan}',                 [LoanController::class, 'show'])->middleware('permission:view_loans')->name('show');
            Route::post('/{loan}/disburse',       [LoanController::class, 'disburse'])->middleware('permission:manage_loans')->name('disburse');
            Route::post('/{loan}/repay',          [LoanController::class, 'repay'])->middleware('permission:manage_loans')->name('repay');
        });
        
        Route::get('/treasury', TreasuryController::class)->middleware('permission:view_treasury')->name('treasury');


        Route::prefix('investors')->name('investors.')->group(function () {
            Route::get('/',                    [InvestorController::class, 'index'])->middleware('permission:view_investors')->name('index');
            Route::get('/create',              [InvestorController::class, 'create'])->middleware('permission:manage_investors')->name('create');
            Route::post('/',                   [InvestorController::class, 'store'])->middleware('permission:manage_investors')->name('store');
            Route::get('/cap-table',           [InvestorController::class, 'capTable'])->middleware('permission:view_investors')->name('cap-table');
            Route::get('/allocate',            [InvestorController::class, 'allocateForm'])->middleware('permission:view_investors')->name('allocate.form');
            Route::post('/allocate',           [InvestorController::class, 'allocateProfit'])->middleware('permission:manage_investors')->name('allocate');
            Route::get('/{investor}',          [InvestorController::class, 'show'])->middleware('permission:view_investors')->name('show');
            Route::post('/{investor}/contribute', [InvestorController::class, 'contribute'])->middleware('permission:manage_investors')->name('contribute');
            Route::post('/{investor}/distribute', [InvestorController::class, 'distribute'])->middleware('permission:manage_investors')->name('distribute');
        });

        Route::prefix('feed-sales')->name('feed-sales.')->group(function () {
            Route::get('/',       [FeedSaleController::class, 'index'])->middleware('permission:view_feed_sales')->name('index');
            Route::get('/create', [FeedSaleController::class, 'create'])->middleware('permission:record_feed_sales')->name('create');
            Route::post('/',      [FeedSaleController::class, 'store'])->middleware('permission:record_feed_sales')->name('store');
        });

    });

    /*
    |--------------------------------------------------------------------------
    | Storefront — back-office workspace (Phase 1)
    |--------------------------------------------------------------------------
    | Gated by storefront permissions (the finance-module pattern), not by a
    | single role, so admin/superuser inherit via the superuser bypass and
    | explicit grants while Salesperson gets a scoped subset.
    */
    Route::prefix('storefront-admin')->name('storefront.admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Storefront\AdminStorefrontController::class, 'dashboard'])
            ->middleware('permission:view_storefront_dashboard')->name('dashboard');

        // Listings.
        Route::middleware('permission:view_storefront_listings')->group(function () {
            Route::get('/listings', [\App\Http\Controllers\Storefront\AdminStorefrontController::class, 'index'])->name('listings.index');
            Route::get('/listings/{listing}', [\App\Http\Controllers\Storefront\AdminStorefrontController::class, 'show'])->name('listings.show');
        });

        // Push-to-storefront wizard + listing lifecycle actions.
        Route::middleware('permission:push_to_storefront')->group(function () {
            Route::get('/push', [\App\Http\Controllers\Storefront\PushToStorefrontController::class, 'create'])->name('push.create');
            Route::post('/push', [\App\Http\Controllers\Storefront\PushToStorefrontController::class, 'store'])->name('push.store');
        });

        Route::middleware('permission:manage_storefront_listings')->group(function () {
            Route::post('/listings/{listing}/publish',   [\App\Http\Controllers\Storefront\AdminStorefrontController::class, 'publish'])->name('listings.publish');
            Route::post('/listings/{listing}/unpublish', [\App\Http\Controllers\Storefront\AdminStorefrontController::class, 'unpublish'])->name('listings.unpublish');
            Route::post('/listings/{listing}/archive',   [\App\Http\Controllers\Storefront\AdminStorefrontController::class, 'archive'])->name('listings.archive');
        });

        // Homepage / featured placements.
        Route::middleware('permission:manage_storefront_placements')->group(function () {
            Route::get('/placements',  [\App\Http\Controllers\Storefront\StorefrontPlacementController::class, 'index'])->name('placements.index');
            Route::post('/placements', [\App\Http\Controllers\Storefront\StorefrontPlacementController::class, 'store'])->name('placements.store');
            Route::delete('/placements/{placement}', [\App\Http\Controllers\Storefront\StorefrontPlacementController::class, 'destroy'])->name('placements.destroy');
        });

        // Listing editor + media (Phase 2).
        Route::middleware('permission:manage_storefront_listings')->group(function () {
            Route::get('/listings/{listing}/edit', [\App\Http\Controllers\Storefront\ListingEditorController::class, 'edit'])->name('listings.edit');
            Route::put('/listings/{listing}',      [\App\Http\Controllers\Storefront\ListingEditorController::class, 'update'])->name('listings.update');
        });
        Route::middleware('permission:manage_storefront_media')->group(function () {
            Route::post('/listings/{listing}/media',                 [\App\Http\Controllers\Storefront\ListingEditorController::class, 'addMedia'])->name('listings.media.add');
            Route::post('/listings/{listing}/media/{media}/primary', [\App\Http\Controllers\Storefront\ListingEditorController::class, 'setPrimaryMedia'])->name('listings.media.primary');
            Route::delete('/listings/{listing}/media/{media}',       [\App\Http\Controllers\Storefront\ListingEditorController::class, 'removeMedia'])->name('listings.media.remove');
        });

        // Quotes workspace (Phase 2).
        Route::middleware('permission:view_storefront_quotes')->group(function () {
            Route::get('/quotes',           [\App\Http\Controllers\Storefront\AdminQuoteController::class, 'index'])->name('quotes.index');
            Route::get('/quotes/{quote}',   [\App\Http\Controllers\Storefront\AdminQuoteController::class, 'show'])->name('quotes.show');
        });
        Route::middleware('permission:manage_storefront_quotes')->group(function () {
            Route::post('/quotes/{quote}/review',  [\App\Http\Controllers\Storefront\AdminQuoteController::class, 'review'])->name('quotes.review');
            Route::post('/quotes/{quote}/quote',   [\App\Http\Controllers\Storefront\AdminQuoteController::class, 'quote'])->name('quotes.quote');
            Route::post('/quotes/{quote}/accept',  [\App\Http\Controllers\Storefront\AdminQuoteController::class, 'accept'])->name('quotes.accept');
            Route::post('/quotes/{quote}/decline', [\App\Http\Controllers\Storefront\AdminQuoteController::class, 'decline'])->name('quotes.decline');
            Route::post('/quotes/{quote}/convert', [\App\Http\Controllers\Storefront\AdminQuoteController::class, 'convert'])->name('quotes.convert');
        });

        // Orders workspace (Phase 3).
        Route::middleware('permission:view_storefront_orders')->group(function () {
            Route::get('/orders',         [\App\Http\Controllers\Storefront\AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [\App\Http\Controllers\Storefront\AdminOrderController::class, 'show'])->name('orders.show');
        });
        Route::middleware('permission:manage_storefront_orders')->group(function () {
            Route::post('/orders/{order}/confirm',        [\App\Http\Controllers\Storefront\AdminOrderController::class, 'confirm'])->name('orders.confirm');
            Route::post('/orders/{order}/cancel',         [\App\Http\Controllers\Storefront\AdminOrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/{order}/dispatch',       [\App\Http\Controllers\Storefront\AdminOrderController::class, 'fulfil'])->name('orders.dispatch');
            Route::post('/orders/{order}/complete',       [\App\Http\Controllers\Storefront\AdminOrderController::class, 'complete'])->name('orders.complete');
            Route::post('/orders/{order}/note',           [\App\Http\Controllers\Storefront\AdminOrderController::class, 'addNote'])->name('orders.note');
            Route::post('/orders/{order}/verify-payment', [\App\Http\Controllers\Storefront\AdminOrderController::class, 'verifyPayment'])->name('orders.verify-payment');
        });

        // Reports (Phase 5).
        Route::middleware('permission:view_storefront_reports')->group(function () {
            Route::get('/reports/sales', [\App\Http\Controllers\Storefront\StorefrontReportController::class, 'sales'])->name('reports.sales');
        });

        // Refunds (Phase 6) — treasurer-grade, NOT salesperson.
        Route::middleware('permission:refund_storefront_orders')->group(function () {
            Route::post('/orders/{order}/refund', [\App\Http\Controllers\Storefront\AdminOrderController::class, 'refund'])->name('orders.refund');
        });
    });

});

/*
|--------------------------------------------------------------------------
| Storefront — customer account area (Phase 1)
|--------------------------------------------------------------------------
| Authenticated, but NOT behind operational role/permission gates. Any
| authenticated user with a valid organization context (enforced by the
| appended EnsureOrganizationContext middleware) may reach their own account.
*/
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('account')->name('storefront.account.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Storefront\CustomerAccountController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [\App\Http\Controllers\Storefront\CustomerAccountController::class, 'profile'])->name('profile');
        Route::get('/addresses', [\App\Http\Controllers\Storefront\CustomerAccountController::class, 'addresses'])->name('addresses');
        Route::post('/addresses', [\App\Http\Controllers\Storefront\CustomerAccountController::class, 'storeAddress'])->name('addresses.store');
        // Phase 2 — saved items, quotes, favourite toggle.
        Route::get('/saved', [\App\Http\Controllers\Storefront\CustomerAccountController::class, 'savedItems'])->name('saved');
        Route::get('/quotes', [\App\Http\Controllers\Storefront\CustomerAccountController::class, 'quotes'])->name('quotes');
        Route::post('/favourites/{listing}/toggle', [\App\Http\Controllers\Storefront\FavouriteController::class, 'toggle'])->name('favourites.toggle');

        // Phase 3 — cart, checkout, payment callback, orders.
        Route::get('/cart', [\App\Http\Controllers\Storefront\CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/add/{listing}', [\App\Http\Controllers\Storefront\CartController::class, 'add'])->name('cart.add');
        Route::patch('/cart/items/{item}', [\App\Http\Controllers\Storefront\CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/items/{item}', [\App\Http\Controllers\Storefront\CartController::class, 'remove'])->name('cart.remove');

        Route::get('/checkout', [\App\Http\Controllers\Storefront\CheckoutController::class, 'show'])->name('checkout.show');
        Route::post('/checkout', [\App\Http\Controllers\Storefront\CheckoutController::class, 'place'])->name('checkout.place');
        Route::get('/paystack/callback', [\App\Http\Controllers\Storefront\PaystackWebhookController::class, 'callback'])->name('paystack.callback');

        Route::get('/orders', [\App\Http\Controllers\Storefront\CustomerOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [\App\Http\Controllers\Storefront\CustomerOrderController::class, 'show'])->name('orders.show');
    });

/*
|--------------------------------------------------------------------------
| Storefront — public shop (Phase 1)
|--------------------------------------------------------------------------
| No authentication required. The appended EnsureOrganizationContext
| middleware passes through unauthenticated requests, so these are open.
*/
Route::prefix('shop')->name('storefront.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Storefront\StorefrontController::class, 'home'])->name('home');
    Route::get('/browse', [\App\Http\Controllers\Storefront\StorefrontController::class, 'shop'])->name('shop');

    // Phase 2 — public listing detail + quote-request flow.
    Route::get('/quote', [\App\Http\Controllers\Storefront\QuoteController::class, 'create'])->name('quote.create');
    Route::post('/quote', [\App\Http\Controllers\Storefront\QuoteController::class, 'store'])->middleware('throttle:storefront-quote')->name('quote.store');
    Route::get('/quote/thanks/{reference}', [\App\Http\Controllers\Storefront\QuoteController::class, 'thanks'])->name('quote.thanks');
    Route::get('/listing/{slug}', [\App\Http\Controllers\Storefront\StorefrontController::class, 'detail'])->name('listing');

    // Phase 3 — Paystack server-to-server webhook (CSRF-exempt; HMAC-verified
    // in the controller). Path must match the exception in bootstrap/app.php.
    Route::post('/paystack/webhook', [\App\Http\Controllers\Storefront\PaystackWebhookController::class, 'webhook'])->middleware('throttle:storefront-webhook')->name('paystack.webhook');
});
