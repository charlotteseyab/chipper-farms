<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::view('/admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Route::view('/manager/dashboard', 'manager.dashboard')->name('manager.dashboard');
    Route::view('/superuser/dashboard', 'superuser.dashboard')->name('superuser.dashboard');
    Route::view('/dataentry/dashboard', 'dataentry.dashboard')->name('dataentry.dashboard');
    
    // Admin API routes
    Route::get('/admin/weight-trends-data', [App\Http\Controllers\AdminController::class, 'getWeightTrendsData'])->name('admin.weight-trends-data');
    Route::get('/api/daily-weights-data', [App\Http\Controllers\DataEntryController::class, 'getDailyWeightsData'])->name('api.daily-weights-data');
    // All Stock Routes
    Route::get('all-stocks', [App\Http\Controllers\PigManagementController::class, 'allIndex'])->name('all-stocks.index');
    Route::get('all-stocks/create', [App\Http\Controllers\PigManagementController::class, 'allCreate'])->name('all-stocks.create');
    Route::get('all-stocks-export/csv', [App\Http\Controllers\PigManagementController::class, 'allExportCsv'])->name('all-stocks.export.csv');
    Route::get('all-stocks-export/pdf', [App\Http\Controllers\PigManagementController::class, 'allExportPdf'])->name('all-stocks.export.pdf');

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

    // Gestation Period Management Routes
    Route::get('gestation', [App\Http\Controllers\PigManagementController::class, 'gestationIndex'])->name('gestation.index');

    // Farrowing routes
    Route::get('farrowing', [App\Http\Controllers\FarrowingController::class, 'index'])->name('farrowing.index');
    Route::get('farrowing/{farrowing}', [App\Http\Controllers\FarrowingController::class, 'show'])->name('farrowing.show');
    Route::post('gestation/{id}/farrowing', [App\Http\Controllers\PigManagementController::class, 'recordFarrowing'])->name('gestation.farrowing');
    Route::post('gestation/{id}/update-status', [App\Http\Controllers\PigManagementController::class, 'updateGestationStatus'])->name('gestation.update-status');

    // Weaning Period Management Routes
    Route::get('weaning', function() {
        return redirect('/weaning/overdue'); // Redirect to most urgent page
    });
    Route::get('weaning/pending', [App\Http\Controllers\PigManagementController::class, 'weaningPending'])->name('weaning.pending');
    Route::get('weaning/in-progress', [App\Http\Controllers\PigManagementController::class, 'weaningInProgress'])->name('weaning.in-progress');
    Route::get('weaning/weaned', [App\Http\Controllers\PigManagementController::class, 'weaningWeaned'])->name('weaning.weaned');
    Route::get('weaning/overdue', [App\Http\Controllers\PigManagementController::class, 'weaningOverdue'])->name('weaning.overdue');
    Route::post('weaning/{id}/wean', [App\Http\Controllers\PigManagementController::class, 'recordWeaning'])->name('weaning.record');
    Route::post('weaning/{id}/complete-maturity', [App\Http\Controllers\PigManagementController::class, 'completeMaturity'])->name('weaning.complete-maturity');
    Route::post('weaning/{id}/update-status', [App\Http\Controllers\PigManagementController::class, 'updateWeaningStatus'])->name('weaning.update-status');
    Route::post('weaning/{id}/update-weight', [App\Http\Controllers\PigManagementController::class, 'updateWeaningWeight'])->name('weaning.update-weight');


    // Stock Type Management Routes
    Route::post('pigs/{id}/promote-to-breeding', [App\Http\Controllers\PigManagementController::class, 'promoteToBreeding'])->name('pigs.promote-to-breeding');
    Route::post('pigs/{id}/demote-to-standard', [App\Http\Controllers\PigManagementController::class, 'demoteToStandard'])->name('pigs.demote-to-standard');

    // Pig Detail View Route
    Route::get('pigs/{id}', [App\Http\Controllers\PigManagementController::class, 'show'])->name('pigs.show');
    Route::delete('pigs/{id}', [App\Http\Controllers\PigManagementController::class, 'destroy'])->name('pigs.destroy');

    // Sale Management Routes
    Route::get('pigs/{id}/sale', [App\Http\Controllers\PigManagementController::class, 'saleForm'])->name('pigs.sale-form');
    Route::post('pigs/{id}/sale', [App\Http\Controllers\PigManagementController::class, 'recordSale'])->name('pigs.record-sale');
    Route::get('sales', [App\Http\Controllers\PigManagementController::class, 'salesIndex'])->name('sales.index');
    Route::get('sales/{id}', [App\Http\Controllers\PigManagementController::class, 'saleShow'])->name('sales.show');
    Route::post('sales/{id}/payment-status', [App\Http\Controllers\PigManagementController::class, 'updatePaymentStatus'])->name('sales.update-payment-status');
    Route::get('sales-export/csv', [App\Http\Controllers\PigManagementController::class, 'salesExportCsv'])->name('sales.export.csv');
    Route::get('sales-export/pdf', [App\Http\Controllers\PigManagementController::class, 'salesExportPdf'])->name('sales.export.pdf');

    // Standard Stock Routes
    Route::get('standard-stocks', [App\Http\Controllers\PigManagementController::class, 'standardIndex'])->name('standard-stocks.index');
    Route::get('standard-stocks/create', [App\Http\Controllers\PigManagementController::class, 'standardCreate'])->name('standard-stocks.create');
    Route::get('standard-stocks-export/csv', [App\Http\Controllers\PigManagementController::class, 'standardExportCsv'])->name('standard-stocks.export.csv');
    Route::get('standard-stocks-export/pdf', [App\Http\Controllers\PigManagementController::class, 'standardExportPdf'])->name('standard-stocks.export.pdf');

    // Weight Management Routes
    Route::get('weights', [App\Http\Controllers\WeightManagementController::class, 'index'])->name('weights.index');
    Route::get('weights/create', [App\Http\Controllers\WeightManagementController::class, 'create'])->name('weights.create');
Route::post('weights', [App\Http\Controllers\WeightManagementController::class, 'store'])->name('weights.store');
Route::get('weights/{weight}/edit', [App\Http\Controllers\WeightManagementController::class, 'edit'])->name('weights.edit');
Route::put('weights/{weight}', [App\Http\Controllers\WeightManagementController::class, 'update'])->name('weights.update');
Route::get('weights/reports', [App\Http\Controllers\WeightManagementController::class, 'reports'])->name('weights.reports');
Route::get('api/pigs/{pig}/previous-weight', [App\Http\Controllers\WeightManagementController::class, 'getPreviousWeight'])->name('api.pigs.previous-weight');
Route::get('api/pigs/{pig}/expected-weight', [App\Http\Controllers\WeightManagementController::class, 'getExpectedWeight'])->name('api.pigs.expected-weight');
    Route::get('weights/reports/export-pdf', [App\Http\Controllers\WeightManagementController::class, 'exportPDF'])->name('weights.reports.export-pdf');

    // Weight Standards Routes (Admin, Manager and Superuser only)
    Route::middleware(['role:admin,manager,superuser'])->group(function () {
        Route::resource('weight-standards', App\Http\Controllers\WeightStandardsController::class);
        Route::get('weight-standards-bulk/create', [App\Http\Controllers\WeightStandardsController::class, 'bulkCreate'])->name('weight-standards.bulk-create');
        Route::post('weight-standards-bulk', [App\Http\Controllers\WeightStandardsController::class, 'bulkStore'])->name('weight-standards.bulk-store');
    });

    // Weight Alerts Management
    Route::resource('weight-alerts', App\Http\Controllers\WeightAlertsController::class);
    Route::post('weight-alerts/{weightAlert}/acknowledge', [App\Http\Controllers\WeightAlertsController::class, 'acknowledge'])->name('weight-alerts.acknowledge');
    Route::post('weight-alerts/bulk-acknowledge', [App\Http\Controllers\WeightAlertsController::class, 'bulkAcknowledge'])->name('weight-alerts.bulk-acknowledge');
    Route::get('weight-alerts-dashboard', [App\Http\Controllers\WeightAlertsController::class, 'dashboard'])->name('weight-alerts.dashboard');

    // Weight System Demo
    Route::get('weight-demo', function () {
        return view('weight-demo');
    })->name('weight-demo');



    // Structure and Room Management Routes
    Route::resource('structures', App\Http\Controllers\StructureController::class);
    Route::post('structures/{structure}/rooms', [App\Http\Controllers\RoomController::class, 'store'])->name('structures.rooms.store');
    Route::delete('structures/{structure}/rooms/{room}', [App\Http\Controllers\RoomController::class, 'destroy'])->name('structures.rooms.destroy');
    Route::get('rooms/{room}', [App\Http\Controllers\RoomController::class, 'show'])->name('rooms.show');
    Route::post('rooms', [App\Http\Controllers\RoomController::class, 'storeGlobal'])->name('rooms.store');
    Route::get('api/structures/{structure}/rooms', [App\Http\Controllers\RoomController::class, 'listByStructure'])->name('api.structures.rooms');

    // Settings Routes
    Route::get('settings/setup', [App\Http\Controllers\SettingsController::class, 'setup'])->name('settings.setup');
    Route::post('settings/setup/breeding', [App\Http\Controllers\SettingsController::class, 'saveBreeding'])->name('settings.setup.breeding.save');
    Route::post('settings/setup/breeding/reset', [App\Http\Controllers\SettingsController::class, 'resetBreeding'])->name('settings.setup.breeding.reset');
    Route::get('settings/activity-log', [App\Http\Controllers\SettingsController::class, 'activityLog'])->name('settings.activity-log');
    
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
});
