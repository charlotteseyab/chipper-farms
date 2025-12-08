<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PigHealthController;
use App\Http\Controllers\FarmManagerDashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;
use App\Http\Controllers\RoomController;
// Import the new Controller
use App\Http\Controllers\PerformanceMetricController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', function() {
        $user = auth()->user();
        $isSuperuser = $user && optional($user->role)->name === 'superuser';
        if ($isSuperuser) {
            // Superuser sees all notifications
            $notifications = Illuminate\Notifications\DatabaseNotification::query()->latest()->paginate(15);
        } else {
            // Others see only their own
            $notifications = $user->notifications()->latest()->paginate(15);
        }
        return view('notifications.index', compact('notifications'));
    })->name('notifications.index');

    Route::post('/notifications/{id}/read', function(string $id) {
        $user = auth()->user();
        $isSuperuser = $user && optional($user->role)->name === 'superuser';
        $notification = $isSuperuser
            ? Illuminate\Notifications\DatabaseNotification::query()->where('id', $id)->firstOrFail()
            : $user->notifications()->where('id', $id)->firstOrFail();
        if (is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => now()])->save();
        }
        return back();
    })->name('notifications.read');

    Route::post('/notifications/read-all', function() {
        $user = auth()->user();
        $isSuperuser = $user && optional($user->role)->name === 'superuser';
        if ($isSuperuser) {
            Illuminate\Notifications\DatabaseNotification::query()->whereNull('read_at')->update(['read_at' => now()]);
            $remaining = Illuminate\Notifications\DatabaseNotification::whereNull('read_at')->count();
        } else {
            $user->unreadNotifications()->update(['read_at' => now()]);
            $remaining = $user->unreadNotifications()->count();
        }
        // Get the current real-time alert count if service available
        $realTimeAlertCount = 0;
        if (class_exists(\App\Services\RealTimeNotificationService::class)) {
            try {
                $realTimeAlertCount = \App\Services\RealTimeNotificationService::getTotalAlertCount();
            } catch (\Throwable $e) {}
        }
        if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
            return response()->json(['success'=>true,'count' => $remaining + $realTimeAlertCount]);
        }
        return back();
    })->name('notifications.readAll');
});

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/health', [PigHealthController::class, 'index'])->name('health.index');
    Route::put('/health/{health_record}', [HealthRecordController::class, 'update'])->name('health.update');
    Route::get('/health/create-health', [PigHealthController::class, 'createHealth'])->name('health.create');
    Route::get('/health/create-medication', [PigHealthController::class, 'createMedication'])->name('health.create-medication');
    Route::post('/health/store-health', [PigHealthController::class, 'storeHealth'])->name('health.store');
    Route::post('/health/store-medication', [PigHealthController::class, 'storeMedication'])->name('medications.store');
    Route::get('/health/{pig}', [PigHealthController::class, 'show'])->name('health.show');
    Route::get('/health/{pig}/create-health', [PigHealthController::class, 'createHealth'])->name('health.createForPig');
    Route::get('/health/{pig}/create-medication', [PigHealthController::class, 'createMedication'])->name('medications.createForPig');
    Route::post('/health/{pig}/health', [PigHealthController::class, 'storeHealth'])->name('health.storeForPig');
    Route::post('/health/{pig}/medication', [PigHealthController::class, 'storeMedication'])->name('medications.storeForPig');
    Route::get('/medications/{medication}', [PigHealthController::class, 'showMedication'])->name('medications.show');
    Route::get('/medications/{medication}/edit', [PigHealthController::class, 'editMedication'])->name('medications.edit');
    Route::put('/medications/{medication}', [PigHealthController::class, 'updateMedication'])->name('medications.update');
    Route::delete('/medications/{medication}', [PigHealthController::class, 'destroyMedication'])->name('medications.destroy');

    // Medications index & Health reports
    Route::get('/medications', [PigHealthController::class, 'medicationsIndex'])->name('medications.index');
    Route::get('/health-reports', [PigHealthController::class, 'reports'])->name('health.reports');
    Route::get('/health/{pig}/edit', [PigHealthController::class, 'edit'])->name('health.edit');
    Route::delete('/health/{pig}', [PigHealthController::class, 'destroy'])->name('health.destroy');
    Route::get('/export/health.csv', [PigHealthController::class, 'exportHealthCsv'])->name('health.export');
    Route::get('/export/medications.csv', [PigHealthController::class, 'exportMedicationsCsv'])->name('medications.export');
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'activity'
])->group(function () {
    Route::view('/admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Route::get('/manager/dashboard', FarmManagerDashboardController::class)->name('manager.dashboard');
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
    Route::delete('mating/{id}', [App\Http\Controllers\PigManagementController::class, 'destroyMating'])->name('mating.destroy');

    // Gestation Period Management Routes
    Route::get('gestation', [App\Http\Controllers\PigManagementController::class, 'gestationIndex'])->name('gestation.index');
    Route::get('gestation/{mating}/edit', [App\Http\Controllers\PigManagementController::class, 'editGestation'])->name('gestation.edit');
    Route::put('gestation/{mating}', [App\Http\Controllers\PigManagementController::class, 'updateGestation'])->name('gestation.update');
    Route::delete('gestation/{mating}', [App\Http\Controllers\PigManagementController::class, 'destroyGestation'])->name('gestation.destroy');

    // Farrowing routes
    Route::get('farrowing', [App\Http\Controllers\FarrowingController::class, 'index'])->name('farrowing.index');
    Route::get('farrowing/{farrowing}', [App\Http\Controllers\FarrowingController::class, 'show'])->name('farrowing.show');
    Route::get('farrowing/{farrowing}/edit', [App\Http\Controllers\FarrowingController::class, 'edit'])->name('farrowing.edit');
    Route::put('farrowing/{farrowing}', [App\Http\Controllers\FarrowingController::class, 'update'])->name('farrowing.update');
    Route::post('farrowing/{farrowing}/piglet-deaths', [App\Http\Controllers\FarrowingController::class, 'recordPigletDeath'])->name('farrowing.piglet-deaths.record');
    Route::put('farrowing/piglet-deaths/{pigletDeath}', [App\Http\Controllers\FarrowingController::class, 'updatePigletDeath'])->name('farrowing.piglet-deaths.update');
    Route::delete('farrowing/piglet-deaths/{pigletDeath}', [App\Http\Controllers\FarrowingController::class, 'destroyPigletDeath'])->name('farrowing.piglet-deaths.destroy');
    Route::post('gestation/{id}/farrowing', [App\Http\Controllers\PigManagementController::class, 'recordFarrowing'])->name('gestation.farrowing');
    Route::post('gestation/{id}/update-status', [App\Http\Controllers\PigManagementController::class, 'updateGestationStatus'])->name('gestation.update-status');

    // Piglet Tracking routes
    Route::get('piglets/tracking', [App\Http\Controllers\FarrowingController::class, 'pigletTracking'])->name('piglets.tracking');

    // Archived pigs (soft deletes)
    Route::get('pigs/archived', [App\Http\Controllers\PigManagementController::class, 'archived'])->name('pigs.archived');
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
    Route::get('weights', [App\Http\Controllers\WeightManagementController::class, 'index'])->name('weights.index');
    Route::get('weights/create', [App\Http\Controllers\WeightManagementController::class, 'create'])->name('weights.create');
    Route::post('weights', [App\Http\Controllers\WeightManagementController::class, 'store'])->name('weights.store');
    Route::get('weights/{weight}/edit', [App\Http\Controllers\WeightManagementController::class, 'edit'])->name('weights.edit');
    Route::put('weights/{weight}', [App\Http\Controllers\WeightManagementController::class, 'update'])->name('weights.update');
    Route::get('weights/reports', [App\Http\Controllers\WeightManagementController::class, 'reports'])->name('weights.reports');
    Route::get('api/pigs/{pig}/previous-weight', [App\Http\Controllers\WeightManagementController::class, 'getPreviousWeight'])->name('api.pigs.previous-weight');
    Route::get('api/pigs/{pig}/expected-weight', [App\Http\Controllers\WeightManagementController::class, 'getExpectedWeight'])->name('api.pigs.expected-weight');
    Route::get('weights/reports/export-pdf', [App\Http\Controllers\WeightManagementController::class, 'exportPDF'])->name('weights.reports.export-pdf');

    // PDF export for all weights (filtered)
    Route::get('weights/export/pdf', [App\Http\Controllers\WeightManagementController::class, 'exportAllPDF'])->name('weights.index.export-pdf');

    // Weight Standards Routes (Admin, Manager and Superuser only)
    Route::middleware(['role:admin,manager,superuser'])->group(function () {
        Route::resource('weight-standards', App\Http\Controllers\WeightStandardsController::class);
        Route::get('weight-standards-bulk/create', [App\Http\Controllers\WeightStandardsController::class, 'bulkCreate'])->name('weight-standards.bulk-create');
        Route::post('weight-standards-bulk', [App\Http\Controllers\WeightStandardsController::class, 'bulkStore'])->name('weight-standards.bulk-store');
    });

    // Weight Alerts Management
    Route::resource('weight-alerts', App\Http\Controllers\WeightAlertsController::class);
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
    Route::resource('structures', App\Http\Controllers\StructureController::class);
    Route::post('structures/{structure}/rooms', [App\Http\Controllers\RoomController::class, 'store'])->name('structures.rooms.store');
    Route::delete('structures/{structure}/rooms/{room}', [App\Http\Controllers\RoomController::class, 'destroy'])->name('structures.rooms.destroy');
    Route::get('rooms/{room}', [App\Http\Controllers\RoomController::class, 'show'])->name('rooms.show');
    Route::get('rooms/{room}/edit', [App\Http\Controllers\RoomController::class, 'edit'])->name('rooms.edit');
    Route::put('rooms/{room}', [App\Http\Controllers\RoomController::class, 'update'])->name('rooms.update');
    Route::post('rooms', [App\Http\Controllers\RoomController::class, 'storeGlobal'])->name('rooms.store');
    Route::get('api/structures/{structure}/rooms', [App\Http\Controllers\RoomController::class, 'listByStructure'])->name('api.structures.rooms');

    // Settings Routes
    Route::get('settings/setup', [App\Http\Controllers\SettingsController::class, 'setup'])->name('settings.setup');
    Route::post('settings/setup/breeding', [App\Http\Controllers\SettingsController::class, 'saveBreeding'])->name('settings.setup.breeding.save');
    Route::post('settings/setup/breeding/reset', [App\Http\Controllers\SettingsController::class, 'resetBreeding'])->name('settings.setup.breeding.reset');
    Route::get('settings/activity-log', [App\Http\Controllers\SettingsController::class, 'activityLog'])->name('settings.activity-log');

    // PERFORMANCE METRICS ROUTE (NEW)
    Route::get('metrics', [PerformanceMetricController::class, 'index'])->name('performance_metrics.index');
    // Route::get('settings/metrics', [App\Http\Controllers\SettingsController::class, 'metrics'])->name('settings.metrics'); // Existing, but commented out.

    
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

    // Feed Management (Scheduling) Routes
    Route::get('feeds/schedule/create', [App\Http\Controllers\FeedUiController::class, 'createSchedule'])->name('feeds.schedule.create');
    Route::get('feeds/schedule', [App\Http\Controllers\FeedUiController::class, 'schedule'])->name('feeds.schedule');
    Route::post('feeds/schedule', [App\Http\Controllers\FeedUiController::class, 'storeSchedule'])->name('feeds.schedule.store');
    Route::get('feeds/schedule/{schedule}', [App\Http\Controllers\FeedUiController::class, 'showSchedule'])->name('feeds.schedule.show');
    Route::post('feeds/schedule/{schedule}/fed', [App\Http\Controllers\FeedUiController::class, 'markFed'])->name('feeds.schedule.mark-fed');
    Route::post('feeds/schedule/{schedule}/skipped', [App\Http\Controllers\FeedUiController::class, 'markSkipped'])->name('feeds.schedule.mark-skipped');

    // Granular Feed Allocation Route (for Modal submission)
    Route::post('feeds/schedule', [App\Http\Controllers\FeedUiController::class, 'storeSchedule'])->name('feeds.allocation.store');

    // Feed Reports Routes
    Route::get('feeds/reports', [App\Http\Controllers\FeedUiController::class, 'reports'])->name('feeds.reports');
    Route::get('feeds/reports/export/pdf', [App\Http\Controllers\FeedUiController::class, 'exportReportsPdf'])->name('feeds.reports.export.pdf');

    // Feed Management (Inventory) Routes
    Route::get('feeds', [App\Http\Controllers\FeedUiController::class, 'index'])->name('feeds.index');
    Route::get('feeds/create', [App\Http\Controllers\FeedUiController::class, 'create'])->name('feeds.create');
    Route::post('feeds', [App\Http\Controllers\FeedUiController::class, 'store'])->name('feeds.store');
    Route::get('feeds/{feed}', [App\Http\Controllers\FeedUiController::class, 'show'])->name('feeds.show');
    Route::get('feeds/{feed}/edit', [App\Http\Controllers\FeedUiController::class, 'edit'])->name('feeds.edit'); // Corrected typo in controller name
    Route::put('feeds/{feed}', [App\Http\Controllers\FeedUiController::class, 'update'])->name('feeds.update');
    Route::delete('feeds/{feed}', [App\Http\Controllers\FeedUiController::class, 'destroy'])->name('feeds.destroy');

    // === Location API Routes (NEW) ===

    // API routes for the cascading Structure/Room/Pig selection
    Route::prefix('api')->group(function () {
    // 1. Fetches Rooms belonging to a selected Structure
    Route::get('structures/{structureId}/rooms', [RoomController::class, 'apiListRoomsByStructure']);

    // 2. Fetches Pigs belonging to a selected Room
    Route::get('rooms/{roomId}/pigs', [RoomController::class, 'apiListPigsByRoom']);

});

   

    // Feed Inventory & Purchase Requests (all roles now)
    //Route::middleware(['role:manager,admin'])->group(function () {
        // Feed Inventory
        Route::get('feed-inventory', [App\Http\Controllers\FeedInventoryController::class, 'index'])->name('feed-inventory.index');
        Route::get('feed-inventory/create', [App\Http\Controllers\FeedInventoryController::class, 'create'])->name('feed-inventory.create');
        Route::post('feed-inventory', [App\Http\Controllers\FeedInventoryController::class, 'store'])->name('feed-inventory.store');
        Route::get('feed-inventory/analytics', [App\Http\Controllers\FeedInventoryController::class, 'analytics'])->name('feed-inventory.analytics');
        Route::get('feed-inventory/{inventory}/edit', [App\Http\Controllers\FeedInventoryController::class, 'edit'])->name('feed-inventory.edit');
        Route::put('feed-inventory/{inventory}', [App\Http\Controllers\FeedInventoryController::class, 'update'])->name('feed-inventory.update');
        Route::delete('feed-inventory/{inventory}', [App\Http\Controllers\FeedInventoryController::class, 'destroy'])->name('feed-inventory.destroy');
        // Feed Purchase Requests
        Route::get('feed-purchase-requests', [App\Http\Controllers\FeedPurchaseRequestController::class, 'index'])->name('feed-purchase-requests.index');
        Route::get('feed-purchase-requests/create', [App\Http\Controllers\FeedPurchaseRequestController::class, 'create'])->name('feed-purchase-requests.create');
        Route::post('feed-purchase-requests', [App\Http\Controllers\FeedPurchaseRequestController::class, 'store'])->name('feed-purchase-requests.store');
        Route::get('feed-purchase-requests/{request}', [App\Http\Controllers\FeedPurchaseRequestController::class, 'show'])->name('feed-purchase-requests.show');
        Route::post('feed-purchase-requests/{request}/approve', [App\Http\Controllers\FeedPurchaseRequestController::class, 'approve'])->middleware('role:admin,superuser')->name('feed-purchase-requests.approve');
        Route::post('feed-purchase-requests/{request}/reject', [App\Http\Controllers\FeedPurchaseRequestController::class, 'reject'])->middleware('role:admin,superuser')->name('feed-purchase-requests.reject');
        // Add route for marking payment status.
        Route::post('feed-purchase-requests/{request}/mark-paid', [App\Http\Controllers\FeedPurchaseRequestController::class, 'markPaid'])->name('feed-purchase-requests.mark-paid');
        Route::delete('feed-purchase-requests/{request}', [App\Http\Controllers\FeedPurchaseRequestController::class, 'destroy'])->middleware('role:superuser')->name('feed-purchase-requests.destroy');
        Route::get('feed-purchase-requests/{request}/edit', [App\Http\Controllers\FeedPurchaseRequestController::class, 'edit'])->name('feed-purchase-requests.edit');
        Route::put('feed-purchase-requests/{request}', [App\Http\Controllers\FeedPurchaseRequestController::class, 'update'])->name('feed-purchase-requests.update');
    //});

    // Feed Management Setup
    Route::middleware(['role:admin,superuser,manager'])->group(function () {
        Route::resource('feed-types', App\Http\Controllers\FeedTypeController::class);
        Route::resource('feeding-standards', App\Http\Controllers\FeedingStandardController::class);
    });

    // Grouping Rules
    Route::resource('grouping-rules', App\Http\Controllers\GroupingRuleController::class)->middleware('role:admin,superuser,manager');

    // Ingredients & Recipes
    Route::resource('ingredients', App\Http\Controllers\IngredientController::class)->middleware('role:admin,superuser,manager');
    Route::resource('recipes', App\Http\Controllers\FeedRecipeController::class)->middleware('role:admin,superuser,manager');
});